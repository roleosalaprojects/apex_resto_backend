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

class OpenclawExpenseVoidTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Bank $bank;

    protected ExpenseCategory $category;

    protected string $voidToken;

    protected string $readOnlyToken;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::factory()->admin()->create();
        $this->owner = User::factory()->create(['role_id' => $role->id]);
        $this->owner->forceFill(['user_id' => $this->owner->id])->save();

        $this->bank = Bank::create([
            'bank_name' => 'BPI',
            'account_name' => 'Main',
            'account_number' => '1',
            'account_type' => Bank::TYPE_CHECKING,
            'opening_balance' => 50000,
            'balance' => 50000,
        ]);
        $this->category = ExpenseCategory::create([
            'name' => 'Utilities', 'status' => 1, 'created_by' => $this->owner->id,
        ]);

        $this->voidToken = $this->mintToken(['openclaw:read', 'openclaw:expenses:create', 'openclaw:expenses:void']);
        $this->readOnlyToken = $this->mintToken(['openclaw:read']);
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

    private function createExpense(float $amount = 1250): Expense
    {
        // Mirror the create flow: deduct from bank, link bank_transaction.
        $balanceBefore = (float) $this->bank->balance;
        $balanceAfter = $balanceBefore - $amount;

        $tx = BankTransaction::create([
            'reference_number' => BankTransaction::generateReferenceNumber(),
            'bank_id' => $this->bank->id,
            'type' => BankTransaction::TYPE_WITHDRAWAL,
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'description' => 'Test expense',
            'payee' => 'Meralco',
            'transaction_date' => now()->toDateString(),
            'created_by' => $this->owner->id,
        ]);
        $this->bank->update(['balance' => $balanceAfter]);

        return Expense::create([
            'reference_number' => Expense::generateReferenceNumber(),
            'expense_category_id' => $this->category->id,
            'bank_id' => $this->bank->id,
            'bank_transaction_id' => $tx->id,
            'payee' => 'Meralco',
            'amount' => $amount,
            'expense_date' => now()->toDateString(),
            'description' => 'Electric bill',
            'status' => Expense::STATUS_ACTIVE,
            'created_by' => $this->owner->id,
        ]);
    }

    public function test_void_marks_expense_voided_and_creates_reversal_deposit(): void
    {
        $expense = $this->createExpense(1250);
        // Bank balance is now 48750 after the expense.
        $this->assertEqualsWithDelta(48750.0, (float) $this->bank->fresh()->balance, 0.001);

        $response = $this->withHeader('Authorization', "Bearer {$this->voidToken}")
            ->postJson("/api/v1/openclaw/expenses/{$expense->id}/void", [
                'reason' => 'Wrong amount entered',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.expense.id', $expense->id)
            ->assertJsonPath('data.expense.status', Expense::STATUS_VOIDED)
            ->assertJsonPath('data.expense.status_name', 'Voided')
            ->assertJsonPath('data.expense.void_reason', 'Wrong amount entered')
            ->assertJsonPath('data.bank.id', $this->bank->id)
            ->assertJsonPath('data.reversal_transaction.type', 'Deposit');

        $this->assertEqualsWithDelta(48750.0, $response->json('data.bank.old_balance'), 0.001);
        $this->assertEqualsWithDelta(50000.0, $response->json('data.bank.new_balance'), 0.001);
        $this->assertEqualsWithDelta(1250.0, $response->json('data.reversal_transaction.amount'), 0.001);

        // Bank fully restored.
        $this->assertEqualsWithDelta(50000.0, (float) $this->bank->fresh()->balance, 0.001);

        // Reference uses REV- prefix.
        $this->assertStringStartsWith('REV-', $response->json('data.reversal_transaction.reference_number'));

        // Audit fields persisted.
        $expense->refresh();
        $this->assertNotNull($expense->voided_at);
        $this->assertSame($this->owner->id, $expense->voided_by);
        $this->assertSame('Wrong amount entered', $expense->void_reason);
    }

    public function test_void_works_without_a_reason(): void
    {
        $expense = $this->createExpense(500);

        $response = $this->withHeader('Authorization', "Bearer {$this->voidToken}")
            ->postJson("/api/v1/openclaw/expenses/{$expense->id}/void", []);

        $response->assertStatus(200);
        $this->assertNull($expense->fresh()->void_reason);
    }

    public function test_double_void_returns_409_without_side_effects(): void
    {
        $expense = $this->createExpense(1000);
        $auth = ['Authorization' => "Bearer {$this->voidToken}"];

        $this->withHeaders($auth)->postJson("/api/v1/openclaw/expenses/{$expense->id}/void", ['reason' => 'first'])
            ->assertStatus(200);

        $balanceAfterFirstVoid = (float) $this->bank->fresh()->balance;

        $this->withHeaders($auth)->postJson("/api/v1/openclaw/expenses/{$expense->id}/void", ['reason' => 'second'])
            ->assertStatus(409)
            ->assertJsonPath('success', false);

        // Bank balance unchanged after the rejected double-void.
        $this->assertEqualsWithDelta($balanceAfterFirstVoid, (float) $this->bank->fresh()->balance, 0.001);

        // No second reversal was created (one withdrawal + one deposit total).
        $this->assertSame(2, BankTransaction::count());
    }

    public function test_token_without_void_ability_is_rejected_with_403(): void
    {
        $expense = $this->createExpense(750);

        $createOnlyToken = $this->mintToken(['openclaw:read', 'openclaw:expenses:create']);

        $this->withHeader('Authorization', "Bearer {$createOnlyToken}")
            ->postJson("/api/v1/openclaw/expenses/{$expense->id}/void", ['reason' => 'oops'])
            ->assertStatus(403)
            ->assertJsonPath('message', 'This token is missing the required ability: openclaw:expenses:void.');

        $this->assertFalse($expense->fresh()->isVoided());
    }

    public function test_read_only_token_is_rejected(): void
    {
        $expense = $this->createExpense(750);

        $this->withHeader('Authorization', "Bearer {$this->readOnlyToken}")
            ->postJson("/api/v1/openclaw/expenses/{$expense->id}/void")
            ->assertStatus(403);
    }

    public function test_unauthenticated_void_returns_401(): void
    {
        $expense = $this->createExpense(750);

        $this->postJson("/api/v1/openclaw/expenses/{$expense->id}/void")
            ->assertStatus(401);
    }

    public function test_void_returns_404_for_unknown_expense(): void
    {
        $this->withHeader('Authorization', "Bearer {$this->voidToken}")
            ->postJson('/api/v1/openclaw/expenses/999999/void')
            ->assertStatus(404);
    }
}
