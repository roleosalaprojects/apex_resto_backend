<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-off repair for tenancy anomalies left over from when this codebase
 * was forked from a multi-tenant project. Three known patterns:
 *
 *   1. customers.user_id = 0 (orphaned, never assigned an owner)
 *   2. categories.user_id = 0 (same)
 *   3. purchases.user_id = <employee id> instead of <tenant id> — caused
 *      by a bug that wrote auth()->id() into the tenant column when
 *      employees created POs.
 *
 * The command is idempotent: after the first successful run, subsequent
 * runs find zero rows to update and exit cleanly.
 */
class OpenclawRepairOrphanTenancy extends Command
{
    protected $signature = 'openclaw:repair-orphan-tenancy
        {--target-user-id= : Tenant user_id to assign orphans to. Auto-detected if omitted.}
        {--dry-run : Report what would change without writing.}';

    protected $description = 'Repair orphaned tenancy data (user_id=0 rows, employee-id-as-tenant on purchases).';

    public function handle(): int
    {
        $target = $this->resolveTargetTenant();
        if ($target === null) {
            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        $this->line('Target tenant user_id: <info>'.$target.'</info>');
        $this->line($dryRun ? 'Mode: <comment>DRY RUN</comment> (no writes will be performed)' : 'Mode: <info>APPLY</info>');
        $this->newLine();

        $plan = $this->buildPlan($target);

        $this->table(
            ['Issue', 'Table', 'Rows affected'],
            collect($plan)->map(fn ($p) => [$p['issue'], $p['table'], $p['count']])->all()
        );

        $totalAffected = collect($plan)->sum('count');

        if ($totalAffected === 0) {
            $this->info('Nothing to repair. The schema is already consistent.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->newLine();
            $this->line("Would update <comment>{$totalAffected}</comment> row(s). Re-run without --dry-run to apply.");

            return self::SUCCESS;
        }

        $applied = DB::transaction(function () use ($plan): array {
            $applied = [];
            foreach ($plan as $p) {
                $applied[$p['key']] = DB::update($p['update_sql'], $p['update_bindings']);
            }

            return $applied;
        });

        $this->newLine();
        $this->info('Repair applied:');
        foreach ($applied as $key => $count) {
            $this->line("  - {$key}: <info>{$count}</info> row(s) updated");
        }

        $this->newLine();
        $this->info('Done.');

        return self::SUCCESS;
    }

    /**
     * Resolve the tenant id rows should belong to.
     * Auto-detection rule: the canonical owner is a user where id == user_id.
     * If --target-user-id is provided, it is trusted as-is.
     */
    private function resolveTargetTenant(): ?int
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
            $this->line('Pass --target-user-id=<id> explicitly to choose one.');

            return null;
        }

        return (int) $owners[0]->id;
    }

    /**
     * @return array<int, array{key: string, issue: string, table: string, count: int, update_sql: string, update_bindings: array<int, mixed>}>
     */
    private function buildPlan(int $target): array
    {
        $plans = [];

        // 1. customers with user_id = 0
        $plans[] = $this->countableUpdate(
            'orphan_customers',
            'customers with user_id=0',
            'customers',
            'SELECT COUNT(*) as cnt FROM customers WHERE user_id = 0',
            [],
            'UPDATE customers SET user_id = ? WHERE user_id = 0',
            [$target],
        );

        // 2. categories with user_id = 0
        $plans[] = $this->countableUpdate(
            'orphan_categories',
            'categories with user_id=0',
            'categories',
            'SELECT COUNT(*) as cnt FROM categories WHERE user_id = 0',
            [],
            'UPDATE categories SET user_id = ? WHERE user_id = 0',
            [$target],
        );

        // 3. purchases.user_id == an employee id (a users.id whose users.user_id = target)
        //    instead of the tenant id. Catches the auth()->id() vs auth()->user()->user_id
        //    confusion bug exactly.
        $plans[] = $this->countableUpdate(
            'employee_id_as_tenant_on_purchases',
            'purchases.user_id is an employee id, not the tenant',
            'purchases',
            'SELECT COUNT(*) as cnt FROM purchases p
                WHERE p.user_id != ?
                  AND p.user_id IN (SELECT u.id FROM users u WHERE u.user_id = ? AND u.id != u.user_id)',
            [$target, $target],
            'UPDATE purchases SET user_id = ?
                WHERE user_id != ?
                  AND user_id IN (SELECT u.id FROM (SELECT id, user_id FROM users) u WHERE u.user_id = ? AND u.id != u.user_id)',
            [$target, $target, $target],
        );

        return $plans;
    }

    /**
     * @param  array<int, mixed>  $countBindings
     * @param  array<int, mixed>  $updateBindings
     * @return array{key: string, issue: string, table: string, count: int, update_sql: string, update_bindings: array<int, mixed>}
     */
    private function countableUpdate(
        string $key,
        string $issue,
        string $table,
        string $countSql,
        array $countBindings,
        string $updateSql,
        array $updateBindings,
    ): array {
        $count = (int) (DB::selectOne($countSql, $countBindings)->cnt ?? 0);

        return [
            'key' => $key,
            'issue' => $issue,
            'table' => $table,
            'count' => $count,
            'update_sql' => $updateSql,
            'update_bindings' => $updateBindings,
        ];
    }
}
