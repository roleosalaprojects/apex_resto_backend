<?php

namespace Tests\Feature\API\v1\openclaw;

use App\Models\Accounting\Bank;
use App\Models\Accounting\BankTransaction;
use App\Models\Accounting\Expense;
use App\Models\Accounting\ExpenseCategory;
use App\Models\ApiToken;
use App\Models\Employees\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpenclawExpensesTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected string $plainToken;

    protected Bank $bank;

    protected ExpenseCategory $utilities;

    protected ExpenseCategory $supplies;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::factory()->admin()->create();
        $this->owner = User::factory()->create(['role_id' => $role->id]);
        $this->owner->forceFill(['user_id' => $this->owner->id])->save();

        $this->plainToken = ApiToken::generatePlainToken();
        ApiToken::create([
            'user_id' => $this->owner->user_id,
            'name' => 'Test Bot',
            'token' => ApiToken::hashToken($this->plainToken),
            // Expense create tests need the write ability; default tokens are read-only.
            'abilities' => ['openclaw:read', 'openclaw:expenses:create'],
        ]);

        $this->bank = Bank::create([
            'bank_name' => 'BPI',
            'account_name' => 'Main',
            'account_number' => '1234',
            'account_type' => Bank::TYPE_CHECKING,
            'opening_balance' => 50000,
            'balance' => 50000,
        ]);

        $this->utilities = ExpenseCategory::create([
            'name' => 'Utilities',
            'description' => 'Power, water, internet',
            'status' => 1,
            'created_by' => $this->owner->id,
        ]);
        $this->supplies = ExpenseCategory::create([
            'name' => 'Supplies',
            'status' => 1,
            'created_by' => $this->owner->id,
        ]);
    }

    private function authed(): self
    {
        return $this->withHeader('Authorization', "Bearer {$this->plainToken}");
    }

    public function test_categories_endpoint_lists_active_categories(): void
    {
        ExpenseCategory::create(['name' => 'Inactive', 'status' => 0, 'created_by' => $this->owner->id]);

        $response = $this->authed()->getJson('/api/v1/openclaw/expenses/categories');

        $response->assertStatus(200);
        $names = collect($response->json('data.categories'))->pluck('name')->all();
        $this->assertContains('Utilities', $names);
        $this->assertContains('Supplies', $names);
        $this->assertNotContains('Inactive', $names);
    }

    public function test_store_creates_expense_and_matching_bank_withdrawal(): void
    {
        $response = $this->authed()->postJson('/api/v1/openclaw/expenses', [
            'amount' => 1250,
            'payee' => 'Meralco',
            'expense_date' => now()->toDateString(),
            'bank_id' => $this->bank->id,
            'expense_category_id' => $this->utilities->id,
            'description' => 'Electric bill',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.expense.payee', 'Meralco')
            ->assertJsonPath('data.expense.category_name', 'Utilities')
            ->assertJsonPath('data.bank.id', $this->bank->id)
            ->assertJsonPath('data.bank.name', 'BPI')
            ->assertJsonPath('data.bank_transaction.type', 'Withdrawal');
        $this->assertEqualsWithDelta(50000.0, $response->json('data.bank.old_balance'), 0.001);
        $this->assertEqualsWithDelta(48750.0, $response->json('data.bank.new_balance'), 0.001);
        $this->assertEqualsWithDelta(1250.0, $response->json('data.expense.amount'), 0.001);
        $this->assertEqualsWithDelta(1250.0, $response->json('data.bank_transaction.amount'), 0.001);

        $this->assertSame(48750.0, (float) $this->bank->fresh()->balance);

        $expenseId = $response->json('data.expense.id');
        $expense = Expense::find($expenseId);
        $this->assertNotNull($expense->bank_transaction_id);
        $this->assertSame($this->owner->id, $expense->created_by);

        $tx = BankTransaction::find($expense->bank_transaction_id);
        $this->assertSame(BankTransaction::TYPE_WITHDRAWAL, $tx->type);
        $this->assertSame(1250.0, (float) $tx->amount);
        $this->assertSame(48750.0, (float) $tx->balance_after);
    }

    public function test_store_resolves_category_by_name_case_insensitive(): void
    {
        $response = $this->authed()->postJson('/api/v1/openclaw/expenses', [
            'amount' => 500,
            'payee' => 'Sari-sari',
            'expense_date' => now()->toDateString(),
            'bank_id' => $this->bank->id,
            'category' => 'utilities',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.expense.category_id', $this->utilities->id)
            ->assertJsonPath('data.expense.category_name', 'Utilities');
    }

    public function test_store_returns_422_for_unknown_category_name(): void
    {
        $response = $this->authed()->postJson('/api/v1/openclaw/expenses', [
            'amount' => 500,
            'payee' => 'Anything',
            'expense_date' => now()->toDateString(),
            'bank_id' => $this->bank->id,
            'category' => 'NonexistentCategory',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['category']);
    }

    public function test_store_validates_required_fields(): void
    {
        // bank_id is optional now (cashless / accrual entries).
        $this->authed()->postJson('/api/v1/openclaw/expenses', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['amount', 'payee', 'expense_date']);
    }

    public function test_index_lists_expenses_within_date_range(): void
    {
        Expense::create([
            'reference_number' => 'EXP-A',
            'expense_category_id' => $this->utilities->id,
            'bank_id' => $this->bank->id,
            'payee' => 'Recent',
            'amount' => 100,
            'expense_date' => now()->toDateString(),
            'status' => Expense::STATUS_ACTIVE,
            'created_by' => $this->owner->id,
        ]);
        Expense::create([
            'reference_number' => 'EXP-B',
            'expense_category_id' => $this->utilities->id,
            'bank_id' => $this->bank->id,
            'payee' => 'Old',
            'amount' => 999,
            'expense_date' => now()->subDays(60)->toDateString(),
            'status' => Expense::STATUS_ACTIVE,
            'created_by' => $this->owner->id,
        ]);

        $response = $this->authed()->getJson('/api/v1/openclaw/expenses');

        $response->assertStatus(200);
        $payees = collect($response->json('data.expenses'))->pluck('payee')->all();
        $this->assertContains('Recent', $payees);
        $this->assertNotContains('Old', $payees);
    }

    public function test_summary_totals_and_breakdowns(): void
    {
        Expense::create([
            'reference_number' => 'EXP-1',
            'expense_category_id' => $this->utilities->id,
            'bank_id' => $this->bank->id,
            'payee' => 'A', 'amount' => 1000,
            'expense_date' => now()->toDateString(),
            'status' => Expense::STATUS_ACTIVE,
            'created_by' => $this->owner->id,
        ]);
        Expense::create([
            'reference_number' => 'EXP-2',
            'expense_category_id' => $this->supplies->id,
            'bank_id' => $this->bank->id,
            'payee' => 'B', 'amount' => 250,
            'expense_date' => now()->toDateString(),
            'status' => Expense::STATUS_ACTIVE,
            'created_by' => $this->owner->id,
        ]);

        $response = $this->authed()->getJson('/api/v1/openclaw/expenses/summary?period=this_month');

        $response->assertStatus(200)
            ->assertJsonPath('data.totals.count', 2);
        $this->assertEqualsWithDelta(1250.0, $response->json('data.totals.total'), 0.001);

        $byCategory = collect($response->json('data.by_category'));
        $this->assertEqualsWithDelta(1000.0, $byCategory->firstWhere('category_name', 'Utilities')['total'], 0.001);
        $this->assertEqualsWithDelta(250.0, $byCategory->firstWhere('category_name', 'Supplies')['total'], 0.001);

        $byBank = collect($response->json('data.by_bank'));
        $this->assertEqualsWithDelta(1250.0, $byBank->firstWhere('bank_name', 'BPI')['total'], 0.001);
    }

    public function test_endpoints_require_a_valid_token(): void
    {
        $this->getJson('/api/v1/openclaw/expenses')->assertStatus(401);
        $this->getJson('/api/v1/openclaw/expenses/summary')->assertStatus(401);
        $this->getJson('/api/v1/openclaw/expenses/categories')->assertStatus(401);
        $this->postJson('/api/v1/openclaw/expenses', ['amount' => 100])->assertStatus(401);
    }
}
