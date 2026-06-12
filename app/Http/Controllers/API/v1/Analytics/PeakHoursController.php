<?php

namespace App\Http\Controllers\API\v1\Analytics;

use App\Http\Controllers\Controller;
use App\Services\PeakHoursAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PeakHoursController extends Controller
{
    public function __construct(protected PeakHoursAnalysisService $peakHoursService) {}

    /**
     * GET /api/v1/analytics/peak-hours
     */
    public function peakHours(Request $request): JsonResponse
    {
        $request->validate([
            'days' => 'nullable|integer|min:1|max:365',
            'store_id' => 'nullable|integer|exists:stores,id',
        ]);

        $userId = auth()->user()->user_id;
        $days = $request->input('days', 30);
        $storeId = $request->input('store_id');

        $data = $this->peakHoursService->getHeatmapData($userId, $days, $storeId);

        return response()->json($data);
    }

    /**
     * GET /api/v1/analytics/hourly-breakdown
     */
    public function hourlyBreakdown(Request $request): JsonResponse
    {
        $request->validate([
            'date' => 'required|date',
            'store_id' => 'nullable|integer|exists:stores,id',
        ]);

        $userId = auth()->user()->user_id;
        $date = $request->input('date');
        $storeId = $request->input('store_id');

        $data = $this->peakHoursService->getHourlyBreakdown($userId, $date, $storeId);

        return response()->json($data);
    }
}
