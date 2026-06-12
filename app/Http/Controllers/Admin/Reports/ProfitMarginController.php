<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Controller;
use App\Services\ProfitAnalysisService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ProfitMarginController extends Controller
{
    public function __construct(protected ProfitAnalysisService $profitService) {}

    public function index(Request $request)
    {
        $access = auth()->user()->role;

        if (! $access->sls) {
            return redirect('/admin/home')->with('error', "You don't have rights to access this. Please contact administrator if there are any concerns.");
        }

        $period = $this->resolvePeriod($request);
        $storeId = $request->input('store_id');
        $userId = auth()->user()->user_id;

        $data = $this->profitService->getProfitMargins($userId, $period, $storeId);
        $alerts = $this->profitService->getMarginAlerts($userId, $storeId);

        return view('admin.reports.reports.profit_margins', compact('data', 'alerts', 'period', 'access'));
    }

    public function data(Request $request)
    {
        $period = $this->resolvePeriod($request);
        $storeId = $request->input('store_id');
        $sort = $request->input('sort', 'margin_change');
        $userId = auth()->user()->user_id;

        return response()->json($this->profitService->getProfitMargins($userId, $period, $storeId, $sort));
    }

    /**
     * Resolve the period in days from start_date/end_date or fall back to period param.
     */
    protected function resolvePeriod(Request $request): int
    {
        if ($request->filled(['start_date', 'end_date'])) {
            $tz = config('app.timezone', 'Asia/Manila');
            $start = Carbon::parse($request->input('start_date'), $tz);
            $end = Carbon::parse($request->input('end_date'), $tz);

            return max(1, $start->diffInDays($end) + 1);
        }

        return (int) $request->input('period', 30);
    }
}
