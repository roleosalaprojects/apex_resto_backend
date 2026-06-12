<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Controller;
use App\Services\PeakHoursAnalysisService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PeakHoursController extends Controller
{
    public function __construct(protected PeakHoursAnalysisService $peakHoursService) {}

    public function index(Request $request)
    {
        $access = auth()->user()->role;

        if (! $access->sls) {
            return redirect('/admin/home')->with('error', "You don't have rights to access this. Please contact administrator if there are any concerns.");
        }

        $days = $this->resolveDays($request);
        $storeId = $request->input('store_id');
        $userId = auth()->user()->user_id;

        $data = $this->peakHoursService->getHeatmapData($userId, $days, $storeId);

        return view('admin.reports.reports.peak_hours', compact('data', 'days', 'access'));
    }

    public function data(Request $request)
    {
        $days = $this->resolveDays($request);
        $storeId = $request->input('store_id');
        $userId = auth()->user()->user_id;

        return response()->json($this->peakHoursService->getHeatmapData($userId, $days, $storeId));
    }

    /**
     * Resolve the days from start_date/end_date or fall back to days param.
     */
    protected function resolveDays(Request $request): int
    {
        if ($request->filled(['start_date', 'end_date'])) {
            $tz = config('app.timezone', 'Asia/Manila');
            $start = Carbon::parse($request->input('start_date'), $tz);
            $end = Carbon::parse($request->input('end_date'), $tz);

            return max(1, $start->diffInDays($end) + 1);
        }

        return (int) $request->input('days', 30);
    }
}
