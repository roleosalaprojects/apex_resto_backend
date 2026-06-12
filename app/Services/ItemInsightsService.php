<?php

namespace App\Services;

use App\Models\ItemInsight;
use App\Models\Products\Item;
use App\Models\Products\ItemStore;
use App\Models\Settings\Store;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ItemInsightsService
{
    protected AiService $ai;

    protected WeatherService $weather;

    protected bool $useAI;

    protected bool $useWeather;

    /**
     * Weighted moving average weights (same pattern as DemandForecastService).
     */
    protected array $weights = [0.35, 0.25, 0.20, 0.12, 0.08];

    /**
     * Philippine fixed holidays with sales factors.
     */
    protected array $fixedHolidays = [
        '01-01' => ['name' => "New Year's Day", 'sales_factor' => 0.3],
        '01-02' => ['name' => 'New Year Holiday', 'sales_factor' => 0.6],
        '02-25' => ['name' => 'EDSA Revolution', 'sales_factor' => 0.9],
        '04-09' => ['name' => 'Araw ng Kagitingan', 'sales_factor' => 0.85],
        '05-01' => ['name' => 'Labor Day', 'sales_factor' => 0.85],
        '06-12' => ['name' => 'Independence Day', 'sales_factor' => 0.85],
        '08-21' => ['name' => 'Ninoy Aquino Day', 'sales_factor' => 0.9],
        '08-26' => ['name' => 'National Heroes Day', 'sales_factor' => 0.9],
        '11-01' => ['name' => "All Saints' Day", 'sales_factor' => 0.7],
        '11-02' => ['name' => "All Souls' Day", 'sales_factor' => 0.8],
        '11-30' => ['name' => 'Bonifacio Day', 'sales_factor' => 0.85],
        '12-08' => ['name' => 'Immaculate Conception', 'sales_factor' => 0.9],
        '12-24' => ['name' => 'Christmas Eve', 'sales_factor' => 1.5],
        '12-25' => ['name' => 'Christmas Day', 'sales_factor' => 0.3],
        '12-26' => ['name' => 'Post-Christmas', 'sales_factor' => 0.7],
        '12-30' => ['name' => 'Rizal Day', 'sales_factor' => 1.3],
        '12-31' => ['name' => "New Year's Eve", 'sales_factor' => 1.4],
    ];

    protected array $preHolidayBoosts = [
        '12-24' => ['days_before' => 14, 'peak_factor' => 1.6],
        '12-31' => ['days_before' => 3, 'peak_factor' => 1.3],
        '11-01' => ['days_before' => 3, 'peak_factor' => 1.2],
    ];

    public function __construct(AiService $ai, WeatherService $weather)
    {
        $this->ai = $ai;
        $this->weather = $weather;
        $this->useAI = $ai->isAvailable();
        $this->useWeather = $weather->isAvailable();
    }

    /**
     * Get top insights, reading from cache (DB) or generating fresh.
     */
    public function getTopInsights(int $userId, Carbon $date, ?int $storeId = null, bool $refresh = false): Collection
    {
        if (! $refresh) {
            $existing = ItemInsight::with('item:id,name')
                ->where('user_id', $userId)
                ->where('insight_date', $date->toDateString())
                ->where('store_id', $storeId)
                ->orderBy('rank')
                ->get();

            if ($existing->isNotEmpty()) {
                return $existing;
            }
        }

        return $this->generateTopInsights($userId, $date, $storeId);
    }

    /**
     * Generate top sellable items with scores and AI insights.
     */
    public function generateTopInsights(int $userId, Carbon $date, ?int $storeId = null, int $limit = 100): Collection
    {
        // Clear existing insights for this date/store
        ItemInsight::where('user_id', $userId)
            ->where('insight_date', $date->toDateString())
            ->where('store_id', $storeId)
            ->delete();

        // Run bulk queries
        $salesData = $this->getBulkSalesData($userId, $storeId);
        $sameDayData = $this->getSameDayOfWeekData($userId, $date, $storeId);
        $trendData = $this->getTrendData($userId, $storeId);

        // Load items with stock and category
        $itemsQuery = Item::where('items.user_id', $userId)
            ->where('items.status', true)
            ->with('category');

        if ($storeId) {
            $itemsQuery->whereHas('itemStores', fn ($q) => $q->where('store_id', $storeId));
        }

        $items = $itemsQuery->get()->keyBy('id');

        // Load stock levels
        $stockQuery = ItemStore::whereIn('item_id', $items->keys());
        if ($storeId) {
            $stockQuery->where('store_id', $storeId);
        }
        $stocks = $stockQuery->get()->groupBy('item_id');

        // Get weather and holiday factors
        $weatherFactor = 1.0;
        $weatherCondition = null;
        if ($this->useWeather && $storeId) {
            $store = Store::find($storeId);
            if ($store && $store->latitude && $store->longitude) {
                $weatherFactor = $this->weather->getWeatherSalesFactor(
                    (float) $store->latitude,
                    (float) $store->longitude,
                    $date
                );
                $weatherInfo = $this->weather->getWeatherInfo(
                    (float) $store->latitude,
                    (float) $store->longitude,
                    $date
                );
                $weatherCondition = $weatherInfo['condition'] ?? null;
            }
        }
        $holidayFactor = $this->getHolidaySalesFactor($date);

        // Compute margin percentiles across all items
        $allMargins = $items->map(function ($item) {
            if (! $item->price || $item->price <= 0) {
                return 0;
            }

            return (($item->price - $item->cost) / $item->price) * 100;
        })->sort()->values();

        // Score each item
        $scored = [];
        foreach ($items as $itemId => $item) {
            $itemSales = $salesData->get($itemId);

            // Skip items with no sales history
            if (! $itemSales) {
                continue;
            }

            $itemSameDayQty = $sameDayData->get($itemId, collect());
            $itemTrend = $trendData->get($itemId);
            $itemStock = $stocks->get($itemId)?->sum('stock') ?? 0;
            $margin = ($item->price > 0) ? (($item->price - $item->cost) / $item->price) * 100 : 0;

            $result = $this->computeSellabilityScore(
                salesAgg: $itemSales,
                sameDayQtys: $itemSameDayQty,
                trend: $itemTrend,
                margin: $margin,
                allMargins: $allMargins,
                stock: $itemStock,
                holidayFactor: $holidayFactor,
                weatherFactor: $weatherFactor,
            );

            $result['item_id'] = $itemId;
            $result['item_name'] = $item->name;
            $result['category_name'] = $item->category?->name;
            $result['current_stock'] = $itemStock;
            $result['profit_margin'] = round($margin, 2);
            $result['factors'] = $this->buildFactorTags(
                $result['breakdown'],
                $holidayFactor,
                $weatherFactor,
                $itemTrend,
                $date
            );

            $scored[] = $result;
        }

        // Sort by score descending, take top N
        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);
        $topItems = array_slice($scored, 0, $limit);

        // Generate AI insights via single batched call
        $aiInsights = [];
        if ($this->useAI && ! empty($topItems)) {
            $ollamaInput = array_map(fn ($item) => [
                'name' => $item['item_name'],
                'category' => $item['category_name'] ?? 'Uncategorized',
                'score' => $item['score'],
                'predicted_qty' => $item['predicted_qty'],
                'factors' => $item['factors'],
            ], $topItems);

            $context = [
                'date' => $date->toDateString(),
                'day' => $date->format('l'),
            ];
            if ($weatherCondition) {
                $context['weather'] = $weatherCondition;
            }

            $aiInsights = $this->ai->generateItemInsights($ollamaInput, $context) ?? [];
        }

        // Persist to DB
        $results = collect();
        foreach ($topItems as $rank => $item) {
            $insight = ItemInsight::create([
                'user_id' => $userId,
                'store_id' => $storeId,
                'insight_date' => $date->toDateString(),
                'item_id' => $item['item_id'],
                'rank' => $rank + 1,
                'sellability_score' => $item['score'],
                'score_breakdown' => $item['breakdown'],
                'ai_insight' => $aiInsights[$rank] ?? null,
                'predicted_qty' => $item['predicted_qty'],
                'current_stock' => $item['current_stock'],
                'profit_margin' => $item['profit_margin'],
                'category_name' => $item['category_name'],
                'factors' => $item['factors'],
            ]);
            $results->push($insight);
        }

        return $results;
    }

    /**
     * 90-day per-item sales aggregates.
     *
     * @return Collection<int, array{total_qty: float, total_revenue: float, total_profit: float, days_sold: int}>
     */
    protected function getBulkSalesData(int $userId, ?int $storeId = null): Collection
    {
        $query = DB::table('sale_lines')
            ->join('sales', 'sales.id', '=', 'sale_lines.sales_id')
            ->where('sales.user_id', $userId)
            ->where('sales.cancelled', 0)
            ->where('sales.type', 0)
            ->where('sales.created_at', '>=', Carbon::today()->subDays(90))
            ->select(
                'sale_lines.item_id',
                DB::raw('SUM(sale_lines.qty) as total_qty'),
                DB::raw('SUM(sale_lines.sub_total) as total_revenue'),
                DB::raw('SUM(sale_lines.profit) as total_profit'),
                DB::raw('COUNT(DISTINCT DATE(sales.created_at)) as days_sold')
            )
            ->groupBy('sale_lines.item_id');

        if ($storeId) {
            $query->where('sales.store_id', $storeId);
        }

        return $query->get()->keyBy('item_id')->map(fn ($row) => [
            'total_qty' => (float) $row->total_qty,
            'total_revenue' => (float) $row->total_revenue,
            'total_profit' => (float) $row->total_profit,
            'days_sold' => (int) $row->days_sold,
        ]);
    }

    /**
     * Per-item same-day-of-week daily quantities from last 8 weeks.
     *
     * @return Collection<int, Collection<int, float>> item_id => collection of daily qtys
     */
    protected function getSameDayOfWeekData(int $userId, Carbon $date, ?int $storeId = null): Collection
    {
        $dayOfWeek = $date->dayOfWeek + 1; // MySQL DAYOFWEEK is 1-indexed (1=Sunday)

        $query = DB::table('sale_lines')
            ->join('sales', 'sales.id', '=', 'sale_lines.sales_id')
            ->where('sales.user_id', $userId)
            ->where('sales.cancelled', 0)
            ->where('sales.type', 0)
            ->where('sales.created_at', '>=', Carbon::today()->subWeeks(8))
            ->whereRaw('DAYOFWEEK(sales.created_at) = ?', [$dayOfWeek])
            ->select(
                'sale_lines.item_id',
                DB::raw('DATE(sales.created_at) as sale_date'),
                DB::raw('SUM(sale_lines.qty) as daily_qty')
            )
            ->groupBy('sale_lines.item_id', DB::raw('DATE(sales.created_at)'))
            ->orderBy('sale_date', 'desc');

        if ($storeId) {
            $query->where('sales.store_id', $storeId);
        }

        return $query->get()
            ->groupBy('item_id')
            ->map(fn ($rows) => $rows->pluck('daily_qty')->map(fn ($v) => (float) $v));
    }

    /**
     * Per-item 7d vs prior 7d trend comparison.
     *
     * @return Collection<int, array{recent_qty: float, prior_qty: float, ratio: float}>
     */
    protected function getTrendData(int $userId, ?int $storeId = null): Collection
    {
        $recent = DB::table('sale_lines')
            ->join('sales', 'sales.id', '=', 'sale_lines.sales_id')
            ->where('sales.user_id', $userId)
            ->where('sales.cancelled', 0)
            ->where('sales.type', 0)
            ->where('sales.created_at', '>=', Carbon::today()->subDays(7))
            ->select('sale_lines.item_id', DB::raw('SUM(sale_lines.qty) as qty'))
            ->groupBy('sale_lines.item_id');

        if ($storeId) {
            $recent->where('sales.store_id', $storeId);
        }

        $recentData = $recent->get()->keyBy('item_id');

        $prior = DB::table('sale_lines')
            ->join('sales', 'sales.id', '=', 'sale_lines.sales_id')
            ->where('sales.user_id', $userId)
            ->where('sales.cancelled', 0)
            ->where('sales.type', 0)
            ->whereBetween('sales.created_at', [Carbon::today()->subDays(14), Carbon::today()->subDays(7)])
            ->select('sale_lines.item_id', DB::raw('SUM(sale_lines.qty) as qty'))
            ->groupBy('sale_lines.item_id');

        if ($storeId) {
            $prior->where('sales.store_id', $storeId);
        }

        $priorData = $prior->get()->keyBy('item_id');

        $allItemIds = $recentData->keys()->merge($priorData->keys())->unique();

        return $allItemIds->mapWithKeys(function ($itemId) use ($recentData, $priorData) {
            $recentQty = (float) ($recentData->get($itemId)?->qty ?? 0);
            $priorQty = (float) ($priorData->get($itemId)?->qty ?? 0);
            $ratio = $priorQty > 0 ? $recentQty / $priorQty : ($recentQty > 0 ? 2.0 : 1.0);

            return [$itemId => [
                'recent_qty' => $recentQty,
                'prior_qty' => $priorQty,
                'ratio' => $ratio,
            ]];
        });
    }

    /**
     * Compute the composite sellability score (0-100).
     *
     * @return array{score: float, breakdown: array, predicted_qty: float}
     */
    protected function computeSellabilityScore(
        array $salesAgg,
        Collection $sameDayQtys,
        ?array $trend,
        float $margin,
        Collection $allMargins,
        float $stock,
        float $holidayFactor,
        float $weatherFactor,
    ): array {
        // 1. Volume score (0-30): weighted MA of same-day-of-week qty
        $predictedQty = 0;
        if ($sameDayQtys->isNotEmpty()) {
            $recentDays = $sameDayQtys->take(5)->values();
            $weightedSum = 0;
            $weightTotal = 0;
            foreach ($recentDays as $i => $qty) {
                $weight = $this->weights[$i] ?? 0.05;
                $weightedSum += $qty * $weight;
                $weightTotal += $weight;
            }
            $predictedQty = $weightTotal > 0 ? $weightedSum / $weightTotal : 0;
        } else {
            // Fallback to daily average from 90-day data
            $predictedQty = $salesAgg['days_sold'] > 0 ? $salesAgg['total_qty'] / $salesAgg['days_sold'] : 0;
        }

        // Normalize volume: use log scale to prevent extreme items from dominating
        $volumeScore = $predictedQty > 0 ? min(30, log1p($predictedQty) / log1p(50) * 30) : 0;

        // 2. Trend score (0-20): 7d vs prior 7d momentum
        $trendRatio = $trend['ratio'] ?? 1.0;
        // ratio > 1 = trending up, < 1 = trending down
        $trendScore = min(20, max(0, ($trendRatio - 0.5) / 1.5 * 20));

        // 3. Margin score (0-15): percentile rank among all items
        $marginPercentile = $this->getPercentileRank($margin, $allMargins->toArray());
        $marginScore = $marginPercentile / 100 * 15;

        // 4. Consistency score (0-10): low CoV = reliable seller
        $consistencyScore = 0;
        if ($sameDayQtys->count() >= 2) {
            $values = $sameDayQtys->toArray();
            $stdDev = $this->calculateStdDev($values);
            $mean = array_sum($values) / count($values);
            $cv = $mean > 0 ? $stdDev / $mean : 1;
            // Lower CV = higher consistency score
            $consistencyScore = min(10, max(0, (1 - $cv) * 10));
        } elseif ($salesAgg['days_sold'] >= 7) {
            $consistencyScore = 5; // Some base consistency if sold on many days
        }

        // 5. Stock readiness (0-10): in-stock and adequate supply
        $stockScore = 0;
        if ($stock > 0 && $predictedQty > 0) {
            $daysOfSupply = $stock / $predictedQty;
            if ($daysOfSupply >= 7) {
                $stockScore = 10;
            } elseif ($daysOfSupply >= 3) {
                $stockScore = 7;
            } elseif ($daysOfSupply >= 1) {
                $stockScore = 4;
            } else {
                $stockScore = 2; // Very low but still in stock
            }
        }

        // 6. Seasonal score (0-10): holiday/payday boost
        $seasonalScore = 0;
        if ($holidayFactor > 1.0) {
            $seasonalScore = min(10, ($holidayFactor - 1.0) * 20);
        } elseif ($holidayFactor < 1.0) {
            $seasonalScore = max(0, $holidayFactor * 5);
        } else {
            $seasonalScore = 5; // Neutral
        }

        // 7. Weather score (0-5): good weather = higher
        $weatherScore = min(5, $weatherFactor * 5);

        $totalScore = round(
            $volumeScore + $trendScore + $marginScore + $consistencyScore + $stockScore + $seasonalScore + $weatherScore,
            2
        );

        return [
            'score' => min(100, max(0, $totalScore)),
            'breakdown' => [
                'volume' => round($volumeScore, 2),
                'trend' => round($trendScore, 2),
                'margin' => round($marginScore, 2),
                'consistency' => round($consistencyScore, 2),
                'stock_readiness' => round($stockScore, 2),
                'seasonal' => round($seasonalScore, 2),
                'weather' => round($weatherScore, 2),
            ],
            'predicted_qty' => round($predictedQty, 2),
        ];
    }

    /**
     * Get the percentile rank of a value within a sorted array.
     */
    protected function getPercentileRank(float $value, array $sortedValues): float
    {
        if (empty($sortedValues)) {
            return 50;
        }

        $below = 0;
        foreach ($sortedValues as $v) {
            if ($v < $value) {
                $below++;
            }
        }

        return ($below / count($sortedValues)) * 100;
    }

    /**
     * Calculate standard deviation.
     */
    protected function calculateStdDev(array $values): float
    {
        if (count($values) < 2) {
            return 0;
        }

        $mean = array_sum($values) / count($values);
        $squaredDiffs = array_map(fn ($v) => pow($v - $mean, 2), $values);

        return sqrt(array_sum($squaredDiffs) / count($values));
    }

    /**
     * Get the sales factor for a date (holiday/payday/weekend adjustment).
     */
    protected function getHolidaySalesFactor(Carbon $date): float
    {
        $monthDay = $date->format('m-d');

        // Check fixed holidays
        if (isset($this->fixedHolidays[$monthDay])) {
            return $this->fixedHolidays[$monthDay]['sales_factor'];
        }

        // Check pre-holiday boost periods
        foreach ($this->preHolidayBoosts as $holidayDate => $boost) {
            $holiday = Carbon::createFromFormat('m-d', $holidayDate)->year($date->year);
            $boostStart = $holiday->copy()->subDays($boost['days_before']);

            if ($date->between($boostStart, $holiday->copy()->subDay())) {
                $daysUntilHoliday = $date->diffInDays($holiday);
                $boostProgress = 1 - ($daysUntilHoliday / $boost['days_before']);

                return 1 + (($boost['peak_factor'] - 1) * $boostProgress);
            }
        }

        // Weekend boost
        if ($date->isWeekend()) {
            return 1.1;
        }

        // Payday boost
        if ($this->isPayday($date)) {
            return 1.15;
        }

        return 1.0;
    }

    /**
     * Check if a date falls on a payday period.
     */
    protected function isPayday(Carbon $date): bool
    {
        return in_array($date->day, [15, 16, 28, 29, 30, 31]);
    }

    /**
     * Build human-readable factor tags for an item.
     *
     * @return array<int, string>
     */
    protected function buildFactorTags(array $breakdown, float $holidayFactor, float $weatherFactor, ?array $trend, Carbon $date): array
    {
        $tags = [];

        if ($breakdown['volume'] >= 20) {
            $tags[] = 'high_volume';
        }

        $trendRatio = $trend['ratio'] ?? 1.0;
        if ($trendRatio > 1.2) {
            $tags[] = 'trending_up';
        } elseif ($trendRatio < 0.8) {
            $tags[] = 'trending_down';
        }

        if ($breakdown['margin'] >= 10) {
            $tags[] = 'high_margin';
        }

        if ($breakdown['consistency'] >= 7) {
            $tags[] = 'consistent_seller';
        }

        if ($breakdown['stock_readiness'] <= 2) {
            $tags[] = 'low_stock_risk';
        }

        if ($holidayFactor > 1.0) {
            $monthDay = $date->format('m-d');
            if (isset($this->fixedHolidays[$monthDay])) {
                $tags[] = 'holiday_boost';
            } elseif ($date->isWeekend()) {
                $tags[] = 'weekend_boost';
            }
            if ($this->isPayday($date)) {
                $tags[] = 'payday_boost';
            }
        }

        if ($weatherFactor < 0.7) {
            $tags[] = 'weather_risk';
        }

        return $tags;
    }
}
