<?php

namespace App\Services;

use App\Models\Forecast;
use App\Models\InventoryManagement\ReorderSuggestion;
use App\Models\Pos\Sale;
use App\Models\Pos\SaleLine;
use App\Models\Products\Item;
use App\Models\Products\ItemStore;
use App\Models\Settings\Store;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DemandForecastService
{
    protected AiService $ai;

    protected WeatherService $weather;

    protected bool $useAI;

    protected bool $useWeather;

    /**
     * Philippine holidays (fixed and movable).
     * Movable holidays like Holy Week are calculated dynamically.
     */
    protected array $fixedHolidays = [
        '01-01' => ['name' => "New Year's Day", 'sales_factor' => 0.3],        // Low sales (closed/recovery)
        '01-02' => ['name' => 'New Year Holiday', 'sales_factor' => 0.6],      // Slow recovery
        '02-25' => ['name' => 'EDSA Revolution', 'sales_factor' => 0.9],       // Regular holiday
        '04-09' => ['name' => 'Araw ng Kagitingan', 'sales_factor' => 0.85],   // Regular holiday
        '05-01' => ['name' => 'Labor Day', 'sales_factor' => 0.85],            // Regular holiday
        '06-12' => ['name' => 'Independence Day', 'sales_factor' => 0.85],     // Regular holiday
        '08-21' => ['name' => 'Ninoy Aquino Day', 'sales_factor' => 0.9],      // Special holiday
        '08-26' => ['name' => 'National Heroes Day', 'sales_factor' => 0.9],   // Regular holiday
        '11-01' => ['name' => "All Saints' Day", 'sales_factor' => 0.7],       // Many travel
        '11-02' => ['name' => "All Souls' Day", 'sales_factor' => 0.8],        // Special holiday
        '11-30' => ['name' => 'Bonifacio Day', 'sales_factor' => 0.85],        // Regular holiday
        '12-08' => ['name' => 'Immaculate Conception', 'sales_factor' => 0.9], // Special holiday
        '12-24' => ['name' => 'Christmas Eve', 'sales_factor' => 1.5],         // High pre-holiday sales
        '12-25' => ['name' => 'Christmas Day', 'sales_factor' => 0.3],         // Closed/low sales
        '12-26' => ['name' => 'Post-Christmas', 'sales_factor' => 0.7],        // Recovery
        '12-30' => ['name' => 'Rizal Day', 'sales_factor' => 1.3],             // Pre-NYE shopping
        '12-31' => ['name' => "New Year's Eve", 'sales_factor' => 1.4],        // High sales
    ];

    /**
     * Pre-holiday shopping boost periods (days before major holidays).
     */
    protected array $preHolidayBoosts = [
        '12-24' => ['days_before' => 14, 'peak_factor' => 1.6], // Christmas shopping
        '12-31' => ['days_before' => 3, 'peak_factor' => 1.3],  // NYE prep
        '11-01' => ['days_before' => 3, 'peak_factor' => 1.2],  // Undas prep
    ];

    public function __construct(AiService $ai, WeatherService $weather)
    {
        $this->ai = $ai;
        $this->weather = $weather;
        $this->useAI = $ai->isAvailable();
        $this->useWeather = $weather->isAvailable();
    }

    /**
     * Generate daily sales forecast for the next N days.
     */
    public function forecastDailySales(int $userId, int $daysAhead = 7, ?int $storeId = null): Collection
    {
        $historicalData = $this->getDailySalesHistory($userId, 90, $storeId);

        if ($historicalData->isEmpty()) {
            return collect();
        }

        // Get last year's data for the same forecast period
        $forecastStart = Carbon::today()->addDay();
        $forecastEnd = Carbon::today()->addDays($daysAhead);
        $lastYearData = $this->getLastYearSalesHistory($userId, $forecastStart, $forecastEnd, $storeId);

        // Also get surrounding weeks from last year for better pattern matching
        $extendedLastYearStart = $forecastStart->copy()->subYear()->subWeeks(2);
        $extendedLastYearEnd = $forecastEnd->copy()->subYear()->addWeeks(2);
        $extendedLastYearData = $this->getLastYearSalesHistory(
            $userId,
            $extendedLastYearStart->copy()->addYear(),
            $extendedLastYearEnd->copy()->addYear(),
            $storeId
        );

        // Look up store coordinates for weather integration
        $storeLatLng = null;
        if ($this->useWeather && $storeId) {
            $store = Store::find($storeId);
            if ($store && $store->latitude && $store->longitude) {
                $storeLatLng = [
                    'lat' => (float) $store->latitude,
                    'lng' => (float) $store->longitude,
                ];
            }
        }

        $forecasts = collect();

        for ($i = 1; $i <= $daysAhead; $i++) {
            $forecastDate = Carbon::today()->addDays($i);
            $prediction = $this->predictDailySales($historicalData, $forecastDate, $extendedLastYearData, $storeLatLng);

            $forecastData = [
                'predicted_value' => $prediction['value'],
                'confidence' => $prediction['confidence'],
                'lower_bound' => $prediction['lower_bound'],
                'upper_bound' => $prediction['upper_bound'],
                'factors' => $prediction['factors'],
                'historical_data' => $historicalData->take(30)->toArray(),
            ];

            if (isset($prediction['weather_data'])) {
                $forecastData['weather_data'] = $prediction['weather_data'];
            }

            $forecast = Forecast::updateOrCreate(
                [
                    'user_id' => $userId,
                    'store_id' => $storeId,
                    'forecast_date' => $forecastDate,
                    'forecast_type' => 'daily_sales',
                ],
                $forecastData
            );

            $forecasts->push($forecast);
        }

        // Generate AI insight for the forecast period
        if ($this->useAI && $forecasts->isNotEmpty()) {
            $insight = $this->generateForecastInsight($historicalData, $forecasts);
            $forecasts->first()->update(['ai_insight' => $insight]);
        }

        return $forecasts;
    }

    /**
     * Generate item-level demand forecast.
     */
    public function forecastItemDemand(int $userId, int $itemId, int $daysAhead = 7, ?int $storeId = null): ?Forecast
    {
        $historicalData = $this->getItemSalesHistory($userId, $itemId, 90, $storeId);

        if ($historicalData->isEmpty()) {
            return null;
        }

        $forecastDate = Carbon::today()->addDays($daysAhead);
        $prediction = $this->predictItemDemand($historicalData, $daysAhead);

        return Forecast::updateOrCreate(
            [
                'user_id' => $userId,
                'item_id' => $itemId,
                'store_id' => $storeId,
                'forecast_date' => $forecastDate,
                'forecast_type' => 'item_demand',
            ],
            [
                'predicted_value' => $prediction['value'],
                'confidence' => $prediction['confidence'],
                'lower_bound' => $prediction['lower_bound'],
                'upper_bound' => $prediction['upper_bound'],
                'factors' => $prediction['factors'],
                'historical_data' => $historicalData->take(30)->toArray(),
            ]
        );
    }

    /**
     * Generate reorder suggestions for items running low.
     */
    public function generateReorderSuggestions(int $userId, ?int $storeId = null): Collection
    {
        $suggestions = collect();

        // Get items with stock (join through items to get user_id)
        $itemsQuery = ItemStore::join('items', 'items.id', '=', 'item_stores.item_id')
            ->where('items.user_id', $userId)
            ->select('item_stores.*', 'items.name as item_name');

        if ($storeId) {
            $itemsQuery->where('item_stores.store_id', $storeId);
        }

        $items = $itemsQuery->get();

        foreach ($items as $itemStore) {
            $salesHistory = $this->getItemSalesHistory($userId, $itemStore->item_id, 30, $storeId);

            if ($salesHistory->isEmpty()) {
                continue;
            }

            $avgDailySales = $salesHistory->avg('qty') ?? 0;

            if ($avgDailySales <= 0) {
                continue;
            }

            $predictedDemand = $avgDailySales * 7; // 7-day forecast
            $daysUntilStockout = $itemStore->stock > 0 ? floor($itemStore->stock / $avgDailySales) : 0;

            $urgency = $this->calculateUrgency($daysUntilStockout, $itemStore->stock);

            if ($urgency === 'none') {
                continue;
            }

            // Calculate suggested quantity (2 weeks supply + safety stock)
            $suggestedQty = max(0, ($avgDailySales * 14) - $itemStore->stock + ($avgDailySales * 3));

            $aiReason = null;
            if ($this->useAI && in_array($urgency, ['critical', 'high'])) {
                $aiReason = $this->ai->generateReorderReason([
                    'item_name' => $itemStore->item_name,
                    'current_stock' => $itemStore->stock,
                    'avg_daily_sales' => round($avgDailySales, 2),
                    'predicted_demand' => round($predictedDemand, 2),
                    'days_until_stockout' => $daysUntilStockout,
                    'suggested_quantity' => round($suggestedQty, 2),
                ]);
            }

            $suggestion = ReorderSuggestion::updateOrCreate(
                [
                    'user_id' => $userId,
                    'item_id' => $itemStore->item_id,
                    'store_id' => $itemStore->store_id,
                ],
                [
                    'current_stock' => $itemStore->stock,
                    'predicted_demand' => $predictedDemand,
                    'suggested_quantity' => $suggestedQty,
                    'days_until_stockout' => $daysUntilStockout,
                    'urgency' => $urgency,
                    'ai_reason' => $aiReason,
                    'is_acknowledged' => false,
                ]
            );

            $suggestions->push($suggestion);
        }

        // FCM dispatch intentionally NOT here. This method is called from
        // ForecastController::reorderSuggestions when the user taps Refresh,
        // and firing a notification on a read/refresh path was a source of
        // notification spam. The scheduled `notifications:fire-alerts`
        // command is the single trigger surface for reorder_alert pushes.

        return $suggestions->sortBy(function ($s) {
            $urgencyOrder = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];

            return $urgencyOrder[$s->urgency] ?? 4;
        })->values();
    }

    /**
     * Get pattern analysis for sales.
     */
    public function analyzeSalesPatterns(int $userId, int $days = 30, ?int $storeId = null): array
    {
        $dailySales = $this->getDailySalesHistory($userId, $days, $storeId);

        if ($dailySales->isEmpty()) {
            return ['patterns' => [], 'insight' => null];
        }

        // Calculate day-of-week patterns
        $dayOfWeekStats = $dailySales->groupBy(function ($item) {
            return Carbon::parse($item['date'])->dayOfWeek;
        })->map(function ($group) {
            return [
                'avg_sales' => $group->avg('total'),
                'avg_transactions' => $group->avg('transactions'),
                'count' => $group->count(),
            ];
        });

        $patterns = [
            'day_of_week' => $dayOfWeekStats->toArray(),
            'overall_trend' => $this->calculateTrend($dailySales),
            'average_daily_sales' => $dailySales->avg('total'),
            'peak_day' => $dailySales->sortByDesc('total')->first(),
            'lowest_day' => $dailySales->sortBy('total')->first(),
        ];

        $insight = null;
        if ($this->useAI) {
            $insight = $this->ai->detectPatterns($dailySales->toArray());
        }

        return [
            'patterns' => $patterns,
            'insight' => $insight,
        ];
    }

    /**
     * Get daily sales history.
     */
    protected function getDailySalesHistory(int $userId, int $days, ?int $storeId = null): Collection
    {
        $query = Sale::where('sales.user_id', $userId)
            ->where('sales.cancelled', 0)
            ->where('sales.created_at', '>=', Carbon::today()->subDays($days))
            ->select(
                DB::raw('DATE(sales.created_at) as date'),
                DB::raw('COUNT(*) as transactions'),
                DB::raw('SUM(sales.total) as total'),
                DB::raw('DAYOFWEEK(sales.created_at) as day_of_week')
            )
            ->groupBy(DB::raw('DATE(sales.created_at)'), DB::raw('DAYOFWEEK(sales.created_at)'))
            ->orderBy('date', 'desc');

        if ($storeId) {
            $query->where('sales.store_id', $storeId);
        }

        return $query->get()->map(function ($row) {
            return [
                'date' => $row->date,
                'transactions' => (int) $row->transactions,
                'total' => (float) $row->total,
                'day_of_week' => (int) $row->day_of_week,
            ];
        });
    }

    /**
     * Get item sales history.
     */
    protected function getItemSalesHistory(int $userId, int $itemId, int $days, ?int $storeId = null): Collection
    {
        $query = SaleLine::join('sales', 'sales.id', '=', 'sale_lines.sales_id')
            ->where('sales.user_id', $userId)
            ->where('sales.cancelled', 0)
            ->where('sale_lines.item_id', $itemId)
            ->where('sales.created_at', '>=', Carbon::today()->subDays($days))
            ->select(
                DB::raw('DATE(sales.created_at) as date'),
                DB::raw('SUM(sale_lines.qty) as qty'),
                DB::raw('SUM(sale_lines.sub_total) as total')
            )
            ->groupBy(DB::raw('DATE(sales.created_at)'))
            ->orderBy('date', 'desc');

        if ($storeId) {
            $query->where('sales.store_id', $storeId);
        }

        return $query->get()->map(function ($row) {
            return [
                'date' => $row->date,
                'qty' => (float) $row->qty,
                'total' => (float) $row->total,
            ];
        });
    }

    /**
     * Predict daily sales using weighted moving average with YoY and holiday adjustments.
     */
    protected function predictDailySales(Collection $historicalData, Carbon $forecastDate, ?Collection $lastYearData = null, ?array $storeLatLng = null): array
    {
        $dayOfWeek = $forecastDate->dayOfWeek + 1; // MySQL DAYOFWEEK is 1-7

        // Get same day-of-week data from current year
        $sameDayData = $historicalData->filter(fn ($d) => $d['day_of_week'] === $dayOfWeek);

        // Calculate weighted moving average (recent data weighted more)
        $weights = [0.35, 0.25, 0.20, 0.12, 0.08]; // Last 5 same-day entries
        $recentSameDays = $sameDayData->take(5)->values();

        $weightedSum = 0;
        $weightTotal = 0;

        foreach ($recentSameDays as $i => $data) {
            $weight = $weights[$i] ?? 0.05;
            $weightedSum += $data['total'] * $weight;
            $weightTotal += $weight;
        }

        $currentYearPrediction = $weightTotal > 0 ? $weightedSum / $weightTotal : $historicalData->avg('total');

        // Blend with last year's data if available (30% weight to last year)
        $lastYearPrediction = null;
        $yoyGrowth = null;
        if ($lastYearData && $lastYearData->isNotEmpty()) {
            $lastYearSameDayData = $lastYearData->filter(fn ($d) => $d['day_of_week'] === $dayOfWeek);
            if ($lastYearSameDayData->isNotEmpty()) {
                $lastYearPrediction = $lastYearSameDayData->avg('total');

                // Calculate YoY growth rate
                if ($lastYearPrediction > 0 && $sameDayData->isNotEmpty()) {
                    $currentAvg = $sameDayData->avg('total');
                    $yoyGrowth = (($currentAvg - $lastYearPrediction) / $lastYearPrediction) * 100;

                    // Apply growth rate to last year baseline
                    $adjustedLastYear = $lastYearPrediction * (1 + ($yoyGrowth / 100));

                    // Blend: 70% current year trend, 30% adjusted last year
                    $currentYearPrediction = ($currentYearPrediction * 0.7) + ($adjustedLastYear * 0.3);
                }
            }
        }

        // Apply holiday adjustment
        $holidaySalesFactor = $this->getHolidaySalesFactor($forecastDate);
        $prediction = $currentYearPrediction * $holidaySalesFactor;

        // Get holiday info for display
        $holidayInfo = $this->getHolidayInfo($forecastDate);

        // Apply weather adjustment
        $weatherSalesFactor = 1.0;
        $weatherInfo = null;
        $weatherData = null;
        if ($storeLatLng) {
            $weatherSalesFactor = $this->weather->getWeatherSalesFactor(
                $storeLatLng['lat'],
                $storeLatLng['lng'],
                $forecastDate
            );
            $prediction *= $weatherSalesFactor;

            $weatherInfo = $this->weather->getWeatherInfo(
                $storeLatLng['lat'],
                $storeLatLng['lng'],
                $forecastDate
            );
            $weatherData = $weatherInfo;
        }

        // Calculate enhanced confidence
        $confidence = $this->calculateEnhancedConfidence(
            $sameDayData,
            $historicalData,
            $lastYearData,
            $forecastDate,
            $weatherSalesFactor
        );

        // Calculate bounds (tighter bounds with better data)
        $stdDev = $this->calculateStdDev($sameDayData->pluck('total')->toArray());
        $marginMultiplier = $confidence > 75 ? 1.0 : ($confidence > 60 ? 1.3 : 1.5);
        $margin = $stdDev * $marginMultiplier;

        $factors = [
            'day_of_week' => $forecastDate->dayName,
            'based_on_samples' => $sameDayData->count(),
            'overall_avg' => round($historicalData->avg('total'), 2),
        ];

        // Add holiday and YoY info to factors
        if ($holidayInfo) {
            $factors['holiday'] = $holidayInfo['name'];
            $factors['holiday_factor'] = $holidaySalesFactor;
        }
        if ($yoyGrowth !== null) {
            $factors['yoy_growth'] = round($yoyGrowth, 1).'%';
        }
        if ($lastYearPrediction !== null) {
            $factors['last_year_avg'] = round($lastYearPrediction, 2);
        }
        if ($weatherInfo) {
            $factors['weather'] = $weatherInfo['condition'];
            $factors['weather_factor'] = $weatherSalesFactor;
        }

        $result = [
            'value' => round($prediction, 2),
            'confidence' => round($confidence, 2),
            'lower_bound' => round(max(0, $prediction - $margin), 2),
            'upper_bound' => round($prediction + $margin, 2),
            'factors' => $factors,
        ];

        if ($weatherData) {
            $result['weather_data'] = $weatherData;
        }

        return $result;
    }

    /**
     * Predict item demand with enhanced confidence.
     */
    protected function predictItemDemand(Collection $historicalData, int $daysAhead): array
    {
        $avgDaily = $historicalData->avg('qty') ?? 0;
        $prediction = $avgDaily * $daysAhead;

        // Calculate enhanced confidence for item demand
        $baseConfidence = 50.0;
        $confidenceBoosts = [];

        // Factor 1: Sample size
        $sampleCount = $historicalData->count();
        if ($sampleCount >= 21) {
            $confidenceBoosts['sample_size'] = 20;
        } elseif ($sampleCount >= 14) {
            $confidenceBoosts['sample_size'] = 15;
        } elseif ($sampleCount >= 7) {
            $confidenceBoosts['sample_size'] = 10;
        } else {
            $confidenceBoosts['sample_size'] = 5;
        }

        // Factor 2: Data consistency
        $values = $historicalData->pluck('qty')->toArray();
        $stdDev = $this->calculateStdDev($values);
        $mean = $avgDaily ?: 1;
        $cv = $stdDev / $mean;

        if ($cv < 0.2) {
            $confidenceBoosts['consistency'] = 20;
        } elseif ($cv < 0.35) {
            $confidenceBoosts['consistency'] = 15;
        } elseif ($cv < 0.5) {
            $confidenceBoosts['consistency'] = 10;
        } else {
            $confidenceBoosts['consistency'] = 5;
        }

        // Factor 3: Forecast proximity
        if ($daysAhead <= 3) {
            $confidenceBoosts['proximity'] = 10;
        } elseif ($daysAhead <= 7) {
            $confidenceBoosts['proximity'] = 5;
        } else {
            $confidenceBoosts['proximity'] = 0;
        }

        $confidence = min(95, max(35, $baseConfidence + array_sum($confidenceBoosts)));

        // Tighter bounds with higher confidence
        $marginMultiplier = $confidence > 75 ? 0.3 : ($confidence > 60 ? 0.4 : 0.5);
        $margin = $stdDev * $daysAhead * $marginMultiplier;

        return [
            'value' => round($prediction, 2),
            'confidence' => round($confidence, 2),
            'lower_bound' => round(max(0, $prediction - $margin), 2),
            'upper_bound' => round($prediction + $margin, 2),
            'factors' => [
                'avg_daily_sales' => round($avgDaily, 2),
                'days_ahead' => $daysAhead,
                'data_points' => $historicalData->count(),
            ],
        ];
    }

    /**
     * Calculate trend direction.
     */
    protected function calculateTrend(Collection $data): string
    {
        if ($data->count() < 7) {
            return 'insufficient_data';
        }

        $recent = $data->take(7)->avg('total');
        $older = $data->skip(7)->take(7)->avg('total');

        if ($older == 0) {
            return 'stable';
        }

        $change = (($recent - $older) / $older) * 100;

        if ($change > 10) {
            return 'increasing';
        }
        if ($change < -10) {
            return 'decreasing';
        }

        return 'stable';
    }

    /**
     * Calculate urgency level.
     */
    protected function calculateUrgency(int $daysUntilStockout, float $currentStock): string
    {
        if ($currentStock <= 0 || $daysUntilStockout <= 0) {
            return 'critical';
        }

        if ($daysUntilStockout <= 3) {
            return 'critical';
        }
        if ($daysUntilStockout <= 7) {
            return 'high';
        }
        if ($daysUntilStockout <= 14) {
            return 'medium';
        }
        if ($daysUntilStockout <= 21) {
            return 'low';
        }

        return 'none';
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
     * Generate AI insight for forecasts.
     */
    protected function generateForecastInsight(Collection $historicalData, Collection $forecasts): ?string
    {
        $salesSummary = [
            'last_7_days_avg' => $historicalData->take(7)->avg('total'),
            'last_30_days_avg' => $historicalData->take(30)->avg('total'),
            'trend' => $this->calculateTrend($historicalData),
            'forecasted_total' => $forecasts->sum('predicted_value'),
            'forecast_days' => $forecasts->count(),
        ];

        $context = [];

        // Collect weather data from forecasts for AI context
        $weatherForecasts = $forecasts->filter(fn ($f) => ! empty($f->weather_data))->map(fn ($f) => [
            'date' => $f->forecast_date->format('Y-m-d'),
            'weather' => $f->weather_data,
        ])->values()->toArray();

        if (! empty($weatherForecasts)) {
            $context['weather_forecast'] = $weatherForecasts;
        }

        return $this->ai->generateSalesInsight($salesSummary, $context);
    }

    /**
     * Get sales data from the same period last year.
     */
    protected function getLastYearSalesHistory(int $userId, Carbon $startDate, Carbon $endDate, ?int $storeId = null): Collection
    {
        $lastYearStart = $startDate->copy()->subYear();
        $lastYearEnd = $endDate->copy()->subYear();

        $query = Sale::where('sales.user_id', $userId)
            ->where('sales.cancelled', 0)
            ->whereBetween('sales.created_at', [$lastYearStart, $lastYearEnd])
            ->select(
                DB::raw('DATE(sales.created_at) as date'),
                DB::raw('COUNT(*) as transactions'),
                DB::raw('SUM(sales.total) as total'),
                DB::raw('DAYOFWEEK(sales.created_at) as day_of_week')
            )
            ->groupBy(DB::raw('DATE(sales.created_at)'), DB::raw('DAYOFWEEK(sales.created_at)'))
            ->orderBy('date', 'desc');

        if ($storeId) {
            $query->where('sales.store_id', $storeId);
        }

        return $query->get()->map(function ($row) {
            return [
                'date' => $row->date,
                'transactions' => (int) $row->transactions,
                'total' => (float) $row->total,
                'day_of_week' => (int) $row->day_of_week,
            ];
        });
    }

    /**
     * Get holiday info for a specific date.
     */
    protected function getHolidayInfo(Carbon $date): ?array
    {
        $monthDay = $date->format('m-d');

        // Check fixed holidays
        if (isset($this->fixedHolidays[$monthDay])) {
            return $this->fixedHolidays[$monthDay];
        }

        // Check for Holy Week (movable - based on Easter)
        $holyWeekInfo = $this->getHolyWeekInfo($date);
        if ($holyWeekInfo) {
            return $holyWeekInfo;
        }

        // Check Eid (approximate - varies by lunar calendar)
        $eidInfo = $this->getEidInfo($date);
        if ($eidInfo) {
            return $eidInfo;
        }

        return null;
    }

    /**
     * Calculate Holy Week dates (based on Easter).
     * Uses a pure PHP algorithm if ext-calendar is not available.
     */
    protected function getHolyWeekInfo(Carbon $date): ?array
    {
        $year = $date->year;
        $easter = $this->calculateEasterDate($year);

        if (! $easter) {
            return null;
        }

        // Maundy Thursday (3 days before Easter)
        $maundyThursday = $easter->copy()->subDays(3);
        // Good Friday (2 days before Easter)
        $goodFriday = $easter->copy()->subDays(2);
        // Black Saturday (1 day before Easter)
        $blackSaturday = $easter->copy()->subDays(1);

        if ($date->isSameDay($maundyThursday)) {
            return ['name' => 'Maundy Thursday', 'sales_factor' => 0.5];
        }
        if ($date->isSameDay($goodFriday)) {
            return ['name' => 'Good Friday', 'sales_factor' => 0.3];
        }
        if ($date->isSameDay($blackSaturday)) {
            return ['name' => 'Black Saturday', 'sales_factor' => 0.4];
        }
        if ($date->isSameDay($easter)) {
            return ['name' => 'Easter Sunday', 'sales_factor' => 0.6];
        }

        // Pre-Holy Week boost (week before)
        $palmSunday = $easter->copy()->subWeek();
        if ($date->between($palmSunday, $maundyThursday->copy()->subDay())) {
            return ['name' => 'Pre-Holy Week', 'sales_factor' => 1.2];
        }

        return null;
    }

    /**
     * Calculate Easter date using Anonymous Gregorian algorithm.
     * Works without ext-calendar extension.
     */
    protected function calculateEasterDate(int $year): ?Carbon
    {
        try {
            // Use built-in function if available
            if (function_exists('easter_date')) {
                return Carbon::createFromTimestamp(easter_date($year));
            }

            // Anonymous Gregorian algorithm (Meeus/Jones/Butcher)
            $a = $year % 19;
            $b = intdiv($year, 100);
            $c = $year % 100;
            $d = intdiv($b, 4);
            $e = $b % 4;
            $f = intdiv($b + 8, 25);
            $g = intdiv($b - $f + 1, 3);
            $h = (19 * $a + $b - $d - $g + 15) % 30;
            $i = intdiv($c, 4);
            $k = $c % 4;
            $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
            $m = intdiv($a + 11 * $h + 22 * $l, 451);
            $month = intdiv($h + $l - 7 * $m + 114, 31);
            $day = (($h + $l - 7 * $m + 114) % 31) + 1;

            return Carbon::create($year, $month, $day);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get approximate Eid dates (simplified - actual dates vary).
     */
    protected function getEidInfo(Carbon $date): ?array
    {
        // Eid dates change each year; this is a simplified approximation
        // In production, you'd use a lunar calendar library
        $eidAlFitr2026 = Carbon::create(2026, 3, 30); // Approximate
        $eidAlAdha2026 = Carbon::create(2026, 6, 6);  // Approximate

        if ($date->isSameDay($eidAlFitr2026) || $date->isSameDay($eidAlAdha2026)) {
            return ['name' => 'Eid Holiday', 'sales_factor' => 0.85];
        }

        return null;
    }

    /**
     * Get the sales factor for a date (holiday adjustment).
     */
    protected function getHolidaySalesFactor(Carbon $date): float
    {
        $holidayInfo = $this->getHolidayInfo($date);
        if ($holidayInfo) {
            return $holidayInfo['sales_factor'];
        }

        // Check pre-holiday boost periods
        foreach ($this->preHolidayBoosts as $holidayDate => $boost) {
            $holiday = Carbon::createFromFormat('m-d', $holidayDate)->year($date->year);
            $boostStart = $holiday->copy()->subDays($boost['days_before']);

            if ($date->between($boostStart, $holiday->copy()->subDay())) {
                // Gradual increase as holiday approaches
                $daysUntilHoliday = $date->diffInDays($holiday);
                $boostProgress = 1 - ($daysUntilHoliday / $boost['days_before']);

                return 1 + (($boost['peak_factor'] - 1) * $boostProgress);
            }
        }

        // Check if it's a weekend (typically higher sales)
        if ($date->isWeekend()) {
            return 1.1; // 10% boost for weekends
        }

        // Check if it's a payday period (15th and end of month)
        $dayOfMonth = $date->day;
        if (in_array($dayOfMonth, [15, 16, 28, 29, 30, 31])) {
            return 1.15; // 15% boost for payday periods
        }

        return 1.0;
    }

    /**
     * Calculate year-over-year correlation for confidence adjustment.
     */
    protected function calculateYoYCorrelation(Collection $currentYearData, Collection $lastYearData): float
    {
        if ($currentYearData->count() < 7 || $lastYearData->count() < 7) {
            return 0.5; // Neutral if insufficient data
        }

        // Compare day-of-week patterns
        $currentByDow = $currentYearData->groupBy('day_of_week')->map(fn ($g) => $g->avg('total'));
        $lastByDow = $lastYearData->groupBy('day_of_week')->map(fn ($g) => $g->avg('total'));

        $correlationSum = 0;
        $matchCount = 0;

        foreach ($currentByDow as $dow => $currentAvg) {
            if (isset($lastByDow[$dow]) && $lastByDow[$dow] > 0) {
                $ratio = min($currentAvg, $lastByDow[$dow]) / max($currentAvg, $lastByDow[$dow]);
                $correlationSum += $ratio;
                $matchCount++;
            }
        }

        return $matchCount > 0 ? $correlationSum / $matchCount : 0.5;
    }

    /**
     * Calculate enhanced confidence score with multiple factors.
     */
    protected function calculateEnhancedConfidence(
        Collection $sameDayData,
        Collection $allData,
        ?Collection $lastYearData,
        Carbon $forecastDate,
        float $weatherSalesFactor = 1.0
    ): float {
        $baseConfidence = 50.0;
        $confidenceBoosts = [];

        // Factor 1: Sample size (more samples = higher confidence)
        $sampleCount = $sameDayData->count();
        if ($sampleCount >= 8) {
            $confidenceBoosts['sample_size'] = 15;
        } elseif ($sampleCount >= 4) {
            $confidenceBoosts['sample_size'] = 10;
        } elseif ($sampleCount >= 2) {
            $confidenceBoosts['sample_size'] = 5;
        } else {
            $confidenceBoosts['sample_size'] = 0;
        }

        // Factor 2: Data consistency (lower CV = higher confidence)
        if ($sameDayData->isNotEmpty()) {
            $values = $sameDayData->pluck('total')->toArray();
            $stdDev = $this->calculateStdDev($values);
            $mean = $sameDayData->avg('total') ?: 1;
            $cv = $stdDev / $mean;

            // CV < 0.2 is very consistent, > 0.5 is highly variable
            if ($cv < 0.15) {
                $confidenceBoosts['consistency'] = 20;
            } elseif ($cv < 0.25) {
                $confidenceBoosts['consistency'] = 15;
            } elseif ($cv < 0.35) {
                $confidenceBoosts['consistency'] = 10;
            } elseif ($cv < 0.5) {
                $confidenceBoosts['consistency'] = 5;
            } else {
                $confidenceBoosts['consistency'] = 0;
            }
        }

        // Factor 3: Year-over-year correlation
        if ($lastYearData && $lastYearData->isNotEmpty()) {
            $yoyCorrelation = $this->calculateYoYCorrelation($allData, $lastYearData);
            $confidenceBoosts['yoy_correlation'] = round($yoyCorrelation * 15);
        }

        // Factor 4: Holiday predictability
        $holidayInfo = $this->getHolidayInfo($forecastDate);
        if ($holidayInfo) {
            // Holidays are more predictable (known patterns)
            $confidenceBoosts['holiday'] = 10;
        }

        // Factor 5: Forecast proximity (nearer dates are more accurate)
        $daysAhead = Carbon::today()->diffInDays($forecastDate);
        if ($daysAhead <= 2) {
            $confidenceBoosts['proximity'] = 10;
        } elseif ($daysAhead <= 7) {
            $confidenceBoosts['proximity'] = 5;
        } else {
            $confidenceBoosts['proximity'] = 0;
        }

        // Factor 6: Weather predictability
        if ($weatherSalesFactor < 0.5) {
            // Severe weather is highly predictable (stores close / foot traffic drops)
            $confidenceBoosts['weather'] = 8;
        } elseif ($weatherSalesFactor < 0.8) {
            // Moderate weather still provides useful signal
            $confidenceBoosts['weather'] = 3;
        }

        $totalConfidence = $baseConfidence + array_sum($confidenceBoosts);

        return min(95, max(30, $totalConfidence)); // Cap between 30-95%
    }
}
