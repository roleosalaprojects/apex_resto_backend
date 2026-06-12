<?php

namespace Tests\Feature\Admin\Accounting;

use App\Models\Accounting\Bank;
use App\Models\Accounting\BankTransaction;
use App\Models\Accounting\Expense;
use App\Models\Accounting\ExpenseCategory;
use App\Models\Employees\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression: clicking the "eye" icon on the admin Expenses table used
 * to dump raw JSON in the browser. The show controller now content-
 * negotiates — JSON for AJAX, Blade view for browser navigation.
 */
class ExpenseShowViewTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Bank $bank;

    protected ExpenseCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::factory()->admin()->create();
        $this->owner = User::factory()->create(['role_id' => $role->id]);
        $this->owner->forceFill(['user_id' => $this->owner->id])->save();

        $this->bank = Bank::create([
            'bank_name' => 'BDO',
            'account_name' => 'Leteres',
            'account_number' => '041078001670',
            'account_type' => Bank::TYPE_CHECKING,
            'opening_balance' => 1000000,
            'balance' => 800000,
        ]);

        $this->category = ExpenseCategory::create([
            'name' => 'Utilities',
            'description' => null,
            'status' => true,
            'created_by' => $this->owner->id,
        ]);
    }

    private function makeExpense(array $overrides = []): Expense
    {
        return Expense::create(array_merge([
            'reference_number' => Expense::generateReferenceNumber(),
            'expense_category_id' => $this->category->id,
            'store_id' => null,
            'bank_id' => $this->bank->id,
            'bank_transaction_id' => null,
            'payee' => 'Meralco',
            'amount' => 1250,
            'expense_date' => now()->toDateString(),
            'description' => 'May electric bill',
            'receipt_number' => 'MERALCO-001',
            'status' => Expense::STATUS_ACTIVE,
            'created_by' => $this->owner->id,
        ], $overrides));
    }

    public function test_browser_request_returns_html_view_with_expense_details(): void
    {
        $expense = $this->makeExpense();

        $response = $this->actingAs($this->owner)
            ->get(route('expenses.show', $expense));

        $response->assertOk();
        $response->assertSee($expense->reference_number);
        $response->assertSee('Meralco');
        $response->assertSee('1,250.00');
        $response->assertSee('Utilities');
        // Doesn't dump raw JSON.
        $response->assertDontSee('{"success":', false);
    }

    public function test_browser_request_response_is_html_not_json(): void
    {
        $expense = $this->makeExpense();

        $response = $this->actingAs($this->owner)->get(route('expenses.show', $expense));

        $response->assertOk();
        $this->assertStringStartsWith('text/html', (string) $response->headers->get('Content-Type'));
    }

    public function test_ajax_caller_still_receives_json(): void
    {
        // Backward compatibility for any existing tooling that depended
        // on the show endpoint returning JSON. wantsJson() is true when
        // Accept: application/json is sent (Laravel's getJson() helper
        // does this automatically).
        $expense = $this->makeExpense();

        $response = $this->actingAs($this->owner)
            ->getJson(route('expenses.show', $expense));

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonPath('expense.id', $expense->id)
            ->assertJsonPath('expense.payee', 'Meralco');
    }

    public function test_view_includes_bank_movement_block_when_linked_to_a_bank_transaction(): void
    {
        $tx = BankTransaction::create([
            'reference_number' => BankTransaction::generateReferenceNumber(),
            'bank_id' => $this->bank->id,
            'type' => BankTransaction::TYPE_WITHDRAWAL,
            'amount' => 1250,
            'balance_before' => 801250,
            'balance_after' => 800000,
            'description' => 'Expense: May electric',
            'payee' => 'Meralco',
            'transaction_date' => now()->toDateString(),
            'created_by' => $this->owner->id,
        ]);
        $expense = $this->makeExpense(['bank_transaction_id' => $tx->id]);

        $response = $this->actingAs($this->owner)->get(route('expenses.show', $expense));

        $response->assertOk();
        $response->assertSee('Bank Movement');
        $response->assertSee($tx->reference_number);
    }

    public function test_view_marks_cashless_when_no_bank_movement_is_attached(): void
    {
        $expense = $this->makeExpense(['bank_id' => null, 'bank_transaction_id' => null]);

        $response = $this->actingAs($this->owner)->get(route('expenses.show', $expense));

        $response->assertOk();
        $response->assertSee('Cashless entry');
        $response->assertDontSee('Bank Movement', false);
    }

    public function test_voided_expense_shows_void_record_block(): void
    {
        $expense = $this->makeExpense([
            'status' => Expense::STATUS_VOIDED,
            'voided_at' => now(),
            'voided_by' => $this->owner->id,
            'void_reason' => 'Posted to wrong PO',
        ]);

        $response = $this->actingAs($this->owner)->get(route('expenses.show', $expense));

        $response->assertOk();
        $response->assertSee('Voided');
        $response->assertSee('Void Record');
        $response->assertSee('Posted to wrong PO');
    }
}
