<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Controller;
use App\Services\Bi\BusinessHealthService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BusinessIntelligenceController extends Controller
{
    public function __construct(protected BusinessHealthService $businessHealthService) {}

    public function index(Request $request)
    {
        $access = auth()->user()->role;

        if (! $access->sls) {
            return redirect('/admin/home')->with('error', "You don't have rights to access this. Please contact administrator if there are any concerns.");
        }

        return view('admin.reports.reports.business_intelligence', compact('access'));
    }

    public function data(Request $request)
    {
        [$start, $end] = $this->resolveRange($request);
        $storeId = $request->input('store_id');
        $userId = auth()->user()->user_id;

        return response()->json($this->businessHealthService->getDashboardData(
            $userId,
            $start,
            $end,
            $storeId ? (int) $storeId : null,
        ));
    }

    public function export(Request $request): StreamedResponse|\Illuminate\Http\RedirectResponse
    {
        if (! auth()->user()->role->sls) {
            return redirect('/admin/home')->with('error', "You don't have rights to access this. Please contact administrator if there are any concerns.");
        }

        [$start, $end] = $this->resolveRange($request);
        $storeId = $request->input('store_id');
        $userId = auth()->user()->user_id;

        $rows = $this->businessHealthService->getDailyPnlRows(
            $userId,
            $start,
            $end,
            $storeId ? (int) $storeId : null,
        );

        $filename = "business_health_{$start->toDateString()}_to_{$end->toDateString()}.csv";

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Date',
                'Gross Sales',
                'Refunds',
                'Net Sales',
                'COGS',
                'Gross Profit',
                'Expenses',
                'Net Profit',
                'Transactions',
                'Refund Count',
            ]);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['date'],
                    $row['gross_sales'],
                    $row['refunds_total'],
                    $row['net_sales'],
                    $row['cogs'],
                    $row['gross_profit'],
                    $row['expenses_total'],
                    $row['net_profit'],
                    $row['transactions'],
                    $row['refund_count'],
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Resolve start_date/end_date inputs, defaulting to the last 30 days
     * (Manila). End is clamped so a reversed range can't slip through.
     *
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    protected function resolveRange(Request $request): array
    {
        $tz = config('app.timezone', 'Asia/Manila');

        if ($request->filled(['start_date', 'end_date'])) {
            $start = Carbon::parse($request->input('start_date'), $tz)->startOfDay();
            $end = Carbon::parse($request->input('end_date'), $tz)->startOfDay();

            if ($end->lessThan($start)) {
                [$start, $end] = [$end, $start];
            }

            return [$start, $end];
        }

        $end = Carbon::today($tz);

        return [$end->copy()->subDays(29), $end];
    }
}
