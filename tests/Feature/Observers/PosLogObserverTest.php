<?php

namespace Tests\Feature\Observers;

use App\Models\Accounting\Expense;
use App\Models\Accounting\ExpenseCategory;
use App\Models\Accounting\PosLog;
use App\Models\Employees\Role;
use App\Models\Settings\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosLogObserverTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected User $cashier;

    protected Store $store;

    protected ExpenseCategory $cashOutCategory;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::factory()->admin()->create();
        $this->owner = User::factory()->create(['role_id' => $role->id]);
        $this->owner->forceFill(['user_id' => $this->owner->id])->save();

        $this->cashier = User::factory()->create([
            'role_id' => $role->id,
            'user_id' => $this->owner->user_id,
            'name' => 'Cashier One',
        ]);

        $this->store = Store::factory()->create(['user_id' => $this->owner->user_id]);

        $this->cashOutCategory = ExpenseCategory::create([
            'name' => 'Cash Out',
            'status' => 1,
            'created_by' => $this->owner->id,
        ]);
    }

    public function test_creating_a_cash_out_log_creates_a_matching_expense(): void
    {
        $log = PosLog::create([
            'cash_out' => 500,
            'type' => 12,
            'reason' => 'Petty supplies',
            'pos_id' => 1,
            'store_id' => $this->store->id,
            'user_id' => $this->cashier->id,
        ]);

        $expense = Expense::query()->where('receipt_number', 'POS-CASHOUT-'.$log->id)->first();
        $this->assertNotNull($expense, 'Observer should auto-create the expense');
        $this->assertEqualsWithDelta(500.0, (float) $expense->amount, 0.001);
        $this->assertSame($this->cashOutCategory->id, $expense->expense_category_id);
        $this->assertSame($this->store->id, $expense->store_id);
        $this->assertNull($expense->bank_id);
        $this->assertNull($expense->bank_transaction_id);
        $this->assertSame($this->cashier->id, $expense->created_by);
        $this->assertStringContainsString('Petty supplies', $expense->description);
        // Reason appended to payee so the admin expense table (which
        // shows payee but not description) reflects what the cash-out
        // was for.
        $this->assertSame('POS Cash Out — Petty supplies', $expense->payee);
    }

    public function test_cash_out_without_reason_falls_back_to_plain_payee(): void
    {
        $log = PosLog::create([
            'cash_out' => 300,
            'type' => 12,
            'reason' => null,
            'pos_id' => 1,
            'store_id' => $this->store->id,
            'user_id' => $this->cashier->id,
        ]);

        $expense = Expense::query()->where('receipt_number', 'POS-CASHOUT-'.$log->id)->first();
        $this->assertNotNull($expense);
        $this->assertSame('POS Cash Out', $expense->payee);

        // Also covers the "reason is whitespace-only" branch.
        $log2 = PosLog::create([
            'cash_out' => 250,
            'type' => 12,
            'reason' => '   ',
            'pos_id' => 1,
            'store_id' => $this->store->id,
            'user_id' => $this->cashier->id,
        ]);

        $expense2 = Expense::query()->where('receipt_number', 'POS-CASHOUT-'.$log2->id)->first();
        $this->assertSame('POS Cash Out', $expense2->payee);
    }

    public function test_observer_falls_back_to_uncategorized_when_no_cash_out_category(): void
    {
        $this->cashOutCategory->delete();

        $log = PosLog::create([
            'cash_out' => 100,
            'type' => 12,
            'reason' => 'No category yet',
            'pos_id' => 1,
            'store_id' => $this->store->id,
            'user_id' => $this->cashier->id,
        ]);

        $expense = Expense::query()->where('receipt_number', 'POS-CASHOUT-'.$log->id)->first();
        $this->assertNotNull($expense);
        $this->assertNull($expense->expense_category_id);
    }

    public function test_voiding_a_cash_out_voids_the_mirrored_expense(): void
    {
        $cashOut = PosLog::create([
            'cash_out' => 500,
            'type' => 12,
            'reason' => 'Will be voided',
            'pos_id' => 1,
            'store_id' => $this->store->id,
            'user_id' => $this->cashier->id,
        ]);

        $expense = Expense::query()->where('receipt_number', 'POS-CASHOUT-'.$cashOut->id)->first();
        $this->assertNotNull($expense);
        $this->assertFalse($expense->isVoided());

        // Cashier records a void.
        PosLog::create([
            'cash_out' => 500,
            'type' => 13,
            'reason' => 'Voided',
            'so_id' => $cashOut->id,
            'pos_id' => 1,
            'store_id' => $this->store->id,
            'user_id' => $this->cashier->id,
        ]);

        $expense->refresh();
        $this->assertTrue($expense->isVoided());
        $this->assertSame('POS cash-out voided (auto-sync)', $expense->void_reason);
        $this->assertSame($this->cashier->id, (int) $expense->voided_by);
    }

    public function test_voiding_a_cash_out_with_no_mirrored_expense_is_a_no_op(): void
    {
        // Pre-existing void log with no matching cash-out / mirrored expense.
        PosLog::create([
            'cash_out' => 500,
            'type' => 13,
            'reason' => 'Orphan void',
            'so_id' => 999999,
            'pos_id' => 1,
            'store_id' => $this->store->id,
            'user_id' => $this->cashier->id,
        ]);

        $this->assertSame(0, Expense::count());
    }

    public function test_non_cashout_pos_log_types_do_not_create_expenses(): void
    {
        // Login (type=1), Sale (type=5), Z-Reading (type=10), etc.
        foreach ([1, 5, 10, 11, 14] as $type) {
            PosLog::create([
                'cash_out' => 500,  // present but irrelevant for non-cashout
                'type' => $type,
                'reason' => 'Type '.$type,
                'pos_id' => 1,
                'store_id' => $this->store->id,
                'user_id' => $this->cashier->id,
            ]);
        }

        $this->assertSame(0, Expense::count());
    }

    public function test_zero_or_null_cash_out_does_not_create_an_expense(): void
    {
        // Zero cash_out — nothing to record.
        PosLog::create([
            'cash_out' => 0,
            'type' => 12,
            'pos_id' => 1,
            'store_id' => $this->store->id,
            'user_id' => $this->cashier->id,
        ]);

        // Null cash_out — same, defensively.
        PosLog::create([
            'cash_out' => null,
            'type' => 12,
            'pos_id' => 1,
            'store_id' => $this->store->id,
            'user_id' => $this->cashier->id,
        ]);

        $this->assertSame(0, Expense::count());
    }

    public function test_artisan_sync_after_observer_does_not_duplicate(): void
    {
        $a = PosLog::create([
            'cash_out' => 500, 'type' => 12, 'reason' => 'A',
            'pos_id' => 1, 'store_id' => $this->store->id, 'user_id' => $this->cashier->id,
        ]);
        $b = PosLog::create([
            'cash_out' => 700, 'type' => 12, 'reason' => 'B',
            'pos_id' => 1, 'store_id' => $this->store->id, 'user_id' => $this->cashier->id,
        ]);

        $this->assertSame(2, Expense::count());

        // Run the artisan sync — should be a clean no-op since the observer
        // already created both expenses.
        $this->artisan('cashouts:sync-to-expenses', ['--all' => true])->assertExitCode(0);

        $this->assertSame(2, Expense::count());
        $this->assertNotNull(Expense::query()->where('receipt_number', 'POS-CASHOUT-'.$a->id)->first());
        $this->assertNotNull(Expense::query()->where('receipt_number', 'POS-CASHOUT-'.$b->id)->first());
    }
}
