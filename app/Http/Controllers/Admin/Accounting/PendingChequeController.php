<?php

namespace App\Http\Controllers\Admin\Accounting;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Accounting\BounceChequeRequest;
use App\Http\Requests\Admin\Accounting\ClearChequeRequest;
use App\Models\Pos\Sale;
use App\Services\MarkChequeBouncedService;
use App\Services\MarkChequeClearedService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Lists pending cheques (admin-recorded sales with payment_type=5,
 * cheque_status=pending) and exposes Mark Cleared / Mark Bounced.
 *
 * Scoped to the admin's tenant via auth()->user()->user_id, since
 * sales.user_id carries the tenant boundary.
 */
class PendingChequeController extends Controller
{
    public function index(): View
    {
        return view('admin.accounting.pending-cheques.index');
    }

    public function table(Request $request): JsonResponse
    {
        $query = Sale::query()
            ->with(['customer:id,name,code', 'bank:id,account_name,bank_name'])
            ->where('payment_type', Sale::PAYMENT_CHEQUE)
            ->where('cheque_status', Sale::CHEQUE_PENDING)
            ->where('user_id', auth()->user()->user_id)
            ->orderBy('created_at');

        $data = $query->get();

        return datatables($data)
            ->addColumn('customer_name', fn (Sale $sale) => $sale->customer?->name ?? '—')
            ->addColumn('bank_name', fn (Sale $sale) => $sale->bank
                ? "{$sale->bank->account_name} ({$sale->bank->bank_name})"
                : '—')
            ->addColumn('days_outstanding', function (Sale $sale) {
                $days = (int) $sale->created_at->diffInDays(now());
                $class = $days > 30 ? 'text-danger fw-bold' : ($days > 14 ? 'text-warning' : '');

                return "<span class=\"{$class}\">{$days}</span>";
            })
            ->addColumn('amount_formatted', fn (Sale $sale) => '₱'.number_format((float) $sale->bank_amount, 2))
            ->addColumn('actions', function (Sale $sale) {
                return view('admin.accounting.pending-cheques.row-actions', compact('sale'))->render();
            })
            ->rawColumns(['days_outstanding', 'actions'])
            ->make(true);
    }

    public function clear(
        Sale $sale,
        ClearChequeRequest $request,
        MarkChequeClearedService $service,
    ): RedirectResponse {
        $service->clear($sale, $request, $request->user());

        return redirect()
            ->route('pending-cheques.index')
            ->with('success', "Cheque #{$sale->reference_number} marked cleared.");
    }

    public function bounce(
        Sale $sale,
        BounceChequeRequest $request,
        MarkChequeBouncedService $service,
    ): RedirectResponse {
        $service->bounce($sale, $request, $request->user());

        return redirect()
            ->route('pending-cheques.index')
            ->with('warning', "Cheque #{$sale->reference_number} marked bounced. Customer was charged ₱".number_format((float) $sale->total, 2).'.');
    }
}
