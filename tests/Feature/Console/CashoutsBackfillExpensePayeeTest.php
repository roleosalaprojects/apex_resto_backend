<?php

namespace Tests\Feature\Console;

use App\Models\Accounting\Expense;
use App\Models\Accounting\ExpenseCategory;
use App\Models\Accounting\PosLog;
use App\Models\Employees\Role;
use App\Models\Settings\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashoutsBackfillExpensePayeeTest extends TestCase
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

    public function test_rewrites_legacy_payee_when_reason_exists(): void
    {
        // Simulate a row created by the OLD observer/sync — payee is the
        // generic legacy value, even though the cash-out has a reason.
        $log = $this->makeCashOutWithoutObserver(500, 'Petty supplies');
        $legacyExpense = Expense::create([
            'reference_number' => Expense::generateReferenceNumber(),
            'expense_category_id' => $this->cashOutCategory->id,
            'store_id' => $this->store->id,
            'payee' => 'POS Cash Out',
            'amount' => 500,
            'expense_date' => now()->toDateString(),
            'description' => 'POS cash-out #'.$log->id.' — Petty supplies',
            'receipt_number' => 'POS-CASHOUT-'.$log->id,
            'status' => Expense::STATUS_ACTIVE,
            'created_by' => $this->owner->id,
        ]);

        $this->artisan('cashouts:backfill-expense-payee', [
            '--target-user-id' => $this->owner->id,
        ])->assertExitCode(0);

        $this->assertSame('POS Cash Out — Petty supplies', $legacyExpense->fresh()->payee);
    }

    public function test_rewrites_payee_for_observer_created_expenses_owned_by_cashier(): void
    {
        // The real-time PosLogObserver writes created_by = cashier.id, not
        // owner.id. The backfill must still find and update these — tenant
        // scoping is via the pos_log → users.user_id join, not via
        // expenses.created_by.
        $log = $this->makeCashOutWithoutObserver(750, 'Lunch run');
        $observerStyleExpense = Expense::create([
            'reference_number' => Expense::generateReferenceNumber(),
            'expense_category_id' => $this->cashOutCategory->id,
            'store_id' => $this->store->id,
            'payee' => 'POS Cash Out',
            'amount' => 750,
            'expense_date' => now()->toDateString(),
            'description' => 'POS cash-out #'.$log->id.' — Lunch run',
            'receipt_number' => 'POS-CASHOUT-'.$log->id,
            'status' => Expense::STATUS_ACTIVE,
            'created_by' => $this->cashier->id,   // ← the bug repro: cashier, not owner
        ]);

        $this->artisan('cashouts:backfill-expense-payee', [
            '--target-user-id' => $this->owner->id,
        ])->assertExitCode(0);

        $this->assertSame('POS Cash Out — Lunch run', $observerStyleExpense->fresh()->payee);
    }

    public function test_does_not_touch_other_tenants_legacy_payees(): void
    {
        // Tenant A's data — eligible for backfill.
        $logA = $this->makeCashOutWithoutObserver(100, 'Tenant A reason');
        $expenseA = Expense::create([
            'reference_number' => Expense::generateReferenceNumber(),
            'expense_category_id' => $this->cashOutCategory->id,
            'store_id' => $this->store->id,
            'payee' => 'POS Cash Out',
            'amount' => 100,
            'expense_date' => now()->toDateString(),
            'description' => 'POS cash-out #'.$logA->id.' — Tenant A reason',
            'receipt_number' => 'POS-CASHOUT-'.$logA->id,
            'status' => Expense::STATUS_ACTIVE,
            'created_by' => $this->cashier->id,
        ]);

        // Tenant B — separate owner, separate cashier, same legacy payee.
        $roleB = Role::factory()->admin()->create();
        $ownerB = User::factory()->create(['role_id' => $roleB->id]);
        $ownerB->forceFill(['user_id' => $ownerB->id])->save();
        $cashierB = User::factory()->create([
            'role_id' => $roleB->id,
            'user_id' => $ownerB->user_id,
            'name' => 'Cashier Other',
        ]);
        $storeB = \App\Models\Settings\Store::factory()->create(['user_id' => $ownerB->user_id]);

        $logB = PosLog::withoutEvents(fn () => PosLog::create([
            'cash_out' => 200,
            'type' => 12,
            'reason' => 'Tenant B reason',
            'pos_id' => 1,
            'store_id' => $storeB->id,
            'user_id' => $cashierB->id,
        ]));
        $expenseB = Expense::create([
            'reference_number' => Expense::generateReferenceNumber(),
            'expense_category_id' => $this->cashOutCategory->id,
            'store_id' => $storeB->id,
            'payee' => 'POS Cash Out',
            'amount' => 200,
            'expense_date' => now()->toDateString(),
            'description' => 'POS cash-out #'.$logB->id.' — Tenant B reason',
            'receipt_number' => 'POS-CASHOUT-'.$logB->id,
            'status' => Expense::STATUS_ACTIVE,
            'created_by' => $cashierB->id,
        ]);

        $this->artisan('cashouts:backfill-expense-payee', [
            '--target-user-id' => $this->owner->id,   // only tenant A
        ])->assertExitCode(0);

        $this->assertSame('POS Cash Out — Tenant A reason', $expenseA->fresh()->payee);
        $this->assertSame('POS Cash Out', $expenseB->fresh()->payee, "Tenant B's data must be untouched.");
    }

    public function test_leaves_rows_alone_when_cash_out_has_no_reason(): void
    {
        $log = $this->makeCashOutWithoutObserver(200, null);
        $expense = Expense::create([
            'reference_number' => Expense::generateReferenceNumber(),
            'expense_category_id' => $this->cashOutCategory->id,
            'store_id' => $this->store->id,
            'payee' => 'POS Cash Out',
            'amount' => 200,
            'expense_date' => now()->toDateString(),
            'description' => 'POS cash-out #'.$log->id,
            'receipt_number' => 'POS-CASHOUT-'.$log->id,
            'status' => Expense::STATUS_ACTIVE,
            'created_by' => $this->owner->id,
        ]);

        $this->artisan('cashouts:backfill-expense-payee', [
            '--target-user-id' => $this->owner->id,
        ])->assertExitCode(0);

        $this->assertSame('POS Cash Out', $expense->fresh()->payee);
    }

    public function test_dry_run_writes_nothing(): void
    {
        $log = $this->makeCashOutWithoutObserver(700, 'Lunch run');
        $expense = Expense::create([
            'reference_number' => Expense::generateReferenceNumber(),
            'expense_category_id' => $this->cashOutCategory->id,
            'store_id' => $this->store->id,
            'payee' => 'POS Cash Out',
            'amount' => 700,
            'expense_date' => now()->toDateString(),
            'description' => 'POS cash-out #'.$log->id.' — Lunch run',
            'receipt_number' => 'POS-CASHOUT-'.$log->id,
            'status' => Expense::STATUS_ACTIVE,
            'created_by' => $this->owner->id,
        ]);

        $this->artisan('cashouts:backfill-expense-payee', [
            '--target-user-id' => $this->owner->id,
            '--dry-run' => true,
        ])->assertExitCode(0);

        $this->assertSame('POS Cash Out', $expense->fresh()->payee);
    }

    public function test_is_idempotent_on_second_run(): void
    {
        $log = $this->makeCashOutWithoutObserver(400, 'Driver toll');
        $expense = Expense::create([
            'reference_number' => Expense::generateReferenceNumber(),
            'expense_category_id' => $this->cashOutCategory->id,
            'store_id' => $this->store->id,
            'payee' => 'POS Cash Out',
            'amount' => 400,
            'expense_date' => now()->toDateString(),
            'description' => 'POS cash-out #'.$log->id.' — Driver toll',
            'receipt_number' => 'POS-CASHOUT-'.$log->id,
            'status' => Expense::STATUS_ACTIVE,
            'created_by' => $this->owner->id,
        ]);

        $this->artisan('cashouts:backfill-expense-payee', [
            '--target-user-id' => $this->owner->id,
        ])->assertExitCode(0);

        $afterFirst = $expense->fresh()->payee;
        $this->assertSame('POS Cash Out — Driver toll', $afterFirst);

        // Second run sees no rows on the legacy payee and is a clean no-op.
        $this->artisan('cashouts:backfill-expense-payee', [
            '--target-user-id' => $this->owner->id,
        ])->assertExitCode(0);

        $this->assertSame($afterFirst, $expense->fresh()->payee);
    }

    public function test_skips_non_legacy_payees(): void
    {
        $log = $this->makeCashOutWithoutObserver(150, 'Something');
        // Already migrated row (payee already includes the reason). The
        // backfill must not touch it.
        $expense = Expense::create([
            'reference_number' => Expense::generateReferenceNumber(),
            'expense_category_id' => $this->cashOutCategory->id,
            'store_id' => $this->store->id,
            'payee' => 'POS Cash Out — Something',
            'amount' => 150,
            'expense_date' => now()->toDateString(),
            'description' => 'POS cash-out #'.$log->id.' — Something',
            'receipt_number' => 'POS-CASHOUT-'.$log->id,
            'status' => Expense::STATUS_ACTIVE,
            'created_by' => $this->owner->id,
        ]);

        $this->artisan('cashouts:backfill-expense-payee', [
            '--target-user-id' => $this->owner->id,
        ])->assertExitCode(0);

        $this->assertSame('POS Cash Out — Something', $expense->fresh()->payee);
    }

    public function test_skips_non_cashout_expenses(): void
    {
        // A manually-entered expense that happens to have payee = "POS
        // Cash Out" (unusual but possible) should not be touched because
        // its receipt_number is not POS-CASHOUT-*.
        $expense = Expense::create([
            'reference_number' => Expense::generateReferenceNumber(),
            'expense_category_id' => $this->cashOutCategory->id,
            'store_id' => $this->store->id,
            'payee' => 'POS Cash Out',
            'amount' => 100,
            'expense_date' => now()->toDateString(),
            'description' => 'Manually entered',
            'receipt_number' => 'MANUAL-001',
            'status' => Expense::STATUS_ACTIVE,
            'created_by' => $this->owner->id,
        ]);

        $this->artisan('cashouts:backfill-expense-payee', [
            '--target-user-id' => $this->owner->id,
        ])->assertExitCode(0);

        $this->assertSame('POS Cash Out', $expense->fresh()->payee);
    }

    public function test_rewrites_created_by_from_tenant_owner_to_cashier(): void
    {
        // Simulates a row created by the OLD artisan sync — payee may
        // already have been corrected by an earlier backfill run, but
        // created_by still points at the tenant owner.
        $log = $this->makeCashOutWithoutObserver(800, 'Old artisan sync row');
        $expense = Expense::create([
            'reference_number' => Expense::generateReferenceNumber(),
            'expense_category_id' => $this->cashOutCategory->id,
            'store_id' => $this->store->id,
            'payee' => 'POS Cash Out — Old artisan sync row',  // already corrected
            'amount' => 800,
            'expense_date' => now()->toDateString(),
            'description' => 'POS cash-out #'.$log->id.' — Old artisan sync row',
            'receipt_number' => 'POS-CASHOUT-'.$log->id,
            'status' => Expense::STATUS_ACTIVE,
            'created_by' => $this->owner->id,   // ← wrong; should be cashier
        ]);

        $this->artisan('cashouts:backfill-expense-payee', [
            '--target-user-id' => $this->owner->id,
        ])->assertExitCode(0);

        $fresh = $expense->fresh();
        $this->assertSame($this->cashier->id, (int) $fresh->created_by);
        // payee was already correct — shouldn't have been re-written.
        $this->assertSame('POS Cash Out — Old artisan sync row', $fresh->payee);
    }

    public function test_corrects_payee_and_created_by_in_one_pass(): void
    {
        // The full legacy artisan-sync row: wrong on both fields.
        $log = $this->makeCashOutWithoutObserver(450, 'Combined fix');
        $expense = Expense::create([
            'reference_number' => Expense::generateReferenceNumber(),
            'expense_category_id' => $this->cashOutCategory->id,
            'store_id' => $this->store->id,
            'payee' => 'POS Cash Out',
            'amount' => 450,
            'expense_date' => now()->toDateString(),
            'description' => 'POS cash-out #'.$log->id.' — Combined fix',
            'receipt_number' => 'POS-CASHOUT-'.$log->id,
            'status' => Expense::STATUS_ACTIVE,
            'created_by' => $this->owner->id,
        ]);

        $this->artisan('cashouts:backfill-expense-payee', [
            '--target-user-id' => $this->owner->id,
        ])->assertExitCode(0);

        $fresh = $expense->fresh();
        $this->assertSame('POS Cash Out — Combined fix', $fresh->payee);
        $this->assertSame($this->cashier->id, (int) $fresh->created_by);
    }

    public function test_leaves_rows_already_in_sync_alone(): void
    {
        // Already-correct row: payee has the reason, created_by is the cashier.
        $log = $this->makeCashOutWithoutObserver(300, 'Already correct');
        $expense = Expense::create([
            'reference_number' => Expense::generateReferenceNumber(),
            'expense_category_id' => $this->cashOutCategory->id,
            'store_id' => $this->store->id,
            'payee' => 'POS Cash Out — Already correct',
            'amount' => 300,
            'expense_date' => now()->toDateString(),
            'description' => 'POS cash-out #'.$log->id.' — Already correct',
            'receipt_number' => 'POS-CASHOUT-'.$log->id,
            'status' => Expense::STATUS_ACTIVE,
            'created_by' => $this->cashier->id,
        ]);

        $beforeUpdatedAt = $expense->updated_at;

        // Sleep a hair so any mistaken touch on updated_at would be detectable.
        sleep(1);

        $this->artisan('cashouts:backfill-expense-payee', [
            '--target-user-id' => $this->owner->id,
        ])->assertExitCode(0);

        $fresh = $expense->fresh();
        $this->assertSame('POS Cash Out — Already correct', $fresh->payee);
        $this->assertSame($this->cashier->id, (int) $fresh->created_by);
        $this->assertEquals($beforeUpdatedAt, $fresh->updated_at, 'Already-in-sync rows must not be touched.');
    }

    /**
     * Create a PosLog without firing the PosLogObserver. The observer
     * already writes the new payee format, which would defeat the
     * purpose of the backfill test.
     */
    private function makeCashOutWithoutObserver(float $amount, ?string $reason): PosLog
    {
        return PosLog::withoutEvents(function () use ($amount, $reason) {
            return PosLog::create([
                'cash_out' => $amount,
                'type' => 12,
                'reason' => $reason,
                'pos_id' => 1,
                'store_id' => $this->store->id,
                'user_id' => $this->cashier->id,
            ]);
        });
    }
}
