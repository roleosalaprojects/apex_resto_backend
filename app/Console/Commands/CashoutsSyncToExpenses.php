<?php

namespace App\Console\Commands;

use App\Models\Accounting\Expense;
use App\Models\Accounting\ExpenseCategory;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Sync POS cash-outs into the expenses table as cashless (no-bank)
 * accounting entries. Each cash-out becomes one expense with
 * receipt_number = "POS-CASHOUT-<id>", which doubles as the idempotency
 * key — re-running the command never duplicates rows.
 *
 * Cash-outs already left the till at the POS, so we explicitly do NOT
 * link a bank or create a bank withdrawal; that would double-count.
 */
class CashoutsSyncToExpenses extends Command
{
    protected $signature = 'cashouts:sync-to-expenses
        {--all : Sync every cash-out ever recorded. Overrides --date-from / --date-to.}
        {--date-from= : Start of window (YYYY-MM-DD). Defaults to 30 days ago.}
        {--date-to= : End of window (YYYY-MM-DD). Defaults to today.}
        {--category= : Match an expense category by name (case-insensitive).}
        {--category-id= : Use this expense_category_id instead of the name match.}
        {--target-user-id= : Tenant user_id. Auto-detected if omitted.}
        {--dry-run : Report what would be created without writing.}';

    protected $description = 'Mirror POS cash-outs into the expenses table as cashless entries (idempotent).';

    private const TYPE_CASH_OUT = 12;

    private const TYPE_VOID_CASH_OUT = 13;

    public function handle(): int
    {
        $tenantUserId = $this->resolveTenant();
        if ($tenantUserId === null) {
            return self::FAILURE;
        }

        $tz = config('app.timezone');
        $allMode = (bool) $this->option('all');

        if ($allMode) {
            $from = null;
            $to = null;
        } else {
            $to = $this->option('date-to') !== null
                ? Carbon::parse($this->option('date-to'), $tz)->endOfDay()
                : Carbon::today($tz)->endOfDay();
            $from = $this->option('date-from') !== null
                ? Carbon::parse($this->option('date-from'), $tz)->startOfDay()
                : (clone $to)->subDays(29)->startOfDay();
        }

        $categoryId = $this->resolveCategory();
        $dryRun = (bool) $this->option('dry-run');

        $this->line('Tenant: <info>'.$tenantUserId.'</info>');
        $this->line('Window: '.($allMode
            ? '<info>ALL TIME</info>'
            : '<info>'.$from->toDateString().'</info> → <info>'.$to->toDateString().'</info>'));
        $this->line('Expense category: '.($categoryId !== null ? "id={$categoryId}" : '<comment>unset (Uncategorized)</comment>'));
        $this->line('Mode: '.($dryRun ? '<comment>DRY RUN</comment>' : '<info>APPLY</info>'));
        $this->newLine();

        // Voided cash-out ids — pulled once, scoped per tenant. The window
        // matches the cash-out window so we don't accidentally treat a row
        // as voided by a void that's outside our scope.
        $voidedIds = DB::table('pos_logs')
            ->join('users', 'pos_logs.user_id', '=', 'users.id')
            ->where('users.user_id', $tenantUserId)
            ->where('pos_logs.type', self::TYPE_VOID_CASH_OUT)
            ->whereNotNull('pos_logs.so_id')
            ->pluck('pos_logs.so_id');

        $cashOuts = DB::table('pos_logs')
            ->join('users', 'pos_logs.user_id', '=', 'users.id')
            ->leftJoin('stores', 'pos_logs.store_id', '=', 'stores.id')
            ->where('users.user_id', $tenantUserId)
            ->where('pos_logs.type', self::TYPE_CASH_OUT)
            ->whereNotIn('pos_logs.id', $voidedIds)
            ->when(! $allMode, fn ($q) => $q->whereBetween('pos_logs.created_at', [$from, $to]))
            ->orderBy('pos_logs.created_at')
            ->select([
                'pos_logs.id',
                'pos_logs.cash_out',
                'pos_logs.reason',
                'pos_logs.store_id',
                'pos_logs.pos_id',
                'pos_logs.user_id as cashier_id',
                'pos_logs.created_at',
                'users.name as employee_name',
                'stores.name as store_name',
            ])
            ->get();

        if ($cashOuts->isEmpty()) {
            $this->info($allMode
                ? 'No cash-outs found. Nothing to do.'
                : 'No cash-outs in window. Nothing to do.');

            return self::SUCCESS;
        }

        // Find existing expenses already synced for these cash-outs.
        $references = $cashOuts->map(fn ($r) => 'POS-CASHOUT-'.$r->id)->all();
        $alreadySynced = Expense::query()
            ->whereIn('receipt_number', $references)
            ->pluck('receipt_number')
            ->all();
        $alreadySyncedSet = array_flip($alreadySynced);

        $createdCount = 0;
        $skippedCount = 0;
        $createdAmount = 0.0;
        $rowsForTable = [];

        foreach ($cashOuts as $row) {
            $ref = 'POS-CASHOUT-'.$row->id;

            if (isset($alreadySyncedSet[$ref])) {
                $skippedCount++;

                continue;
            }

            $expenseDate = $row->created_at
                ? Carbon::parse($row->created_at)->setTimezone($tz)->toDateString()
                : Carbon::today($tz)->toDateString();
            $hasReason = $row->reason !== null && trim((string) $row->reason) !== '';
            $description = sprintf(
                'POS cash-out #%d%s%s',
                $row->id,
                $hasReason ? ' — '.$row->reason : '',
                $row->store_name ? ' ('.$row->store_name.')' : '',
            );
            $payee = $hasReason
                ? 'POS Cash Out — '.trim((string) $row->reason)
                : 'POS Cash Out';

            if (! $dryRun) {
                Expense::create([
                    'reference_number' => Expense::generateReferenceNumber(),
                    'expense_category_id' => $categoryId,
                    'store_id' => $row->store_id,
                    'supplier_id' => null,
                    'bank_id' => null,
                    'bank_transaction_id' => null,
                    'payee' => $payee,
                    'amount' => (float) $row->cash_out,
                    'expense_date' => $expenseDate,
                    'description' => $description,
                    'receipt_number' => $ref,
                    'status' => Expense::STATUS_ACTIVE,
                    // The cashier who triggered the cash-out is the one who
                    // "created" the expense — matches the PosLogObserver path
                    // and what the admin expense table shows in the "By" column.
                    'created_by' => (int) $row->cashier_id,
                ]);
            }

            $createdCount++;
            $createdAmount += (float) $row->cash_out;
            $rowsForTable[] = [
                'cashout_id' => $row->id,
                'date' => $expenseDate,
                'amount' => number_format((float) $row->cash_out, 2),
                'employee' => $row->employee_name ?? '—',
                'reason' => $row->reason ?? '—',
            ];
        }

        if ($rowsForTable !== []) {
            $this->table(['cashout_id', 'date', 'amount', 'employee', 'reason'], $rowsForTable);
        }

        $this->newLine();
        $this->info(($dryRun ? 'Would create: ' : 'Created: ').$createdCount.' expense(s) — total ₱'.number_format($createdAmount, 2));
        $this->line('Skipped (already synced): <info>'.$skippedCount.'</info>');

        return self::SUCCESS;
    }

    private function resolveTenant(): ?int
    {
        if ($this->option('target-user-id') !== null) {
            return (int) $this->option('target-user-id');
        }

        $owners = DB::select('SELECT id FROM users WHERE id = user_id ORDER BY id');

        if (count($owners) === 0) {
            $this->error('Could not auto-detect target tenant: no user has id == user_id.');
            $this->line('Pass --target-user-id=<id> explicitly.');

            return null;
        }

        if (count($owners) > 1) {
            $ids = collect($owners)->pluck('id')->implode(', ');
            $this->error("Multiple tenant owners detected (users.id == users.user_id): {$ids}.");
            $this->line('Pass --target-user-id=<id> to choose one.');

            return null;
        }

        return (int) $owners[0]->id;
    }

    private function resolveCategory(): ?int
    {
        if ($this->option('category-id') !== null) {
            return (int) $this->option('category-id');
        }

        $name = $this->option('category');
        if ($name === null || trim($name) === '') {
            return null;
        }

        $category = ExpenseCategory::query()
            ->whereRaw('LOWER(name) = ?', [strtolower(trim($name))])
            ->first();

        if ($category === null) {
            $available = ExpenseCategory::query()->where('status', 1)->pluck('name')->implode(', ');
            $this->warn("Unknown expense category '{$name}'. Available: {$available}");
            $this->warn('Continuing with no category (Uncategorized).');

            return null;
        }

        return (int) $category->id;
    }
}
