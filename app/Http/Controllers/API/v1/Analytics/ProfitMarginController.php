<?php

namespace App\Http\Controllers\API\v1\Analytics;

use App\Http\Controllers\Controller;
use App\Services\ProfitAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfitMarginController extends Controller
{
    public function __construct(protected ProfitAnalysisService $profitService) {}

    /**
     * GET /api/v1/analytics/profit-margins
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'period' => 'nullable|integer|min:1|max:365',
            'store_id' => 'nullable|integer|exists:stores,id',
            'sort' => 'nullable|string|in:margin_change,margin_change_desc,margin_pct,total_profit,total_sold',
        ]);

        $userId = auth()->user()->user_id;
        $period = $request->input('period', 30);
        $storeId = $request->input('store_id');
        $sort = $request->input('sort', 'margin_change');

        return response()->json(
            $this->profitService->getProfitMargins($userId, $period, $storeId, $sort)
        );
    }

    /**
     * GET /api/v1/analytics/profit-margins/{item}/trend
     */
    public function trend(Request $request, int $item): JsonResponse
    {
        $request->validate([
            'days' => 'nullable|integer|min:1|max:365',
            'store_id' => 'nullable|integer|exists:stores,id',
        ]);

        $userId = auth()->user()->user_id;
        $days = $request->input('days', 90);
        $storeId = $request->input('store_id');

        return response()->json(
            $this->profitService->getMarginTrend($userId, $item, $days, $storeId)
        );
    }

    /**
     * GET /api/v1/analytics/margin-alerts
     */
    public function marginAlerts(Request $request): JsonResponse
    {
        $request->validate([
            'store_id' => 'nullable|integer|exists:stores,id',
        ]);

        $userId = auth()->user()->user_id;
        $storeId = $request->input('store_id');

        return response()->json(
            $this->profitService->getMarginAlerts($userId, $storeId)
        );
    }
}
