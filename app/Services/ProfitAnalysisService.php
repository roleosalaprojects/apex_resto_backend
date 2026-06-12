<?php

namespace App\Services;

use App\Models\Pos\SaleLine;
use App\Models\Products\Item;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ProfitAnalysisService
{
    /**
     * Get profit margin data grouped by item with period comparison.
     *
     * @return array{data: array}
     */
    public function getProfitMargins(
        int $userId,
        int $period = 30,
        ?int $storeId = null,
        string $sort = 'margin_change'
    ): array {
        $tz = config('app.timezone', 'Asia/Manila');
        $currentStart = Carbon::today($tz)->subDays($period);
        $previousStart = Carbon::today($tz)->subDays($period * 2);
        $previousEnd = Carbon::today($tz)->subDays($period);

        // Current period margins
        $currentData = $this->getMarginsByItem($userId, $currentStart, Carbon::now($tz), $storeId);

        // Previous period margins for comparison
        $previousData = $this->getMarginsByItem($userId, $previousStart, $previousEnd, $storeId);

        $previousLookup = collect($previousData)->keyBy('item_id');

        $results = collect($currentData)->map(function ($item) use ($previousLookup) {
            $previousItem = $previousLookup->get($item['item_id']);
            $previousMarginPct = $previousItem ? $previousItem['margin_pct'] : null;

            $marginChange = $previousMarginPct !== null
                ? $item['margin_pct'] - $previousMarginPct
                : null;

            return [
                'item_id' => $item['item_id'],
                'item_name' => $item['item_name'],
                'current_margin_pct' => round($item['margin_pct'], 2),
                'previous_margin_pct' => $previousMarginPct !== null ? round($previousMarginPct, 2) : null,
                'margin_change' => $marginChange !== null ? round($marginChange, 2) : null,
                'current_cost' => round($item['avg_cost'], 2),
                'current_price' => round($item['avg_price'], 2),
                'total_sold' => $item['total_qty'],
                'total_profit' => round($item['total_profit'], 2),
            ];
        });

        // Sort results
        $results = match ($sort) {
            'margin_change' => $results->sortBy('margin_change')->values(),
            'margin_change_desc' => $results->sortByDesc('margin_change')->values(),
            'margin_pct' => $results->sortByDesc('current_margin_pct')->values(),
            'total_profit' => $results->sortByDesc('total_profit')->values(),
            'total_sold' => $results->sortByDesc('total_sold')->values(),
            default => $results->sortBy('margin_change')->values(),
        };

        return ['data' => $results->toArray()];
    }

    /**
     * Get margin trend for a specific item over time.
     *
     * @return array{item_name: string, data_points: array}
     */
    public function getMarginTrend(int $userId, int $itemId, int $days = 90, ?int $storeId = null): array
    {
        $item = Item::find($itemId);

        $query = SaleLine::query()
            ->join('sales', 'sales.id', '=', 'sale_lines.sales_id')
            ->where('sales.user_id', $userId)
            ->where('sales.cancelled', 0)
            ->where('sale_lines.item_id', $itemId)
            ->where('sales.created_at', '>=', Carbon::today(config('app.timezone'))->subDays($days))
            ->select(
                DB::raw('DATE(sales.created_at) as date'),
                DB::raw('AVG(sale_lines.cost) as avg_cost'),
                DB::raw('AVG(sale_lines.price) as avg_price'),
                DB::raw('SUM(sale_lines.qty) as qty_sold'),
                DB::raw('SUM(sale_lines.profit) as total_profit'),
                DB::raw('SUM(sale_lines.sub_total) as total_revenue')
            )
            ->groupBy(DB::raw('DATE(sales.created_at)'))
            ->orderBy('date');

        if ($storeId) {
            $query->where('sales.store_id', $storeId);
        }

        $dataPoints = $query->get()->map(function ($row) {
            $avgPrice = (float) $row->avg_price;
            $avgCost = (float) $row->avg_cost;
            $marginPct = $avgPrice > 0 ? (($avgPrice - $avgCost) / $avgPrice) * 100 : 0;

            return [
                'date' => $row->date,
                'margin_pct' => round($marginPct, 2),
                'cost' => round($avgCost, 2),
                'avg_price' => round($avgPrice, 2),
                'qty_sold' => (float) $row->qty_sold,
            ];
        })->toArray();

        return [
            'item_name' => $item ? $item->name : 'Unknown',
            'data_points' => $dataPoints,
        ];
    }

    /**
     * Get margin alerts: items with margin drops exceeding threshold.
     *
     * @return array{alerts: array}
     */
    public function getMarginAlerts(int $userId, ?int $storeId = null, float $threshold = 5.0): array
    {
        $margins = $this->getProfitMargins($userId, 30, $storeId, 'margin_change');

        $alerts = collect($margins['data'])
            ->filter(function ($item) use ($threshold) {
                return $item['margin_change'] !== null && $item['margin_change'] < -$threshold;
            })
            ->map(function ($item) {
                $reason = 'Margin decreased';
                if ($item['current_cost'] > 0 && $item['previous_margin_pct'] !== null) {
                    // Check if cost increased
                    $reason = $item['current_margin_pct'] < $item['previous_margin_pct']
                        ? 'Cost increase or price decrease detected'
                        : 'Price adjustment needed';
                }

                return [
                    'item_id' => $item['item_id'],
                    'item_name' => $item['item_name'],
                    'margin_drop_pct' => abs($item['margin_change']),
                    'old_margin' => $item['previous_margin_pct'],
                    'new_margin' => $item['current_margin_pct'],
                    'reason' => $reason,
                ];
            })
            ->values()
            ->toArray();

        // FCM dispatch intentionally NOT here. This method is called on every
        // render of the profit-margins report (ProfitMarginController::index),
        // and firing a notification on a read path was the original source of
        // notification spam. The scheduled `notifications:fire-alerts` command
        // is the single trigger surface for margin_alert pushes.

        return ['alerts' => $alerts];
    }

    /**
     * Query margins grouped by item for a date range.
     *
     * @return array<int, array{item_id: int, item_name: string, margin_pct: float, avg_cost: float, avg_price: float, total_qty: float, total_profit: float}>
     */
    protected function getMarginsByItem(int $userId, Carbon $start, Carbon $end, ?int $storeId = null): array
    {
        $query = SaleLine::query()
            ->join('sales', 'sales.id', '=', 'sale_lines.sales_id')
            ->join('items', 'items.id', '=', 'sale_lines.item_id')
            ->where('sales.user_id', $userId)
            ->where('sales.cancelled', 0)
            ->whereBetween('sales.created_at', [$start, $end])
            ->select(
                'sale_lines.item_id',
                'items.name as item_name',
                DB::raw('AVG(sale_lines.cost) as avg_cost'),
                DB::raw('AVG(sale_lines.price) as avg_price'),
                DB::raw('SUM(sale_lines.qty) as total_qty'),
                DB::raw('SUM(sale_lines.profit) as total_profit'),
                DB::raw('SUM(sale_lines.sub_total) as total_revenue')
            )
            ->groupBy('sale_lines.item_id', 'items.name');

        if ($storeId) {
            $query->where('sales.store_id', $storeId);
        }

        return $query->get()->map(function ($row) {
            $avgPrice = (float) $row->avg_price;
            $avgCost = (float) $row->avg_cost;
            $marginPct = $avgPrice > 0 ? (($avgPrice - $avgCost) / $avgPrice) * 100 : 0;

            return [
                'item_id' => $row->item_id,
                'item_name' => $row->item_name,
                'margin_pct' => $marginPct,
                'avg_cost' => $avgCost,
                'avg_price' => $avgPrice,
                'total_qty' => (float) $row->total_qty,
                'total_profit' => (float) $row->total_profit,
            ];
        })->toArray();
    }
}
