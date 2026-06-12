<?php

namespace App\Console\Commands;

use App\Models\Accounting\Expense;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-shot data-consistency pass for synced POS cash-out expenses.
 *
 * Older versions of the sync logic wrote two fields that newer code
 * computes differently:
 *
 *   payee         was "POS Cash Out", now "POS Cash Out — <reason>"
 *                 (so the admin Expenses table actually shows what the
 *                 cash-out was for, since that table renders payee but
 *                 not description).
 *
 *   created_by    was the tenant owner (artisan sync path), now the
 *                 cashier (matching the PosLogObserver path and what
 *                 the "By" column in the admin Expenses table is meant
 *                 to show — the person who actually rang it up).
 *
 * This command walks every POS-CASHOUT-* expense, looks up the
 * matching pos_log, and updates whichever of those two fields are out
 * of sync with the current logic. Idempotent — re-running on already-
 * fixed rows is a no-op.
 *
 * Tenant-scoped via the pos_log → users.user_id join (the observer
 * writes created_by = cashier, the legacy artisan sync wrote
 * created_by = owner, so filtering by created_by on the expenses table
 * would miss whole classes of rows).
 */
class CashoutsBackfillExpensePayee extends Command
{
    protected $signature = 'cashouts:backfill-expense-payee
        {--target-user-id= : Tenant user_id (the owner\'s users.id). Auto-detected if a single tenant exists.}
        {--dry-run : Report what would change without writing.}';

    protected $description = 'Reconcile payee + created_by on existing POS-CASHOUT-* expenses so they match current sync logic.';

    private const LEGACY_PAYEE = 'POS Cash Out';

    public function handle(): int
    {
        $tenantUserId = $this->resolveTenant();
        if ($tenantUserId === null) {
            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        $this->line('Tenant: <info>'.$tenantUserId.'</info>');
        $this->line('Mode: '.($dryRun ? '<comment>DRY RUN</comment>' : '<info>APPLY</info>'));
        $this->newLine();

        // Every POS-CASHOUT-* expense is a candidate (no payee or
        // created_by filter — we want to consider rows that are partially
        // correct, e.g. payee already fixed by an earlier run but
        // created_by still wrong).
        $candidates = Expense::query()
            ->where('receipt_number', 'like', 'POS-CASHOUT-%')
            ->get(['id', 'receipt_number', 'payee', 'description', 'created_by']);

        if ($candidates->isEmpty()) {
            $this->info('No cash-out expenses found. Nothing to backfill.');

            return self::SUCCESS;
        }

        $logIds = $candidates
            ->map(fn ($e) => (int) str_replace('POS-CASHOUT-', '', $e->receipt_number))
            ->filter()
            ->all();

        // Tenant-scoped log lookup, joined for the cashier id + reason.
        $logs = DB::table('pos_logs')
            ->join('users', 'pos_logs.user_id', '=', 'users.id')
            ->whereIn('pos_logs.id', $logIds)
            ->where('users.user_id', $tenantUserId)
            ->select([
                'pos_logs.id',
                'pos_logs.reason',
                'pos_logs.user_id as cashier_id',
            ])
            ->get()
            ->keyBy('id');

        $payeeUpdates = 0;
        $createdByUpdates = 0;
        $touchedRows = 0;
        $skippedCount = 0;
        $rowsForTable = [];

        foreach ($candidates as $expense) {
            $logId = (int) str_replace('POS-CASHOUT-', '', $expense->receipt_number);
            $log = $logs->get($logId);

            if ($log === null) {
                // Either the pos_log no longer exists, or it belongs to
                // another tenant. Leave the expense alone.
                $skippedCount++;

                continue;
            }

            $changes = [];

            // payee: rewrite only if it's still the exact legacy string
            // AND the cash-out has a reason to surface. Rows with a
            // missing reason stay on "POS Cash Out".
            $reason = $log->reason !== null ? trim((string) $log->reason) : '';
            if ($expense->payee === self::LEGACY_PAYEE && $reason !== '') {
                $changes['payee'] = 'POS Cash Out — '.$reason;
            }

            // created_by: rewrite if it doesn't already point at the
            // cashier (the older artisan sync wrote tenant_owner.id).
            $cashierId = (int) $log->cashier_id;
            if ((int) $expense->created_by !== $cashierId) {
                $changes['created_by'] = $cashierId;
            }

            if ($changes === []) {
                $skippedCount++;

                continue;
            }

            if (! $dryRun) {
                $expense->update($changes);
            }

            if (array_key_exists('payee', $changes)) {
                $payeeUpdates++;
            }
            if (array_key_exists('created_by', $changes)) {
                $createdByUpdates++;
            }
            $touchedRows++;

            $rowsForTable[] = [
                'expense_id' => $expense->id,
                'cashout_id' => $logId,
                'payee' => $changes['payee'] ?? '—',
                'created_by' => array_key_exists('created_by', $changes) ? (string) $changes['created_by'] : '—',
            ];
        }

        if ($rowsForTable !== []) {
            $this->table(['expense_id', 'cashout_id', 'payee', 'created_by'], $rowsForTable);
        }

        $this->newLine();
        $verb = $dryRun ? 'Would touch' : 'Touched';
        $this->info("{$verb}: {$touchedRows} row(s)  (payee: {$payeeUpdates}, created_by: {$createdByUpdates})");
        $this->line('Skipped (already in sync, or pos_log missing): <info>'.$skippedCount.'</info>');

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
}
