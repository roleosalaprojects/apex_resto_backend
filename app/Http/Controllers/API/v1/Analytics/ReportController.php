<?php

namespace App\Http\Controllers\API\v1\Analytics;

use App\Http\Controllers\Controller;
use App\Models\Reports\ReportRecipient;
use App\Services\ReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(protected ReportService $reportService) {}

    /**
     * GET /api/v1/reports/sales-summary
     */
    public function salesSummary(Request $request): JsonResponse
    {
        $request->validate([
            'period' => 'nullable|string|in:daily,weekly,monthly',
            'date' => 'nullable|date',
            'store_id' => 'nullable|integer|exists:stores,id',
        ]);

        $userId = auth()->user()->user_id;
        $period = $request->input('period', 'daily');
        $date = $request->input('date');
        $storeId = $request->input('store_id');

        return response()->json(
            $this->reportService->getSalesSummary($userId, $period, $date, $storeId)
        );
    }

    /**
     * GET /api/v1/reports/recipients
     */
    public function recipients(): JsonResponse
    {
        $userId = auth()->user()->user_id;
        $recipients = ReportRecipient::where('user_id', $userId)
            ->select('id', 'email', 'report_type', 'is_active')
            ->get();

        return response()->json(['data' => $recipients]);
    }

    /**
     * POST /api/v1/reports/recipients
     */
    public function storeRecipient(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email',
            'report_type' => 'required|in:daily,weekly,both',
            'is_active' => 'nullable|boolean',
        ]);

        $userId = auth()->user()->user_id;

        $recipient = ReportRecipient::create([
            'user_id' => $userId,
            'email' => $request->input('email'),
            'report_type' => $request->input('report_type'),
            'is_active' => $request->input('is_active', true),
        ]);

        return response()->json($recipient, 201);
    }

    /**
     * DELETE /api/v1/reports/recipients/{reportRecipient}
     */
    public function destroyRecipient(ReportRecipient $reportRecipient): JsonResponse
    {
        $reportRecipient->delete();

        return response()->json(['message' => 'Recipient removed.']);
    }
}
