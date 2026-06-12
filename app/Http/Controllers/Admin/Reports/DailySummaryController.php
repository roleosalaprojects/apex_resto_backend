<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Controller;
use App\Services\ReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Operational daily summary — what came in today across POS and the
 * web-admin cashless flow, plus how many cheques are still in the air.
 * Mirrors the data shape the daily email uses so the view and the
 * mailable show the same numbers.
 */
class DailySummaryController extends Controller
{
    public function __construct(private ReportService $reports) {}

    public function index(Request $request): View
    {
        $tz = config('app.timezone', 'Asia/Manila');

        $date = $request->filled('date')
            ? Carbon::parse($request->input('date'), $tz)
            : Carbon::today($tz);

        $userId = auth()->user()->user_id;

        $summary = $this->reports->getSalesSummary($userId, 'daily', $date->toDateString());

        [$start, $end] = $this->periodRange($date, $tz);
        $cashless = $this->reports->getCashlessBreakdown($userId, $start, $end);

        // Pending cheques snapshot is always "right now" — it doesn't
        // change based on the date picker since it's not a period
        // metric, it's an open-balance metric.
        $pending = $this->reports->getPendingChequesSummary($userId);

        return view('admin.reports.daily-summary.index', [
            'date' => $date,
            'summary' => $summary,
            'cashless' => $cashless,
            'pending' => $pending,
        ]);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function periodRange(Carbon $date, string $tz): array
    {
        $start = $date->copy()->startOfDay()->setTimezone('UTC');
        $end = $date->copy()->endOfDay()->setTimezone('UTC');

        return [$start, $end];
    }
}
