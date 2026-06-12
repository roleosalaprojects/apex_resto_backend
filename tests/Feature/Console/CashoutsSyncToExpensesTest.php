<?php

namespace Tests\Feature\Console;

use App\Models\Accounting\Expense;
use App\Models\Accounting\ExpenseCategory;
use App\Models\Accounting\PosLog;
use App\Models\Employees\Role;
use App\Models\Settings\Store;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashoutsSyncToExpensesTest extends TestCase
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

    private function makeCashOut(float $amount, string $reason, ?Carbon $when = null, int $type = 12, ?int $soId = null): PosLog
    {
        $when = $when ?? Carbon::now();

        // These tests target the artisan command in isolation, so we
        // suppress the PosLogObserver here. Observer behaviour is covered
        // separately by tests/Feature/Observers/PosLogObserverTest.php.
        return PosLog::withoutEvents(function () use ($amount, $type, $reason, $soId, $when) {
            $log = PosLog::create([
                'cash_out' => $amount,
                'type' => $type,
                'reason' => $reason,
                'so_id' => $soId,
                'pos_id' => 1,
                'store_id' => $this->store->id,
                'user_id' => $this->cashier->id,
            ]);
            $log->forceFill(['created_at' => $when, 'updated_at' => $when])->save();

            return $log;
        });
    }

    public function test_creates_one_expense_per_cash_out_in_window(): void
    {
        $today = Carbon::today(config('app.timezone'))->setTime(12, 0);
        $a = $this->makeCashOut(500, 'Petty supplies', $today);
        $b = $this->makeCashOut(1200, 'Lunch run', $today);

        $this->artisan('cashouts:sync-to-expenses', ['--category' => 'Cash Out'])->assertExitCode(0);

        $this->assertSame(2, Expense::count());

        $expenseA = Expense::where('receipt_number', 'POS-CASHOUT-'.$a->id)->first();
        $this->assertNotNull($expenseA);
        $this->assertEqualsWithDelta(500.0, (float) $expenseA->amount, 0.001);
        $this->assertSame($this->cashOutCategory->id, $expenseA->expense_category_id);
        $this->assertNull($expenseA->bank_id, 'cashless: no bank linkage');
        $this->assertSame($this->store->id, $expenseA->store_id);
        $this->assertStringContainsString('Petty supplies', $expenseA->description);
        $this->assertSame(
            $this->cashier->id,
            (int) $expenseA->created_by,
            'created_by must be the cashier who triggered the cash-out, not the tenant owner.',
        );

        $this->assertNotNull(Expense::where('receipt_number', 'POS-CASHOUT-'.$b->id)->first());
    }

    public function test_skips_voided_cash_outs(): void
    {
        $today = Carbon::today(config('app.timezone'))->setTime(12, 0);
        $active = $this->makeCashOut(500, 'Active', $today);
        $voided = $this->makeCashOut(750, 'About to be voided', $today);
        $this->makeCashOut($voided->cash_out, 'Voided', $today, type: 13, soId: $voided->id);

        $this->artisan('cashouts:sync-to-expenses', ['--category' => 'Cash Out'])->assertExitCode(0);

        $this->assertSame(1, Expense::count());
        $this->assertNotNull(Expense::where('receipt_number', 'POS-CASHOUT-'.$active->id)->first());
        $this->assertNull(Expense::where('receipt_number', 'POS-CASHOUT-'.$voided->id)->first());
    }

    public function test_is_idempotent_on_re_run(): void
    {
        $today = Carbon::today(config('app.timezone'))->setTime(12, 0);
        $this->makeCashOut(500, 'A', $today);
        $this->makeCashOut(700, 'B', $today);

        $this->artisan('cashouts:sync-to-expenses', ['--category' => 'Cash Out'])->assertExitCode(0);
        $this->assertSame(2, Expense::count());

        // Re-run: nothing new, nothing duplicated.
        $this->artisan('cashouts:sync-to-expenses', ['--category' => 'Cash Out'])->assertExitCode(0);
        $this->assertSame(2, Expense::count());
    }

    public function test_dry_run_does_not_write(): void
    {
        $today = Carbon::today(config('app.timezone'))->setTime(12, 0);
        $this->makeCashOut(500, 'A', $today);
        $this->makeCashOut(700, 'B', $today);

        $this->artisan('cashouts:sync-to-expenses', [
            '--category' => 'Cash Out',
            '--dry-run' => true,
        ])->assertExitCode(0);

        $this->assertSame(0, Expense::count());

        // Real run still creates them after dry run.
        $this->artisan('cashouts:sync-to-expenses', ['--category' => 'Cash Out'])->assertExitCode(0);
        $this->assertSame(2, Expense::count());
    }

    public function test_filters_by_date_window(): void
    {
        $today = Carbon::today(config('app.timezone'))->setTime(12, 0);
        $recent = $this->makeCashOut(100, 'Recent', $today);
        $this->makeCashOut(999, 'Old', $today->copy()->subDays(60));

        $this->artisan('cashouts:sync-to-expenses', ['--category' => 'Cash Out'])->assertExitCode(0);

        $this->assertSame(1, Expense::count());
        $this->assertNotNull(Expense::where('receipt_number', 'POS-CASHOUT-'.$recent->id)->first());
    }

    public function test_unknown_category_continues_with_uncategorized(): void
    {
        $today = Carbon::today(config('app.timezone'))->setTime(12, 0);
        $this->makeCashOut(500, 'A', $today);

        $this->artisan('cashouts:sync-to-expenses', ['--category' => 'Nonexistent'])
            ->expectsOutputToContain('Unknown expense category')
            ->assertExitCode(0);

        $this->assertSame(1, Expense::count());
        $this->assertNull(Expense::first()->expense_category_id);
    }

    public function test_explicit_target_user_id_overrides_auto_detect(): void
    {
        $today = Carbon::today(config('app.timezone'))->setTime(12, 0);
        $this->makeCashOut(500, 'A', $today);

        $this->artisan('cashouts:sync-to-expenses', [
            '--category' => 'Cash Out',
            '--target-user-id' => $this->owner->id,
        ])->assertExitCode(0);

        $this->assertSame(1, Expense::count());
    }

    public function test_all_flag_syncs_every_cash_out_regardless_of_age(): void
    {
        $today = Carbon::today(config('app.timezone'))->setTime(12, 0);
        $recent = $this->makeCashOut(100, 'Recent', $today);
        $ancient = $this->makeCashOut(999, 'Ancient', $today->copy()->subYears(2));

        $this->artisan('cashouts:sync-to-expenses', [
            '--all' => true,
            '--category' => 'Cash Out',
        ])->assertExitCode(0);

        $this->assertSame(2, Expense::count());
        $this->assertNotNull(Expense::where('receipt_number', 'POS-CASHOUT-'.$recent->id)->first());
        $this->assertNotNull(Expense::where('receipt_number', 'POS-CASHOUT-'.$ancient->id)->first());
    }

    public function test_all_flag_remains_idempotent(): void
    {
        $today = Carbon::today(config('app.timezone'))->setTime(12, 0);
        $this->makeCashOut(100, 'A', $today);
        $this->makeCashOut(200, 'B', $today->copy()->subYears(3));

        $this->artisan('cashouts:sync-to-expenses', ['--all' => true])->assertExitCode(0);
        $this->assertSame(2, Expense::count());

        // Re-run with --all: nothing duplicated.
        $this->artisan('cashouts:sync-to-expenses', ['--all' => true])->assertExitCode(0);
        $this->assertSame(2, Expense::count());
    }

    public function test_no_cash_outs_in_window_is_a_clean_no_op(): void
    {
        $this->artisan('cashouts:sync-to-expenses', ['--category' => 'Cash Out'])
            ->expectsOutputToContain('No cash-outs in window')
            ->assertExitCode(0);

        $this->assertSame(0, Expense::count());
    }
}
