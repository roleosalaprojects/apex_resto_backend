<?php

namespace App\Http\Controllers\API\v1\openclaw;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Services\PeakHoursAnalysisService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    use ApiResponse;

    public function __construct(protected PeakHoursAnalysisService $peakHoursService) {}

    /**
     * Heatmap-style peak hours over the past N days.
     */
    public function peakHours(Request $request): JsonResponse
    {
        $request->validate([
            'days' => 'nullable|integer|min:1|max:365',
            'store_id' => 'nullable|integer|min:1',
        ]);

        $days = (int) $request->input('days', 30);
        $storeId = $request->filled('store_id') ? (int) $request->input('store_id') : null;

        $data = $this->peakHoursService->getHeatmapData(
            (int) auth()->user()->user_id,
            $days,
            $storeId,
        );

        return $this->success($data);
    }
}
