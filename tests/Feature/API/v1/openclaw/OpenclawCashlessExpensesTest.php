<?php

namespace Tests\Feature\API\v1\openclaw;

use App\Models\Accounting\Bank;
use App\Models\Accounting\BankTransaction;
use App\Models\Accounting\Expense;
use App\Models\Accounting\ExpenseCategory;
use App\Models\ApiToken;
use App\Models\Employees\Role;
use App\Models\InventoryManagement\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpenclawCashlessExpensesTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected ExpenseCategory $payroll;

    protected Bank $bank;

    protected string $writeToken;

    protected string $readToken;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::factory()->admin()->create();
        $this->owner = User::factory()->create(['role_id' => $role->id]);
        $this->owner->forceFill(['user_id' => $this->owner->id])->save();

        $this->payroll = ExpenseCategory::create([
            'name' => 'Payroll Expense',
            'status' => 1,
            'created_by' => $this->owner->id,
        ]);

        $this->bank = Bank::create([
            'bank_name' => 'BDO',
            'account_name' => 'Leteres',
            'account_number' => '1',
            'account_type' => Bank::TYPE_CHECKING,
            'opening_balance' => 1000000,
            'balance' => 1000000,
        ]);

        $this->writeToken = $this->mintToken([
            'openclaw:read',
            'openclaw:expenses:create',
            'openclaw:expenses:void',
            'openclaw:expenses:update',
        ]);
        $this->readToken = $this->mintToken(['openclaw:read']);
    }

    private function mintToken(array $abilities): string
    {
        $plain = ApiToken::generatePlainToken();
        ApiToken::create([
            'user_id' => $this->owner->user_id,
            'name' => 'Test',
            'token' => ApiToken::hashToken($plain),
            'abilities' => $abilities,
        ]);

        return $plain;
    }

    // ---- create without bank_id ----

    public function test_create_without_bank_id_creates_accounting_entry_only(): void
    {
        $balanceBefore = (float) $this->bank->balance;

        $response = $this->withHeader('Authorization', "Bearer {$this->writeToken}")
            ->postJson('/api/v1/openclaw/expenses', [
                'amount' => 12000,
                'payee' => 'JOEL DURAY',
                'expense_date' => now()->toDateString(),
                'expense_category_id' => $this->payroll->id,
                'description' => 'Payslip 2026-05-01..15',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.bank', null)
            ->assertJsonPath('data.bank_transaction', null)
            ->assertJsonPath('data.expense.payee', 'JOEL DURAY')
            ->assertJsonPath('data.expense.bank_id', null);

        // Bank balance untouched.
        $this->assertEqualsWithDelta($balanceBefore, (float) $this->bank->fresh()->balance, 0.001);

        // No bank_transaction row was created at all.
        $this->assertSame(0, BankTransaction::count());

        $expense = Expense::find($response->json('data.expense.id'));
        $this->assertNull($expense->bank_id);
        $this->assertNull($expense->bank_transaction_id);
    }

    public function test_create_with_bank_id_still_records_withdrawal(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->writeToken}")
            ->postJson('/api/v1/openclaw/expenses', [
                'amount' => 1000,
                'payee' => 'Meralco',
                'expense_date' => now()->toDateString(),
                'bank_id' => $this->bank->id,
                'expense_category_id' => $this->payroll->id,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.bank.id', $this->bank->id);

        $this->assertEqualsWithDelta(999000.0, (float) $this->bank->fresh()->balance, 0.001);
        $this->assertSame(1, BankTransaction::count());
    }

    // ---- void without bank linkage ----

    public function test_void_cashless_expense_skips_reversal(): void
    {
        $expense = Expense::create([
            'reference_number' => Expense::generateReferenceNumber(),
            'expense_category_id' => $this->payroll->id,
            'bank_id' => null,
            'bank_transaction_id' => null,
            'payee' => 'JOEL DURAY',
            'amount' => 12000,
            'expense_date' => now()->toDateString(),
            'status' => Expense::STATUS_ACTIVE,
            'created_by' => $this->owner->id,
        ]);

        $balanceBefore = (float) $this->bank->balance;

        $response = $this->withHeader('Authorization', "Bearer {$this->writeToken}")
            ->postJson("/api/v1/openclaw/expenses/{$expense->id}/void", ['reason' => 'Wrong period']);

        $response->assertStatus(200)
            ->assertJsonPath('data.bank', null)
            ->assertJsonPath('data.reversal_transaction', null)
            ->assertJsonPath('data.expense.status', Expense::STATUS_VOIDED);

        $this->assertTrue($expense->fresh()->isVoided());
        $this->assertSame('Wrong period', $expense->fresh()->void_reason);

        // Bank balance untouched + no reversal transaction created.
        $this->assertEqualsWithDelta($balanceBefore, (float) $this->bank->fresh()->balance, 0.001);
        $this->assertSame(0, BankTransaction::count());
    }

    // ---- update endpoint ----

    public function test_update_changes_non_financial_fields(): void
    {
        $supplier = Supplier::factory()->create(['user_id' => $this->owner->user_id]);
        $expense = Expense::create([
            'reference_number' => Expense::generateReferenceNumber(),
            'expense_category_id' => $this->payroll->id,
            'bank_id' => null,
            'payee' => 'JOEL DURAY',
            'amount' => 12000,
            'expense_date' => '2026-05-01',
            'description' => 'Payslip',
            'status' => Expense::STATUS_ACTIVE,
            'created_by' => $this->owner->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->writeToken}")
            ->patchJson("/api/v1/openclaw/expenses/{$expense->id}", [
                'description' => 'Payslip 2026-05-01..15',
                'expense_date' => '2026-05-15',
                'supplier_id' => $supplier->id,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.expense.description', 'Payslip 2026-05-01..15')
            ->assertJsonPath('data.expense.expense_date', '2026-05-15')
            ->assertJsonPath('data.expense.supplier_id', $supplier->id);
    }

    public function test_update_refuses_to_change_amount_or_bank_id(): void
    {
        $expense = Expense::create([
            'reference_number' => Expense::generateReferenceNumber(),
            'expense_category_id' => $this->payroll->id,
            'bank_id' => null,
            'payee' => 'X', 'amount' => 100, 'expense_date' => now()->toDateString(),
            'status' => Expense::STATUS_ACTIVE, 'created_by' => $this->owner->id,
        ]);

        $this->withHeader('Authorization', "Bearer {$this->writeToken}")
            ->patchJson("/api/v1/openclaw/expenses/{$expense->id}", [
                'amount' => 999,
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertEqualsWithDelta(100.0, (float) $expense->fresh()->amount, 0.001);
    }

    public function test_update_refuses_voided_expenses(): void
    {
        $expense = Expense::create([
            'reference_number' => Expense::generateReferenceNumber(),
            'bank_id' => null,
            'payee' => 'X', 'amount' => 100, 'expense_date' => now()->toDateString(),
            'status' => Expense::STATUS_VOIDED, 'created_by' => $this->owner->id,
        ]);

        $this->withHeader('Authorization', "Bearer {$this->writeToken}")
            ->patchJson("/api/v1/openclaw/expenses/{$expense->id}", ['description' => 'late edit'])
            ->assertStatus(409);
    }

    public function test_update_resolves_category_by_name(): void
    {
        $expense = Expense::create([
            'reference_number' => Expense::generateReferenceNumber(),
            'bank_id' => null,
            'payee' => 'X', 'amount' => 100, 'expense_date' => now()->toDateString(),
            'status' => Expense::STATUS_ACTIVE, 'created_by' => $this->owner->id,
            'expense_category_id' => null,
        ]);

        $this->withHeader('Authorization', "Bearer {$this->writeToken}")
            ->patchJson("/api/v1/openclaw/expenses/{$expense->id}", [
                'category' => 'payroll expense',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.expense.category_id', $this->payroll->id)
            ->assertJsonPath('data.expense.category_name', 'Payroll Expense');
    }

    public function test_update_requires_expenses_update_ability(): void
    {
        $expense = Expense::create([
            'reference_number' => Expense::generateReferenceNumber(),
            'bank_id' => null,
            'payee' => 'X', 'amount' => 100, 'expense_date' => now()->toDateString(),
            'status' => Expense::STATUS_ACTIVE, 'created_by' => $this->owner->id,
        ]);

        $this->withHeader('Authorization', "Bearer {$this->readToken}")
            ->patchJson("/api/v1/openclaw/expenses/{$expense->id}", ['description' => 'x'])
            ->assertStatus(403)
            ->assertJsonPath('message', 'This token is missing the required ability: openclaw:expenses:update.');
    }
}
