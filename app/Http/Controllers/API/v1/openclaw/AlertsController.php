<?php

namespace App\Http\Controllers\API\v1\openclaw;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Accounting\Bank;
use App\Models\CustomerRelations\Customer;
use App\Models\CustomerRelations\CustomerCreditTransaction;
use App\Models\InventoryManagement\Purchase;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Bundled "things the bot probably wants to nag about" feed.
 *
 * Three feeds, returned together so the bot only has to poll one endpoint:
 *   - banks below their low_balance_threshold (global, mirrors mobile/admin)
 *   - purchase approvals stuck in pending past N days (tenant-scoped)
 *   - customers with positive credit_balance whose earliest open charge
 *     is past due_date (tenant-scoped via Customer.user_id)
 *
 * Query parameters:
 *   - approval_age_days: int, default 3
 */
class AlertsController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'approval_age_days' => 'nullable|integer|min:0|max:365',
        ]);

        $tenantUserId = (int) auth()->user()->user_id;
        $approvalAgeDays = (int) $request->input('approval_age_days', 3);
        $tz = config('app.timezone');
        $today = Carbon::today($tz);

        return $this->success([
            'as_of' => Carbon::now($tz)->toIso8601String(),
            'banks_below_threshold' => $this->banksBelowThreshold(),
            'pending_approvals' => $this->pendingApprovalsAging($tenantUserId, $approvalAgeDays, $today),
            'overdue_credit' => $this->overdueCredit($tenantUserId, $today),
        ]);
    }

    /**
     * Banks below their configured low_balance_threshold. Banking is global
     * in this codebase (no user_id on banks), matching the existing
     * /openclaw/banks/balances behavior.
     *
     * @return array<int, array<string, mixed>>
     */
    private function banksBelowThreshold(): array
    {
        return Bank::query()
            ->whereNotNull('low_balance_threshold')
            ->whereColumn('balance', '<=', 'low_balance_threshold')
            ->orderBy('balance')
            ->get(['id', 'bank_name', 'account_name', 'balance', 'low_balance_threshold'])
            ->map(fn (Bank $b) => [
                'bank_id' => (int) $b->id,
                'bank_name' => $b->bank_name,
                'account_name' => $b->account_name,
                'balance' => round((float) $b->balance, 2),
                'low_balance_threshold' => round((float) $b->low_balance_threshold, 2),
                'shortfall' => round((float) $b->low_balance_threshold - (float) $b->balance, 2),
            ])
            ->values()
            ->all();
    }

    /**
     * Purchase orders that have been sitting in approval_status=pending for
     * at least N days. Tenant-scoped via Purchase.user_id.
     *
     * @return array<int, array<string, mixed>>
     */
    private function pendingApprovalsAging(int $tenantUserId, int $approvalAgeDays, Carbon $today): array
    {
        $threshold = (clone $today)->subDays($approvalAgeDays);

        return Purchase::query()
            ->with(['supplier:id,name', 'store:id,name', 'creator:id,name'])
            ->where('user_id', $tenantUserId)
            ->where('approval_status', Purchase::APPROVAL_PENDING)
            ->where('created_at', '<=', $threshold)
            ->orderBy('created_at')
            ->get()
            ->map(fn (Purchase $p) => [
                'purchase_id' => (int) $p->id,
                'po' => $p->po,
                'supplier' => $p->supplier?->name,
                'store' => $p->store?->name,
                'created_by' => $p->creator?->name,
                'total' => round((float) ($p->total ?? 0), 2),
                'created_at' => $p->created_at?->toIso8601String(),
                'days_pending' => (int) $p->created_at?->diffInDays($today),
            ])
            ->values()
            ->all();
    }

    /**
     * Customers with credit_balance > 0 whose earliest credit transaction
     * with a due_date is before today. Tenant-scoped via Customer.user_id.
     *
     * @return array<int, array<string, mixed>>
     */
    private function overdueCredit(int $tenantUserId, Carbon $today): array
    {
        $oldestDueByCustomer = CustomerCreditTransaction::query()
            ->whereNotNull('due_date')
            ->selectRaw('customer_id, MIN(due_date) as earliest_due_date')
            ->groupBy('customer_id');

        return Customer::query()
            ->where('user_id', $tenantUserId)
            ->where('credit_balance', '>', 0)
            ->joinSub($oldestDueByCustomer, 'd', fn ($join) => $join->on('customers.id', '=', 'd.customer_id'))
            ->whereDate('d.earliest_due_date', '<', $today->toDateString())
            ->orderBy('d.earliest_due_date')
            ->get([
                'customers.id',
                'customers.name',
                'customers.code',
                'customers.credit_balance',
                'customers.credit_limit',
                'customers.credit_term_days',
                'd.earliest_due_date',
            ])
            ->map(function (Customer $c) use ($today) {
                $earliestDueDate = $c->getAttribute('earliest_due_date');
                $due = $earliestDueDate ? Carbon::parse($earliestDueDate) : null;

                return [
                    'customer_id' => (int) $c->id,
                    'name' => $c->name,
                    'code' => $c->code,
                    'credit_balance' => round((float) $c->credit_balance, 2),
                    'credit_limit' => round((float) $c->credit_limit, 2),
                    'credit_term_days' => (int) $c->credit_term_days,
                    'earliest_due_date' => $due?->toDateString(),
                    'days_overdue' => $due ? (int) $due->diffInDays($today) : null,
                ];
            })
            ->values()
            ->all();
    }
}
