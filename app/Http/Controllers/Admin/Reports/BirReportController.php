<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Controller;
use App\Models\Reports\AuditLog;
use App\Services\BirReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BirReportController extends Controller
{
    public function __construct(private readonly BirReportService $reports) {}

    public function index(): \Illuminate\View\View
    {
        return view('admin.reports.bir.annexf.index');
    }

    public function salesSummary(Request $request): \Illuminate\View\View
    {
        [$start, $end] = $this->dates($request);
        $rows = $this->reports->getBirSalesSummary(auth()->user()->user_id, $start, $end, $request->input('store_id'));

        return view('admin.reports.bir.annexf.sales_summary', compact('rows', 'start', 'end'));
    }

    public function voided(Request $request): \Illuminate\View\View
    {
        [$start, $end] = $this->dates($request);
        $rows = $this->reports->getVoidedTransactions(auth()->user()->user_id, $start, $end, $request->input('store_id'));

        return view('admin.reports.bir.annexf.voided', compact('rows', 'start', 'end'));
    }

    public function discountBook(Request $request): \Illuminate\View\View
    {
        [$start, $end] = $this->dates($request);
        $type = $request->input('type', 'sc');
        $rows = $this->reports->getDiscountSalesBook(auth()->user()->user_id, $type, $start, $end, $request->input('store_id'));

        return view('admin.reports.bir.annexf.discount_book', compact('rows', 'start', 'end', 'type'));
    }

    public function adjustments(Request $request): \Illuminate\View\View
    {
        [$start, $end] = $this->dates($request);
        $rows = $this->reports->getAdjustments(auth()->user()->user_id, $start, $end, $request->input('store_id'));

        return view('admin.reports.bir.annexf.adjustments', compact('rows', 'start', 'end'));
    }

    public function vatClass(Request $request): \Illuminate\View\View
    {
        [$start, $end] = $this->dates($request);
        $rows = $this->reports->getDailySalesByVatClass(auth()->user()->user_id, $start, $end, $request->input('store_id'));

        return view('admin.reports.bir.annexf.vat_class', compact('rows', 'start', 'end'));
    }

    /**
     * CSV export for any of the Annex F reports, with an explicit
     * audit-log entry recording who exported what range.
     */
    public function export(Request $request, string $report): StreamedResponse
    {
        [$start, $end] = $this->dates($request);
        $userId = auth()->user()->user_id;
        $storeId = $request->input('store_id');

        $rows = match ($report) {
            'sales-summary' => $this->reports->getBirSalesSummary($userId, $start, $end, $storeId),
            'voided' => $this->reports->getVoidedTransactions($userId, $start, $end, $storeId),
            'discount-book' => $this->reports->getDiscountSalesBook($userId, $request->input('type', 'sc'), $start, $end, $storeId),
            'adjustments' => $this->reports->getAdjustments($userId, $start, $end, $storeId),
            'vat-class' => $this->reports->getDailySalesByVatClass($userId, $start, $end, $storeId),
            default => abort(404),
        };

        AuditLog::create([
            'user_id' => auth()->id(),
            'auditable_type' => 'bir_report_export',
            'auditable_id' => 0,
            'event' => 'exported',
            'source' => 'web',
            'new_values' => ['report' => $report, 'start' => $start, 'end' => $end, 'rows' => count($rows)],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'url' => $request->fullUrl(),
        ]);

        $csv = $this->reports->generateCsv($rows);
        $filename = 'bir_'.str_replace('-', '_', $report).'_'.now()->format('Y-m-d_His').'.csv';

        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function dates(Request $request): array
    {
        $tz = config('app.timezone', 'Asia/Manila');
        $start = $request->input('startDate', Carbon::today($tz)->toDateString());
        $end = $request->input('endDate', Carbon::today($tz)->toDateString());

        return [$start, $end];
    }
}
