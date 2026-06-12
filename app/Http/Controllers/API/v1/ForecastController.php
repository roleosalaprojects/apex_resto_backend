<?php

namespace App\Http\Controllers\API\v1;

use App\Http\Controllers\Controller;
use App\Models\Forecast;
use App\Models\InventoryManagement\ReorderSuggestion;
use App\Services\AiService;
use App\Services\DemandForecastService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ForecastController extends Controller
{
    public function __construct(
        protected DemandForecastService $forecastService,
        protected AiService $ai
    ) {}

    /**
     * Get daily sales forecasts.
     */
    public function dailySales(Request $request): JsonResponse
    {
        $request->validate([
            'days' => 'nullable|integer|min:1|max:30',
            'store_id' => 'nullable|integer|exists:stores,id',
            'refresh' => 'nullable|boolean',
        ]);

        $userId = auth()->user()->user_id;
        $days = $request->input('days', 7);
        $storeId = $request->input('store_id');

        // Check if we should refresh or use cached forecasts
        if ($request->boolean('refresh')) {
            $forecasts = $this->forecastService->forecastDailySales($userId, $days, $storeId);
        } else {
            // Get existing forecasts from today onwards
            $forecasts = Forecast::where('user_id', $userId)
                ->where('forecast_type', 'daily_sales')
                ->where('forecast_date', '>=', now()->toDateString())
                ->when($storeId, fn ($q) => $q->where('store_id', $storeId))
                ->orderBy('forecast_date')
                ->limit($days)
                ->get();

            // If not enough forecasts, generate new ones
            if ($forecasts->count() < $days) {
                $forecasts = $this->forecastService->forecastDailySales($userId, $days, $storeId);
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'forecasts' => $forecasts->map(fn ($f) => [
                    'date' => $f->forecast_date->format('Y-m-d'),
                    'day' => $f->forecast_date->dayName,
                    'predicted_sales' => (float) $f->predicted_value,
                    'confidence' => (float) $f->confidence,
                    'lower_bound' => (float) $f->lower_bound,
                    'upper_bound' => (float) $f->upper_bound,
                    'factors' => $f->factors,
                ]),
                'total_predicted' => $forecasts->sum('predicted_value'),
                'ai_insight' => $forecasts->first()?->ai_insight,
                'ai_available' => $this->ai->isAvailable(),
                'ai_provider' => $this->ai->activeProvider(),
            ],
        ]);
    }

    /**
     * Get reorder suggestions.
     */
    public function reorderSuggestions(Request $request): JsonResponse
    {
        $request->validate([
            'store_id' => 'nullable|integer|exists:stores,id',
            'urgency' => 'nullable|in:critical,high,medium,low',
            'refresh' => 'nullable|boolean',
        ]);

        $userId = auth()->user()->user_id;
        $storeId = $request->input('store_id');

        if ($request->boolean('refresh')) {
            $this->forecastService->generateReorderSuggestions($userId, $storeId);
        }

        $query = ReorderSuggestion::with('item:id,name,barcode', 'store:id,name')
            ->where('user_id', $userId)
            ->where('is_acknowledged', false);

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        if ($request->has('urgency')) {
            $query->where('urgency', $request->input('urgency'));
        }

        $suggestions = $query->orderByRaw("FIELD(urgency, 'critical', 'high', 'medium', 'low')")
            ->get();

        $summary = [
            'critical' => $suggestions->where('urgency', 'critical')->count(),
            'high' => $suggestions->where('urgency', 'high')->count(),
            'medium' => $suggestions->where('urgency', 'medium')->count(),
            'low' => $suggestions->where('urgency', 'low')->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'suggestions' => $suggestions->map(fn ($s) => [
                    'id' => $s->id,
                    'item' => $s->item ? [
                        'id' => $s->item->id,
                        'name' => $s->item->name,
                        'barcode' => $s->item->barcode,
                    ] : null,
                    'store' => $s->store ? [
                        'id' => $s->store->id,
                        'name' => $s->store->name,
                    ] : null,
                    'current_stock' => (float) $s->current_stock,
                    'predicted_demand' => (float) $s->predicted_demand,
                    'suggested_quantity' => (float) $s->suggested_quantity,
                    'days_until_stockout' => $s->days_until_stockout,
                    'urgency' => $s->urgency,
                    'ai_reason' => $s->ai_reason,
                ]),
                'summary' => $summary,
                'ai_available' => $this->ai->isAvailable(),
                'ai_provider' => $this->ai->activeProvider(),
            ],
        ]);
    }

    /**
     * Acknowledge a reorder suggestion.
     */
    public function acknowledgeReorder(Request $request, int $id): JsonResponse
    {
        $suggestion = ReorderSuggestion::where('user_id', auth()->user()->user_id)
            ->findOrFail($id);

        $suggestion->update(['is_acknowledged' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Suggestion acknowledged',
        ]);
    }

    /**
     * Get sales pattern analysis.
     */
    public function patterns(Request $request): JsonResponse
    {
        $request->validate([
            'days' => 'nullable|integer|min:7|max:90',
            'store_id' => 'nullable|integer|exists:stores,id',
        ]);

        $userId = auth()->user()->user_id;
        $days = $request->input('days', 30);
        $storeId = $request->input('store_id');

        $analysis = $this->forecastService->analyzeSalesPatterns($userId, $days, $storeId);

        $dayNames = ['', 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $dayOfWeekFormatted = [];

        foreach ($analysis['patterns']['day_of_week'] ?? [] as $dayNum => $stats) {
            $dayOfWeekFormatted[] = [
                'day' => $dayNames[$dayNum] ?? "Day $dayNum",
                'day_number' => $dayNum,
                'avg_sales' => round($stats['avg_sales'], 2),
                'avg_transactions' => round($stats['avg_transactions'], 1),
                'sample_count' => $stats['count'],
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'trend' => $analysis['patterns']['overall_trend'] ?? 'unknown',
                'average_daily_sales' => round($analysis['patterns']['average_daily_sales'] ?? 0, 2),
                'peak_day' => $analysis['patterns']['peak_day'] ?? null,
                'lowest_day' => $analysis['patterns']['lowest_day'] ?? null,
                'day_of_week' => $dayOfWeekFormatted,
                'ai_insight' => $analysis['insight'],
                'ai_available' => $this->ai->isAvailable(),
                'ai_provider' => $this->ai->activeProvider(),
            ],
        ]);
    }

    /**
     * Get item-level demand forecast.
     */
    public function itemDemand(Request $request, int $itemId): JsonResponse
    {
        $request->validate([
            'days' => 'nullable|integer|min:1|max:30',
            'store_id' => 'nullable|integer|exists:stores,id',
        ]);

        $userId = auth()->user()->user_id;
        $days = $request->input('days', 7);
        $storeId = $request->input('store_id');

        $forecast = $this->forecastService->forecastItemDemand($userId, $itemId, $days, $storeId);

        if (! $forecast) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient sales data for this item',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'item_id' => $itemId,
                'forecast_date' => $forecast->forecast_date->format('Y-m-d'),
                'days_ahead' => $days,
                'predicted_quantity' => (float) $forecast->predicted_value,
                'confidence' => (float) $forecast->confidence,
                'lower_bound' => (float) $forecast->lower_bound,
                'upper_bound' => (float) $forecast->upper_bound,
                'factors' => $forecast->factors,
            ],
        ]);
    }

    /**
     * Check AI service status.
     */
    public function aiStatus(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'available' => $this->ai->isAvailable(),
                'provider' => $this->ai->activeProvider(),
            ],
        ]);
    }
}
