<?php

namespace App\Http\Controllers\API\v1\Analytics;

use App\Http\Controllers\Controller;
use App\Services\ItemInsightsService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItemInsightsController extends Controller
{
    public function __construct(protected ItemInsightsService $insightsService) {}

    /**
     * GET /api/v1/analytics/item-insights
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'date' => 'nullable|date',
            'store_id' => 'nullable|integer|exists:stores,id',
            'limit' => 'nullable|integer|min:1|max:100',
            'refresh' => 'nullable|boolean',
        ]);

        $userId = auth()->user()->user_id;
        $date = Carbon::parse($request->input('date', Carbon::today()->toDateString()));
        $storeId = $request->input('store_id');
        $limit = $request->input('limit', 100);
        $refresh = $request->boolean('refresh');

        $insights = $this->insightsService->getTopInsights($userId, $date, $storeId, $refresh);

        // Limit if needed (insights may already be limited by generation)
        if ($insights->count() > $limit) {
            $insights = $insights->take($limit);
        }

        $items = $insights->map(fn ($insight) => [
            'rank' => $insight->rank,
            'item_id' => $insight->item_id,
            'item_name' => $insight->item?->name ?? 'Unknown',
            'category' => $insight->category_name,
            'sellability_score' => $insight->sellability_score,
            'score_breakdown' => $insight->score_breakdown,
            'predicted_qty' => $insight->predicted_qty,
            'current_stock' => $insight->current_stock,
            'profit_margin' => $insight->profit_margin,
            'ai_insight' => $insight->ai_insight,
            'factors' => $insight->factors,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'date' => $date->toDateString(),
                'day' => $date->format('l'),
                'generated_at' => $insights->first()?->created_at?->toIso8601String(),
                'items' => $items->values(),
                'summary' => [
                    'total_items' => $items->count(),
                    'avg_score' => round($insights->avg('sellability_score'), 1),
                    'categories_count' => $insights->pluck('category_name')->filter()->unique()->count(),
                    'low_stock_count' => $insights->filter(fn ($i) => in_array('low_stock_risk', $i->factors ?? []))->count(),
                ],
            ],
        ]);
    }
}
