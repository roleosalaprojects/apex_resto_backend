<?php

namespace App\Http\Controllers\API\v1\mobile;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Pos\Sale;
use App\Models\Pos\SaleLine;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    use ApiResponse;

    /**
     * Real-time sales ticker - recent sales feed.
     */
    public function salesTicker(Request $request): JsonResponse
    {
        $limit = $request->input('limit', 20);

        $sales = Sale::with(['customer:id,name,code', 'store:id,name', 'sold_by:id,name'])
            ->where('cancelled', false)
            ->where('type', 0)
            ->select([
                'id',
                'son',
                'total',
                'customer_id',
                'store_id',
                'sales_by',
                'payment_type',
                'created_at',
            ])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($sale) {
                return [
                    'id' => $sale->id,
                    'son' => $sale->son,
                    'total' => (float) $sale->total,
                    'customer_name' => $sale->customer?->name ?? 'Walk-in',
                    'store_name' => $sale->store?->name,
                    'cashier_name' => $sale->sold_by?->name,
                    'payment_type' => $sale->payment_type ?? 'cash',
                    'time_ago' => $sale->created_at->diffForHumans(),
                    'created_at' => $sale->created_at,
                ];
            });

        return $this->success([
            'sales' => $sales,
            'last_updated' => now()->toIso8601String(),
        ]);
    }

    /**
     * Top selling products widget.
     */
    public function topProducts(Request $request): JsonResponse
    {
        $period = $request->input('period', 'today');
        $limit = $request->input('limit', 10);

        [$startDate, $endDate] = $this->getDateRange($period);

        $products = SaleLine::query()
            ->join('sales', 'sale_lines.sales_id', '=', 'sales.id')
            ->join('items', 'sale_lines.item_id', '=', 'items.id')
            ->leftJoin('categories', 'items.category_id', '=', 'categories.id')
            ->whereBetween('sales.created_at', [$startDate, $endDate])
            ->where('sales.cancelled', false)
            ->where('sales.type', 0)
            ->select([
                'items.id',
                'items.name',
                'items.barcode',
                'categories.name as category_name',
                DB::raw('SUM(sale_lines.qty * sale_lines.unit_qty) as total_qty_sold'),
                DB::raw('SUM(sale_lines.sub_total) as total_revenue'),
                DB::raw('COUNT(DISTINCT sales.id) as transaction_count'),
            ])
            ->groupBy('items.id', 'items.name', 'items.barcode', 'categories.name')
            ->orderByDesc('total_qty_sold')
            ->limit($limit)
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'barcode' => $product->barcode,
                    'category' => $product->category_name,
                    'total_qty_sold' => (float) $product->total_qty_sold,
                    'total_revenue' => round((float) $product->total_revenue, 2),
                    'transaction_count' => (int) $product->transaction_count,
                ];
            });

        return $this->success([
            'products' => $products,
            'period' => $period,
            'date_range' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
        ]);
    }

    /**
     * Revenue comparison (daily/weekly/monthly).
     */
    public function revenueComparison(Request $request): JsonResponse
    {
        $period = $request->input('period', 'daily');

        $comparison = match ($period) {
            'weekly' => $this->getWeeklyComparison(),
            'monthly' => $this->getMonthlyComparison(),
            default => $this->getDailyComparison(),
        };

        return $this->success([
            'comparison' => $comparison,
            'period' => $period,
        ]);
    }

    /**
     * Staff performance leaderboard.
     */
    public function staffLeaderboard(Request $request): JsonResponse
    {
        $period = $request->input('period', 'today');
        $limit = $request->input('limit', 10);

        [$startDate, $endDate] = $this->getDateRange($period);

        $staff = User::query()
            ->join('sales', 'users.id', '=', 'sales.sales_by')
            ->leftJoin('roles', 'users.role_id', '=', 'roles.id')
            ->whereBetween('sales.created_at', [$startDate, $endDate])
            ->where('sales.cancelled', false)
            ->where('sales.type', 0)
            ->select([
                'users.id',
                'users.name',
                'roles.name as role_name',
                DB::raw('COUNT(sales.id) as total_transactions'),
                DB::raw('SUM(sales.total) as total_sales'),
                DB::raw('SUM(sales.profit) as total_profit'),
                DB::raw('AVG(sales.total) as average_transaction'),
            ])
            ->groupBy('users.id', 'users.name', 'roles.name')
            ->orderByDesc('total_sales')
            ->limit($limit)
            ->get()
            ->map(function ($member, $index) {
                return [
                    'rank' => $index + 1,
                    'id' => $member->id,
                    'name' => $member->name,
                    'role' => $member->role_name,
                    'total_transactions' => (int) $member->total_transactions,
                    'total_sales' => round((float) $member->total_sales, 2),
                    'total_profit' => round((float) $member->total_profit, 2),
                    'average_transaction' => round((float) $member->average_transaction, 2),
                ];
            });

        return $this->success([
            'leaderboard' => $staff,
            'period' => $period,
            'date_range' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
        ]);
    }

    /**
     * Get date range based on period string.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function getDateRange(string $period): array
    {
        return match ($period) {
            'yesterday' => [
                Carbon::yesterday()->startOfDay(),
                Carbon::yesterday()->endOfDay(),
            ],
            'this_week' => [
                Carbon::now()->startOfWeek(),
                Carbon::now()->endOfDay(),
            ],
            'last_week' => [
                Carbon::now()->subWeek()->startOfWeek(),
                Carbon::now()->subWeek()->endOfWeek(),
            ],
            'this_month' => [
                Carbon::now()->startOfMonth(),
                Carbon::now()->endOfDay(),
            ],
            'last_month' => [
                Carbon::now()->subMonth()->startOfMonth(),
                Carbon::now()->subMonth()->endOfMonth(),
            ],
            default => [
                Carbon::today()->startOfDay(),
                Carbon::now()->endOfDay(),
            ],
        };
    }

    /**
     * Get daily revenue comparison (today vs yesterday).
     *
     * @return array<string, mixed>
     */
    private function getDailyComparison(): array
    {
        $today = $this->getSalesMetrics(
            Carbon::today()->startOfDay(),
            Carbon::now()->endOfDay()
        );

        $yesterday = $this->getSalesMetrics(
            Carbon::yesterday()->startOfDay(),
            Carbon::yesterday()->endOfDay()
        );

        return [
            'current' => [
                'label' => 'Today',
                'date' => Carbon::today()->toDateString(),
                ...$today,
            ],
            'previous' => [
                'label' => 'Yesterday',
                'date' => Carbon::yesterday()->toDateString(),
                ...$yesterday,
            ],
            'change' => $this->calculateChange($today, $yesterday),
        ];
    }

    /**
     * Get weekly revenue comparison (this week vs last week).
     *
     * @return array<string, mixed>
     */
    private function getWeeklyComparison(): array
    {
        $thisWeek = $this->getSalesMetrics(
            Carbon::now()->startOfWeek(),
            Carbon::now()->endOfDay()
        );

        $lastWeek = $this->getSalesMetrics(
            Carbon::now()->subWeek()->startOfWeek(),
            Carbon::now()->subWeek()->endOfWeek()
        );

        return [
            'current' => [
                'label' => 'This Week',
                'start_date' => Carbon::now()->startOfWeek()->toDateString(),
                'end_date' => Carbon::now()->toDateString(),
                ...$thisWeek,
            ],
            'previous' => [
                'label' => 'Last Week',
                'start_date' => Carbon::now()->subWeek()->startOfWeek()->toDateString(),
                'end_date' => Carbon::now()->subWeek()->endOfWeek()->toDateString(),
                ...$lastWeek,
            ],
            'change' => $this->calculateChange($thisWeek, $lastWeek),
        ];
    }

    /**
     * Get monthly revenue comparison (this month vs last month).
     *
     * @return array<string, mixed>
     */
    private function getMonthlyComparison(): array
    {
        $thisMonth = $this->getSalesMetrics(
            Carbon::now()->startOfMonth(),
            Carbon::now()->endOfDay()
        );

        $lastMonth = $this->getSalesMetrics(
            Carbon::now()->subMonth()->startOfMonth(),
            Carbon::now()->subMonth()->endOfMonth()
        );

        return [
            'current' => [
                'label' => 'This Month',
                'month' => Carbon::now()->format('F Y'),
                ...$thisMonth,
            ],
            'previous' => [
                'label' => 'Last Month',
                'month' => Carbon::now()->subMonth()->format('F Y'),
                ...$lastMonth,
            ],
            'change' => $this->calculateChange($thisMonth, $lastMonth),
        ];
    }

    /**
     * Get sales metrics for a date range.
     *
     * @return array{total_sales: float, total_profit: float, transaction_count: int, refunds: float}
     */
    private function getSalesMetrics(Carbon $startDate, Carbon $endDate): array
    {
        $metrics = Sale::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('cancelled', false)
            ->select([
                DB::raw('SUM(IF(type = 0, total, 0)) as total_sales'),
                DB::raw('SUM(IF(type = 0, profit, -profit)) as total_profit'),
                DB::raw('COUNT(IF(type = 0, 1, NULL)) as transaction_count'),
                DB::raw('SUM(IF(type = 1, total, 0)) as refunds'),
            ])
            ->first();

        return [
            'total_sales' => round((float) ($metrics->total_sales ?? 0), 2),
            'total_profit' => round((float) ($metrics->total_profit ?? 0), 2),
            'transaction_count' => (int) ($metrics->transaction_count ?? 0),
            'refunds' => round((float) ($metrics->refunds ?? 0), 2),
        ];
    }

    /**
     * Calculate percentage change between periods.
     *
     * @param  array{total_sales: float, total_profit: float, transaction_count: int}  $current
     * @param  array{total_sales: float, total_profit: float, transaction_count: int}  $previous
     * @return array{sales_change: float, profit_change: float, transaction_change: float}
     */
    private function calculateChange(array $current, array $previous): array
    {
        return [
            'sales_change' => $this->percentageChange($current['total_sales'], $previous['total_sales']),
            'profit_change' => $this->percentageChange($current['total_profit'], $previous['total_profit']),
            'transaction_change' => $this->percentageChange($current['transaction_count'], $previous['transaction_count']),
        ];
    }

    /**
     * Calculate percentage change.
     */
    private function percentageChange(float $current, float $previous): float
    {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }

        return round((($current - $previous) / $previous) * 100, 2);
    }
}
