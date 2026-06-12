<?php

namespace App\Http\Controllers\Admin\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\PosLog;
use App\Models\Settings\Pos;
use App\Models\Settings\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Yajra\DataTables\Exceptions\Exception;

class PosLogController extends Controller
{
    /**
     * POS log type labels.
     *
     * @var array<int, string>
     */
    private const TYPE_LABELS = [
        1 => 'Login',
        2 => 'Store Selection',
        3 => 'Start Day',
        4 => 'Cash-In',
        5 => 'Sale',
        6 => 'Refund',
        7 => 'Lock',
        8 => 'Log-Out',
        9 => 'Unlocked',
        10 => 'Z-Reading',
        11 => 'X-Reading',
        12 => 'Cash-Out',
        13 => 'Void Cash-Out',
        14 => 'Shift Reading',
    ];

    /**
     * Badge colors per type.
     *
     * @var array<int, string>
     */
    private const TYPE_COLORS = [
        1 => 'primary',
        2 => 'info',
        3 => 'success',
        4 => 'success',
        5 => 'primary',
        6 => 'warning',
        7 => 'secondary',
        8 => 'dark',
        9 => 'info',
        10 => 'danger',
        11 => 'warning',
        12 => 'danger',
        13 => 'danger',
        14 => 'info',
    ];

    public function index(): View
    {
        $stores = Store::where('status', 1)->orderBy('name')->get();
        $terminals = Pos::where('status', 1)->orderBy('name')->get();
        $typeLabels = self::TYPE_LABELS;

        return view('admin.accounting.pos-logs.index', compact('stores', 'terminals', 'typeLabels'));
    }

    public function table(Request $request): JsonResponse
    {
        $query = $this->filteredQuery($request);

        try {
            return DataTables($query)
                ->addColumn('formatted_date', function ($log) {
                    return $log->created_at->format('M d, Y h:i A');
                })
                ->addColumn('type_badge', function ($log) {
                    $label = self::TYPE_LABELS[$log->type] ?? 'Unknown';
                    $color = self::TYPE_COLORS[$log->type] ?? 'secondary';

                    return '<span class="badge badge-light-'.$color.'">'.$label.'</span>';
                })
                ->addColumn('terminal_name', function ($log) {
                    return $log->pos?->name ?? '<span class="text-muted">-</span>';
                })
                ->addColumn('store_name', function ($log) {
                    return $log->store?->name ?? '<span class="text-muted">-</span>';
                })
                ->addColumn('employee_name', function ($log) {
                    return $log->user?->name ?? '<span class="text-muted">-</span>';
                })
                ->addColumn('formatted_cash_in', function ($log) {
                    if (! $log->cash_in) {
                        return '<span class="text-muted">-</span>';
                    }

                    return '<span class="text-success fw-bold">'.number_format($log->cash_in, 2).'</span>';
                })
                ->addColumn('formatted_cash_out', function ($log) {
                    if (! $log->cash_out) {
                        return '<span class="text-muted">-</span>';
                    }

                    return '<span class="text-danger fw-bold">'.number_format($log->cash_out, 2).'</span>';
                })
                ->addColumn('reason_text', function ($log) {
                    return $log->reason ?? '<span class="text-muted">-</span>';
                })
                ->rawColumns(['type_badge', 'terminal_name', 'store_name', 'employee_name', 'formatted_cash_in', 'formatted_cash_out', 'reason_text'])
                ->make(true);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function export(Request $request): StreamedResponse
    {
        $query = $this->filteredQuery($request);

        $filename = 'pos_logs_'.now()->format('Y-m-d_His').'.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Date', 'Type', 'Terminal', 'Store', 'Employee', 'Cash In', 'Cash Out', 'Reason']);

            foreach ($query->lazy() as $log) {
                fputcsv($handle, [
                    $log->created_at->format('M d, Y h:i A'),
                    self::TYPE_LABELS[$log->type] ?? 'Unknown',
                    $log->pos?->name ?? '-',
                    $log->store?->name ?? '-',
                    $log->user?->name ?? '-',
                    $log->cash_in ? number_format($log->cash_in, 2, '.', '') : '',
                    $log->cash_out ? number_format($log->cash_out, 2, '.', '') : '',
                    $log->reason ?? '',
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function filteredQuery(Request $request): Builder
    {
        $query = PosLog::with(['pos', 'store', 'user'])
            ->latest('id');

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('store_id')) {
            $query->where('store_id', $request->input('store_id'));
        }

        if ($request->filled('pos_id')) {
            $query->where('pos_id', $request->input('pos_id'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        return $query;
    }
}
