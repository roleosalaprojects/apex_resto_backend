<?php

namespace App\Http\Controllers\API\v1\openclaw;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\CustomerRelations\Customer;
use App\Models\Pos\Sale;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    use ApiResponse;

    /**
     * Top customers by sales total over the date range.
     */
    public function top(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'limit' => 'nullable|integer|min:1|max:200',
        ]);

        $tenantUserId = (int) auth()->user()->user_id;
        $tz = config('app.timezone');
        $to = $request->filled('date_to')
            ? Carbon::parse($request->input('date_to'), $tz)->endOfDay()
            : Carbon::today($tz)->endOfDay();
        $from = $request->filled('date_from')
            ? Carbon::parse($request->input('date_from'), $tz)->startOfDay()
            : (clone $to)->subDays(29)->startOfDay();
        $limit = (int) $request->input('limit', 20);

        $rows = Sale::query()
            ->join('customers', 'customers.id', '=', 'sales.customer_id')
            ->where('sales.user_id', $tenantUserId)
            ->where('sales.cancelled', 0)
            ->where('sales.type', 0)
            ->whereBetween('sales.created_at', [$from, $to])
            ->whereNotNull('sales.customer_id')
            ->selectRaw('customers.id as customer_id, customers.name, customers.code, COUNT(*) as transactions, COALESCE(SUM(sales.total), 0) as total')
            ->groupBy('customers.id', 'customers.name', 'customers.code')
            ->orderByDesc('total')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => [
                'customer_id' => (int) $r->customer_id,
                'name' => $r->name,
                'code' => $r->code,
                'transactions' => (int) $r->transactions,
                'total' => round((float) $r->total, 2),
            ]);

        return $this->success([
            'date_from' => $from->toIso8601String(),
            'date_to' => $to->toIso8601String(),
            'customers' => $rows,
        ]);
    }

    /**
     * Customers with positive credit_balance (unpaid balance owed by them).
     */
    public function outstandingCredit(Request $request): JsonResponse
    {
        $request->validate([
            'limit' => 'nullable|integer|min:1|max:500',
        ]);

        $tenantUserId = (int) auth()->user()->user_id;
        $limit = (int) $request->input('limit', 100);

        $customers = Customer::query()
            ->where('user_id', $tenantUserId)
            ->where('credit_balance', '>', 0)
            ->orderByDesc('credit_balance')
            ->limit($limit)
            ->get(['id', 'name', 'code', 'credit_balance', 'credit_limit', 'credit_term_days'])
            ->map(fn (Customer $c) => [
                'customer_id' => $c->id,
                'name' => $c->name,
                'code' => $c->code,
                'credit_balance' => round((float) $c->credit_balance, 2),
                'credit_limit' => round((float) $c->credit_limit, 2),
                'credit_term_days' => (int) $c->credit_term_days,
            ]);

        $totals = Customer::query()
            ->where('user_id', $tenantUserId)
            ->selectRaw('COALESCE(SUM(credit_balance), 0) as outstanding, SUM(CASE WHEN credit_balance > 0 THEN 1 ELSE 0 END) as active_count')
            ->first();

        return $this->success([
            'summary' => [
                'outstanding_total' => round((float) ($totals->outstanding ?? 0), 2),
                'active_credit_count' => (int) ($totals->active_count ?? 0),
            ],
            'customers' => $customers,
        ]);
    }

    /**
     * Aggregate customer points balances + lifetime accumulated.
     */
    public function pointsSummary(Request $request): JsonResponse
    {
        $tenantUserId = (int) auth()->user()->user_id;

        $totals = Customer::query()
            ->where('user_id', $tenantUserId)
            ->selectRaw('COUNT(*) as customer_count, COALESCE(SUM(points), 0) as points_balance, COALESCE(SUM(accumulated_points), 0) as lifetime_points, SUM(CASE WHEN points > 0 THEN 1 ELSE 0 END) as customers_with_points')
            ->first();

        $top = Customer::query()
            ->where('user_id', $tenantUserId)
            ->where('points', '>', 0)
            ->orderByDesc('points')
            ->limit(20)
            ->get(['id', 'name', 'code', 'points', 'accumulated_points'])
            ->map(fn (Customer $c) => [
                'customer_id' => $c->id,
                'name' => $c->name,
                'code' => $c->code,
                'points' => round((float) $c->points, 2),
                'accumulated_points' => round((float) $c->accumulated_points, 2),
            ]);

        return $this->success([
            'summary' => [
                'customer_count' => (int) ($totals->customer_count ?? 0),
                'points_balance' => round((float) ($totals->points_balance ?? 0), 2),
                'lifetime_points' => round((float) ($totals->lifetime_points ?? 0), 2),
                'customers_with_points' => (int) ($totals->customers_with_points ?? 0),
            ],
            'top_holders' => $top,
        ]);
    }
}
