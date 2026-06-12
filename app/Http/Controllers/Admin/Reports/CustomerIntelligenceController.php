<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Controller;
use App\Services\Bi\CustomerIntelligenceService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CustomerIntelligenceController extends Controller
{
    /** RFM window options the UI exposes, in days. */
    private const ALLOWED_WINDOWS = [90, 180, 365];

    public function __construct(protected CustomerIntelligenceService $customerIntelligenceService) {}

    public function index(Request $request)
    {
        $access = auth()->user()->role;

        if (! $access->sls) {
            return redirect('/admin/home')->with('error', "You don't have rights to access this. Please contact administrator if there are any concerns.");
        }

        return view('admin.reports.reports.customer_intelligence', compact('access'));
    }

    public function data(Request $request)
    {
        $tz = config('app.timezone', 'Asia/Manila');
        $userId = auth()->user()->user_id;

        $windowDays = (int) $request->input('window_days', 365);
        if (! in_array($windowDays, self::ALLOWED_WINDOWS, true)) {
            $windowDays = 365;
        }

        [$funnelFrom, $funnelTo] = $this->resolveFunnelRange($request, $tz);

        return response()->json([
            'rfm' => $this->customerIntelligenceService->getRfmData($userId, Carbon::today($tz), $windowDays),
            'funnel' => $this->customerIntelligenceService->getFunnelData($userId, $funnelFrom, $funnelTo),
        ]);
    }

    public function export(Request $request): StreamedResponse|\Illuminate\Http\RedirectResponse
    {
        if (! auth()->user()->role->sls) {
            return redirect('/admin/home')->with('error', "You don't have rights to access this. Please contact administrator if there are any concerns.");
        }

        $tz = config('app.timezone', 'Asia/Manila');
        $userId = auth()->user()->user_id;
        $asOf = Carbon::today($tz);

        $windowDays = (int) $request->input('window_days', 365);
        if (! in_array($windowDays, self::ALLOWED_WINDOWS, true)) {
            $windowDays = 365;
        }

        $customers = $this->customerIntelligenceService
            ->rfmCustomers($userId, $asOf, $windowDays)
            ->sortByDesc('monetary')
            ->values();

        $filename = "customer_segments_{$asOf->toDateString()}_{$windowDays}d.csv";

        return response()->streamDownload(function () use ($customers) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Customer',
                'Segment',
                'Recency (Days)',
                'Frequency',
                'Net Spend',
                'Lifetime Profit',
                'R',
                'F',
                'M',
            ]);

            foreach ($customers as $customer) {
                fputcsv($handle, [
                    $customer['name'],
                    $customer['segment'],
                    $customer['recency_days'],
                    $customer['frequency'],
                    $customer['monetary'],
                    $customer['lifetime_profit'],
                    $customer['r'],
                    $customer['f'],
                    $customer['m'],
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Funnel range from start_date/end_date, defaulting to the last 30
     * days (Manila). Reversed ranges are swapped rather than rejected.
     *
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    protected function resolveFunnelRange(Request $request, string $tz): array
    {
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
