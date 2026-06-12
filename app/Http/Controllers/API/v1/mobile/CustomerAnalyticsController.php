<?php

namespace App\Http\Controllers\API\v1\mobile;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\CustomerRelations\Customer;
use App\Models\CustomerRelations\CustomerPointsHistory;
use App\Models\Pos\Sale;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerAnalyticsController extends Controller
{
    use ApiResponse;

    /**
     * Get top customers by sales volume.
     */
    public function topCustomers(Request $request): JsonResponse
    {
        $limit = min($request->input('limit', 10), 50);
        [$startDate, $endDate] = $this->getDateRange($request);

        $query = Customer::query()
            ->join('sales', 'customers.id', '=', 'sales.customer_id')
            ->leftJoin('sale_lines', 'sales.id', '=', 'sale_lines.sales_id')
            ->where('sales.cancelled', false)
            ->where('sales.type', 0)
            ->whereBetween('sales.created_at', [$startDate, $endDate]);

        if ($request->filled('store_id')) {
            $query->where('sales.store_id', $request->input('store_id'));
        }

        $topCustomers = $query
            ->select([
                'customers.id',
                'customers.name',
                'customers.code',
                'customers.phone',
                'customers.email',
                'customers.image',
                'customers.points as loyalty_points_balance',
                DB::raw('COUNT(DISTINCT sales.id) as total_transactions'),
                DB::raw('SUM(sales.total) as total_spent'),
                DB::raw('SUM(sale_lines.qty * sale_lines.unit_qty) as total_items_purchased'),
                DB::raw('AVG(sales.total) as average_transaction'),
                DB::raw('MAX(sales.created_at) as last_purchase_date'),
            ])
            ->groupBy(
                'customers.id',
                'customers.name',
                'customers.code',
                'customers.phone',
                'customers.email',
                'customers.image',
                'customers.points'
            )
            ->orderByDesc('total_spent')
            ->limit($limit)
            ->get()
            ->map(function ($customer, $index) {
                return [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'code' => $customer->code,
                    'phone' => $customer->phone,
                    'email' => $customer->email,
                    'image' => $customer->image,
                    'total_transactions' => (int) $customer->total_transactions,
                    'total_spent' => round((float) $customer->total_spent, 2),
                    'total_items_purchased' => (int) ($customer->total_items_purchased ?? 0),
                    'average_transaction' => round((float) $customer->average_transaction, 2),
                    'last_purchase_date' => $customer->last_purchase_date,
                    'loyalty_points_balance' => (float) $customer->loyalty_points_balance,
                    'rank' => $index + 1,
                ];
            });

        $summary = $this->getCustomerSummary($startDate, $endDate, $request->input('store_id'));

        return $this->success([
            'top_customers' => $topCustomers,
            'summary' => $summary,
        ]);
    }

    /**
     * Get customer activity trends.
     */
    public function trends(Request $request): JsonResponse
    {
        $period = $request->input('period', 'daily');
        [$startDate, $endDate] = $this->getDateRange($request);

        $dateFormat = match ($period) {
            'weekly' => '%Y-%u',
            'monthly' => '%Y-%m',
            default => '%Y-%m-%d',
        };

        $labelFormat = match ($period) {
            'weekly' => 'Week %u, %Y',
            'monthly' => '%b %Y',
            default => '%Y-%m-%d',
        };

        $query = Sale::query()
            ->where('cancelled', false)
            ->where('type', 0)
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($request->filled('store_id')) {
            $query->where('store_id', $request->input('store_id'));
        }

        $trends = $query
            ->select([
                DB::raw("DATE_FORMAT(created_at, '{$dateFormat}') as period_key"),
                DB::raw("DATE_FORMAT(created_at, '{$labelFormat}') as period"),
                DB::raw('COUNT(DISTINCT CASE WHEN customer_id IS NOT NULL THEN customer_id END) as total_active_customers'),
                DB::raw('COUNT(id) as total_transactions'),
                DB::raw('SUM(total) as total_revenue'),
                DB::raw('SUM(acquired_points) as points_earned'),
                DB::raw('SUM(points_used) as points_redeemed'),
            ])
            ->groupBy('period_key', 'period')
            ->orderBy('period_key')
            ->get();

        $newVsReturning = $this->calculateNewVsReturning($startDate, $endDate, $period, $request->input('store_id'));

        $trendsWithCustomerBreakdown = $trends->map(function ($trend) use ($newVsReturning) {
            $periodData = $newVsReturning[$trend->period_key] ?? ['new' => 0, 'returning' => 0];

            return [
                'period' => $trend->period,
                'new_customers' => $periodData['new'],
                'returning_customers' => $periodData['returning'],
                'total_active_customers' => (int) $trend->total_active_customers,
                'total_transactions' => (int) $trend->total_transactions,
                'total_revenue' => round((float) $trend->total_revenue, 2),
                'points_earned' => round((float) ($trend->points_earned ?? 0), 2),
                'points_redeemed' => round((float) ($trend->points_redeemed ?? 0), 2),
            ];
        });

        $summary = $this->getTrendsSummary($startDate, $endDate, $trendsWithCustomerBreakdown, $request->input('store_id'));

        return $this->success([
            'trends' => $trendsWithCustomerBreakdown,
            'summary' => $summary,
        ]);
    }

    /**
     * Get points redemption history.
     */
    public function pointsHistory(Request $request): JsonResponse
    {
        $limit = min($request->input('limit', 20), 100);
        $offset = $request->input('offset', 0);
        [$startDate, $endDate] = $this->getDateRange($request);

        $query = CustomerPointsHistory::query()
            ->with(['customer:id,name,code', 'store:id,name'])
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->input('customer_id'));
        }

        $total = $query->count();

        $history = $query
            ->orderByDesc('created_at')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->map(function ($record) {
                return [
                    'id' => $record->id,
                    'customer' => $record->customer ? [
                        'id' => $record->customer->id,
                        'name' => $record->customer->name,
                        'code' => $record->customer->code,
                    ] : null,
                    'type' => $record->type,
                    'points' => (float) $record->points,
                    'balance_after' => (float) $record->balance_after,
                    'reference_type' => $record->reference_type,
                    'reference_id' => $record->reference_id,
                    'reference_number' => $record->reference_number,
                    'description' => $record->description,
                    'store' => $record->store ? [
                        'id' => $record->store->id,
                        'name' => $record->store->name,
                    ] : null,
                    'created_at' => $record->created_at->toIso8601String(),
                ];
            });

        $summary = $this->getPointsHistorySummary($startDate, $endDate);

        return $this->success([
            'history' => $history,
            'summary' => $summary,
            'pagination' => [
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => ($offset + $limit) < $total,
            ],
        ]);
    }

    /**
     * Get points summary for dashboard widget.
     */
    public function pointsSummary(Request $request): JsonResponse
    {
        [$startDate, $endDate] = $this->getDateRange($request);

        $totalPointsInCirculation = Customer::where('status', true)
            ->sum('points');

        $customersWithPoints = Customer::where('status', true)
            ->where('points', '>', 0)
            ->count();

        $averagePointsPerCustomer = $customersWithPoints > 0
            ? $totalPointsInCirculation / $customersWithPoints
            : 0;

        $periodStats = Sale::query()
            ->where('cancelled', false)
            ->where('type', 0)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->whereNotNull('customer_id')
            ->select([
                DB::raw('SUM(acquired_points) as points_earned'),
                DB::raw('SUM(points_used) as points_redeemed'),
            ])
            ->first();

        $pointsEarned = (float) ($periodStats->points_earned ?? 0);
        $pointsRedeemed = (float) ($periodStats->points_redeemed ?? 0);
        $redemptionRate = $pointsEarned > 0 ? ($pointsRedeemed / $pointsEarned) * 100 : 0;

        $topPointHolders = Customer::where('status', true)
            ->where('points', '>', 0)
            ->orderByDesc('points')
            ->limit(5)
            ->get(['id', 'name', 'points'])
            ->map(function ($customer) {
                return [
                    'customer_id' => $customer->id,
                    'customer_name' => $customer->name,
                    'points_balance' => (float) $customer->points,
                ];
            });

        return $this->success([
            'total_points_in_circulation' => round($totalPointsInCirculation, 2),
            'total_customers_with_points' => $customersWithPoints,
            'average_points_per_customer' => round($averagePointsPerCustomer, 2),
            'points_earned_this_period' => round($pointsEarned, 2),
            'points_redeemed_this_period' => round($pointsRedeemed, 2),
            'redemption_rate' => round($redemptionRate, 1),
            'top_point_holders' => $topPointHolders,
        ]);
    }

    /**
     * Get date range from request or default to current month.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function getDateRange(Request $request): array
    {
        $startDate = $request->filled('start_date')
            ? Carbon::parse($request->input('start_date'))->startOfDay()
            : Carbon::now()->startOfMonth();

        $endDate = $request->filled('end_date')
            ? Carbon::parse($request->input('end_date'))->endOfDay()
            : Carbon::now()->endOfDay();

        return [$startDate, $endDate];
    }

    /**
     * Get customer summary statistics.
     *
     * @return array{total_customers_with_purchases: int, total_revenue_from_customers: float, average_customer_spend: float}
     */
    private function getCustomerSummary(Carbon $startDate, Carbon $endDate, ?int $storeId): array
    {
        $query = Sale::query()
            ->where('cancelled', false)
            ->where('type', 0)
            ->whereNotNull('customer_id')
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        $stats = $query
            ->select([
                DB::raw('COUNT(DISTINCT customer_id) as total_customers'),
                DB::raw('SUM(total) as total_revenue'),
            ])
            ->first();

        $totalCustomers = (int) ($stats->total_customers ?? 0);
        $totalRevenue = (float) ($stats->total_revenue ?? 0);
        $averageSpend = $totalCustomers > 0 ? $totalRevenue / $totalCustomers : 0;

        return [
            'total_customers_with_purchases' => $totalCustomers,
            'total_revenue_from_customers' => round($totalRevenue, 2),
            'average_customer_spend' => round($averageSpend, 2),
        ];
    }

    /**
     * Calculate new vs returning customers per period.
     *
     * @return array<string, array{new: int, returning: int}>
     */
    private function calculateNewVsReturning(Carbon $startDate, Carbon $endDate, string $period, ?int $storeId): array
    {
        $dateFormat = match ($period) {
            'weekly' => '%Y-%u',
            'monthly' => '%Y-%m',
            default => '%Y-%m-%d',
        };

        $query = Sale::query()
            ->where('cancelled', false)
            ->where('type', 0)
            ->whereNotNull('customer_id')
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        $customerFirstPurchases = Sale::query()
            ->where('cancelled', false)
            ->where('type', 0)
            ->whereNotNull('customer_id')
            ->groupBy('customer_id')
            ->pluck(DB::raw('MIN(created_at)'), 'customer_id');

        $periodCustomers = $query
            ->select([
                'customer_id',
                DB::raw("DATE_FORMAT(created_at, '{$dateFormat}') as period_key"),
            ])
            ->groupBy('customer_id', 'period_key')
            ->get();

        $result = [];
        foreach ($periodCustomers as $row) {
            $periodKey = $row->period_key;
            if (! isset($result[$periodKey])) {
                $result[$periodKey] = ['new' => 0, 'returning' => 0];
            }

            $firstPurchase = $customerFirstPurchases[$row->customer_id] ?? null;
            if ($firstPurchase) {
                $firstPeriodKey = Carbon::parse($firstPurchase)->format(
                    match ($period) {
                        'weekly' => 'Y-W',
                        'monthly' => 'Y-m',
                        default => 'Y-m-d',
                    }
                );

                if ($firstPeriodKey === $periodKey || Carbon::parse($firstPurchase)->between($startDate, $endDate)) {
                    $result[$periodKey]['new']++;
                } else {
                    $result[$periodKey]['returning']++;
                }
            }
        }

        return $result;
    }

    /**
     * Get trends summary.
     *
     * @return array<string, mixed>
     */
    private function getTrendsSummary(Carbon $startDate, Carbon $endDate, $trends, ?int $storeId): array
    {
        $totalNew = $trends->sum('new_customers');
        $totalReturning = $trends->sum('returning_customers');
        $totalActive = $trends->sum('total_active_customers');
        $daysCount = max($trends->count(), 1);

        $previousPeriodStart = $startDate->copy()->subDays($startDate->diffInDays($endDate) + 1);
        $previousPeriodEnd = $startDate->copy()->subDay();

        $query = Sale::query()
            ->where('cancelled', false)
            ->where('type', 0)
            ->whereNotNull('customer_id')
            ->whereBetween('created_at', [$previousPeriodStart, $previousPeriodEnd]);

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        $previousCustomers = $query->distinct('customer_id')->count('customer_id');
        $retentionRate = $previousCustomers > 0 ? ($totalReturning / $previousCustomers) * 100 : 0;

        return [
            'period_start' => $startDate->toDateString(),
            'period_end' => $endDate->toDateString(),
            'total_new_customers' => $totalNew,
            'total_returning_customers' => $totalReturning,
            'average_daily_active' => round($totalActive / $daysCount, 0),
            'customer_retention_rate' => round(min($retentionRate, 100), 1),
        ];
    }

    /**
     * Get points history summary.
     *
     * @return array<string, float>
     */
    private function getPointsHistorySummary(Carbon $startDate, Carbon $endDate): array
    {
        $stats = CustomerPointsHistory::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select([
                DB::raw("SUM(CASE WHEN type = 'earned' THEN points ELSE 0 END) as total_earned"),
                DB::raw("SUM(CASE WHEN type = 'redeemed' THEN ABS(points) ELSE 0 END) as total_redeemed"),
                DB::raw("SUM(CASE WHEN type = 'expired' THEN ABS(points) ELSE 0 END) as total_expired"),
                DB::raw("SUM(CASE WHEN type = 'adjusted' THEN points ELSE 0 END) as total_adjusted"),
            ])
            ->first();

        $totalEarned = (float) ($stats->total_earned ?? 0);
        $totalRedeemed = (float) ($stats->total_redeemed ?? 0);
        $totalExpired = (float) ($stats->total_expired ?? 0);
        $totalAdjusted = (float) ($stats->total_adjusted ?? 0);

        return [
            'total_points_earned' => round($totalEarned, 2),
            'total_points_redeemed' => round($totalRedeemed, 2),
            'total_points_expired' => round($totalExpired, 2),
            'total_points_adjusted' => round($totalAdjusted, 2),
            'net_points_issued' => round($totalEarned - $totalRedeemed - $totalExpired + $totalAdjusted, 2),
        ];
    }

    /**
     * Get customers with outstanding credit balances.
     * GET /api/v1/mobile/customers/analytics/outstanding-credits
     */
    public function outstandingCredits(Request $request): JsonResponse
    {
        $limit = $request->input('limit', 10);

        $customers = Customer::where('credit_balance', '>', 0)
            ->where('status', true)
            ->orderByDesc('credit_balance')
            ->take((int) $limit)
            ->get(['id', 'name', 'phone', 'credit_balance', 'credit_limit']);

        $totalOutstanding = Customer::where('credit_balance', '>', 0)
            ->where('status', true)
            ->sum('credit_balance');

        $count = Customer::where('credit_balance', '>', 0)
            ->where('status', true)
            ->count();

        return $this->success([
            'customers' => $customers,
            'total_outstanding' => round((float) $totalOutstanding, 2),
            'count' => $count,
        ]);
    }
}
