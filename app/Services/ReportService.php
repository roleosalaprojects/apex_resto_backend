<?php

namespace App\Services;

use App\Models\Pos\Sale;
use App\Models\Pos\SaleLine;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportService
{
    /**
     * Generate sales summary for a given period.
     *
     * @return array{sales: float, refunds: float, profit: float, transactions: int, top_items: array, comparison: array}
     */
    public function getSalesSummary(int $userId, string $period = 'daily', ?string $date = null, ?int $storeId = null): array
    {
        $tz = config('app.timezone', 'Asia/Manila');
        $date = $date ? Carbon::parse($date, $tz) : Carbon::today($tz);

        [$currentStart, $currentEnd] = $this->getPeriodRange($period, $date);
        [$previousStart, $previousEnd] = $this->getPreviousPeriodRange($period, $date);

        $current = $this->aggregateSales($userId, $currentStart, $currentEnd, $storeId);
        $previous = $this->aggregateSales($userId, $previousStart, $previousEnd, $storeId);
        $topItems = $this->getTopItems($userId, $currentStart, $currentEnd, $storeId, 5);

        $changePct = $previous['sales'] > 0
            ? round((($current['sales'] - $previous['sales']) / $previous['sales']) * 100, 2)
            : null;

        return [
            'sales' => $current['sales'],
            'refunds' => $current['refunds'],
            'profit' => $current['profit'],
            'transactions' => $current['transactions'],
            'top_items' => $topItems,
            'comparison' => [
                'previous_period' => [
                    'sales' => $previous['sales'],
                    'refunds' => $previous['refunds'],
                    'profit' => $previous['profit'],
                    'transactions' => $previous['transactions'],
                ],
                'change_pct' => $changePct,
            ],
        ];
    }

    /**
     * Generate full report data for email.
     *
     * @return array{summary: array, peak_hours_summary: array, margin_alerts: array, sold_items: array}
     */
    public function generateReportData(int $userId, string $type = 'daily', ?int $storeId = null): array
    {
        $tz = config('app.timezone', 'Asia/Manila');
        $date = Carbon::today($tz);

        [$start, $end] = $this->getPeriodRange($type, $date);

        $summary = $this->getSalesSummary($userId, $type, null, $storeId);
        $soldItems = $this->getSoldItems($userId, $start, $end, $storeId);

        $peakHoursSummary = [];
        $marginAlerts = [];

        $peakHoursService = app(PeakHoursAnalysisService::class);
        $days = $type === 'weekly' ? 7 : 1;
        $peakData = $peakHoursService->getHeatmapData($userId, $days, $storeId);
        $peakHoursSummary = $peakData['peak_hours'] ?? [];

        $profitService = app(ProfitAnalysisService::class);
        $alertData = $profitService->getMarginAlerts($userId, $storeId);
        $marginAlerts = $alertData['alerts'] ?? [];

        return [
            'summary' => $summary,
            'peak_hours_summary' => array_slice($peakHoursSummary, 0, 3),
            'margin_alerts' => array_slice($marginAlerts, 0, 5),
            'sold_items' => $soldItems,
            'cashless_breakdown' => $this->getCashlessBreakdown($userId, $start, $end, $storeId),
            'pending_cheques' => $this->getPendingChequesSummary($userId),
        ];
    }

    /**
     * Admin-recorded cashless sales (pos_id IS NULL) for a period,
     * grouped by payment_type so the email can show what came in via
     * GCash vs bank transfer vs cheque. Cash is included for symmetry
     * but is rarely populated through the admin path.
     *
     * @return array<int, array{payment_type: int, label: string, count: int, total: float}>
     */
    public function getCashlessBreakdown(
        int $userId,
        Carbon $start,
        Carbon $end,
        ?int $storeId = null,
    ): array {
        $labels = [
            Sale::PAYMENT_CASH => 'Cash (web admin)',
            Sale::PAYMENT_EWALLET => 'GCash / E-Wallet',
            Sale::PAYMENT_BANK_TRANSFER => 'Bank Transfer',
            Sale::PAYMENT_CHEQUE => 'Cheque',
        ];

        $query = Sale::where('user_id', $userId)
            ->whereNull('pos_id')
            ->where('cancelled', 0)
            ->where('type', false)
            ->whereBetween('created_at', [$start, $end])
            ->select(
                'payment_type',
                DB::raw('COUNT(*) as txn_count'),
                DB::raw('COALESCE(SUM(total), 0) as total'),
            )
            ->groupBy('payment_type');

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        $rows = $query->get();

        return $rows->map(fn ($row) => [
            'payment_type' => (int) $row->payment_type,
            'label' => $labels[(int) $row->payment_type] ?? 'Other',
            'count' => (int) $row->txn_count,
            'total' => round((float) $row->total, 2),
        ])->all();
    }

    /**
     * Pending cheques across all stores for a tenant — count, sum, and
     * the longest-outstanding cheque in days. Used to flag stale
     * cheques in the daily email so admin chases them up.
     *
     * @return array{count: int, total: float, oldest_days: int|null}
     */
    public function getPendingChequesSummary(int $userId): array
    {
        $row = Sale::where('user_id', $userId)
            ->where('payment_type', Sale::PAYMENT_CHEQUE)
            ->where('cheque_status', Sale::CHEQUE_PENDING)
            ->select(
                DB::raw('COUNT(*) as cheque_count'),
                DB::raw('COALESCE(SUM(bank_amount), 0) as total'),
                DB::raw('MIN(created_at) as oldest_at'),
            )
            ->first();

        $oldestAt = $row->oldest_at ? Carbon::parse($row->oldest_at) : null;

        return [
            'count' => (int) $row->cheque_count,
            'total' => round((float) $row->total, 2),
            'oldest_days' => $oldestAt ? (int) $oldestAt->diffInDays(now()) : null,
        ];
    }

    /**
     * Aggregate sales for a date range.
     *
     * @return array{sales: float, refunds: float, profit: float, transactions: int}
     */
    protected function aggregateSales(int $userId, Carbon $start, Carbon $end, ?int $storeId = null): array
    {
        $query = Sale::where('user_id', $userId)
            ->where('cancelled', 0)
            ->whereBetween('created_at', [$start, $end])
            ->select(
                DB::raw('COALESCE(SUM(CASE WHEN type = 0 THEN total ELSE 0 END), 0) as sales'),
                DB::raw('COALESCE(SUM(CASE WHEN type = 1 THEN total ELSE 0 END), 0) as refunds'),
                DB::raw('COALESCE(SUM(CASE WHEN type = 0 THEN profit ELSE -profit END), 0) as profit'),
                DB::raw('COUNT(*) as transactions')
            );

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        $result = $query->first();

        return [
            'sales' => round((float) $result->sales, 2),
            'refunds' => round((float) $result->refunds, 2),
            'profit' => round((float) $result->profit, 2),
            'transactions' => (int) $result->transactions,
        ];
    }

    /**
     * Get top selling items for a period.
     *
     * @return array<int, array{item_name: string, qty_sold: float, total_sales: float}>
     */
    protected function getTopItems(int $userId, Carbon $start, Carbon $end, ?int $storeId = null, int $limit = 5): array
    {
        $query = SaleLine::query()
            ->join('sales', 'sales.id', '=', 'sale_lines.sales_id')
            ->join('items', 'items.id', '=', 'sale_lines.item_id')
            ->where('sales.user_id', $userId)
            ->where('sales.cancelled', 0)
            ->where('sales.type', 0)
            ->whereBetween('sales.created_at', [$start, $end])
            ->select(
                'items.name as item_name',
                DB::raw('SUM(sale_lines.qty) as qty_sold'),
                DB::raw('SUM(sale_lines.sub_total) as total_sales')
            )
            ->groupBy('sale_lines.item_id', 'items.name')
            ->orderByDesc('total_sales')
            ->limit($limit);

        if ($storeId) {
            $query->where('sales.store_id', $storeId);
        }

        return $query->get()->map(function ($row) {
            return [
                'item_name' => $row->item_name,
                'qty_sold' => (float) $row->qty_sold,
                'total_sales' => round((float) $row->total_sales, 2),
            ];
        })->toArray();
    }

    /**
     * Get all items sold for a period (matches admin sales_by_item format).
     *
     * @return array<int, array{item: string, items_sold: float, net_sales: float, revenue: float}>
     */
    public function getSoldItems(int $userId, Carbon $start, Carbon $end, ?int $storeId = null): array
    {
        $query = SaleLine::query()
            ->join('sales as s', 'sale_lines.sales_id', '=', 's.id')
            ->join('items as i', 'i.id', '=', 'sale_lines.item_id')
            ->where('s.user_id', $userId)
            ->where('s.cancelled', 0)
            ->whereBetween('s.created_at', [$start, $end])
            ->select(
                DB::raw('i.name as item'),
                DB::raw('SUM(IF(s.type = 0, (sale_lines.qty * sale_lines.unit_qty), -(sale_lines.qty * sale_lines.unit_qty))) as items_sold'),
                DB::raw('SUM(IF(s.type = 0, sale_lines.sub_total, -sale_lines.sub_total)) as net_sales'),
                DB::raw('SUM(IF(s.type = 0, sale_lines.sub_total - (sale_lines.qty * sale_lines.cost * sale_lines.unit_qty), -(sale_lines.sub_total - (sale_lines.qty * sale_lines.cost * sale_lines.unit_qty)))) as revenue'),
            )
            ->groupBy('sale_lines.item_id', 'i.name')
            ->orderByDesc('net_sales');

        if ($storeId) {
            $query->where('s.store_id', $storeId);
        }

        return $query->get()->map(function ($row) {
            return [
                'item' => $row->item,
                'items_sold' => (float) $row->items_sold,
                'net_sales' => round((float) $row->net_sales, 2),
                'revenue' => round((float) $row->revenue, 2),
            ];
        })->toArray();
    }

    /**
     * Generate CSV content for sold items.
     */
    public function generateSoldItemsCsv(array $soldItems): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, ['Item', 'Items Sold', 'Net Sales', 'Revenue']);

        foreach ($soldItems as $item) {
            fputcsv($handle, [
                $item['item'],
                $item['items_sold'],
                number_format($item['net_sales'], 2, '.', ''),
                number_format($item['revenue'], 2, '.', ''),
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * Get current period date range.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function getPeriodRange(string $period, Carbon $date): array
    {
        return match ($period) {
            'daily' => [$date->copy()->startOfDay(), $date->copy()->endOfDay()],
            'weekly' => [$date->copy()->startOfWeek(), $date->copy()->endOfWeek()],
            'monthly' => [$date->copy()->startOfMonth(), $date->copy()->endOfMonth()],
            default => [$date->copy()->startOfDay(), $date->copy()->endOfDay()],
        };
    }

    /**
     * Get previous period date range for comparison.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function getPreviousPeriodRange(string $period, Carbon $date): array
    {
        return match ($period) {
            'daily' => [$date->copy()->subDay()->startOfDay(), $date->copy()->subDay()->endOfDay()],
            'weekly' => [$date->copy()->subWeek()->startOfWeek(), $date->copy()->subWeek()->endOfWeek()],
            'monthly' => [$date->copy()->subMonth()->startOfMonth(), $date->copy()->subMonth()->endOfMonth()],
            default => [$date->copy()->subDay()->startOfDay(), $date->copy()->subDay()->endOfDay()],
        };
    }
}
