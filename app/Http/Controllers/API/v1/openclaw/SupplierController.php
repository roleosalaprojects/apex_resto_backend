<?php

namespace App\Http\Controllers\API\v1\openclaw;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\InventoryManagement\Purchase;
use App\Models\InventoryManagement\Supplier;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Supplier ledger / payables endpoints.
 *
 * Outstanding payable per supplier is derived on the fly from the
 * purchases table:
 *   SUM(purchases.total - purchases.amount_paid)
 *   WHERE supplier_id = X
 *     AND user_id = tenant
 *     AND approval_status = APPROVED  (drafts and rejected POs don't owe anything)
 * No running totals stored; the raw POs are the source of truth.
 */
class SupplierController extends Controller
{
    use ApiResponse;

    /**
     * GET /v1/openclaw/suppliers/payables-summary — totals + top creditors.
     */
    public function payablesSummary(Request $request): JsonResponse
    {
        $request->validate([
            'limit' => 'nullable|integer|min:1|max:200',
        ]);

        $tenantUserId = (int) auth()->user()->user_id;
        $limit = (int) $request->input('limit', 20);

        $rows = Purchase::query()
            ->join('suppliers', 'suppliers.id', '=', 'purchases.supplier_id')
            ->where('purchases.user_id', $tenantUserId)
            ->where('purchases.approval_status', Purchase::APPROVAL_APPROVED)
            ->whereRaw('purchases.total > purchases.amount_paid')
            ->groupBy('suppliers.id', 'suppliers.name', 'suppliers.payment_terms_days')
            ->selectRaw('suppliers.id as supplier_id, suppliers.name as supplier_name, suppliers.payment_terms_days,
                COUNT(*) as po_count,
                COALESCE(SUM(purchases.total - purchases.amount_paid), 0) as outstanding,
                MIN(purchases.purchased) as oldest_unpaid_date')
            ->orderByDesc('outstanding')
            ->limit($limit)
            ->get();

        $totalPayable = (float) $rows->sum('outstanding');

        // Get full distinct supplier count with balance (not just the limited rows).
        $supplierCount = Purchase::query()
            ->where('purchases.user_id', $tenantUserId)
            ->where('purchases.approval_status', Purchase::APPROVAL_APPROVED)
            ->whereRaw('purchases.total > purchases.amount_paid')
            ->distinct('supplier_id')
            ->count('supplier_id');

        return $this->success([
            'totals' => [
                'total_payable' => round($totalPayable, 2),
                'supplier_count_with_balance' => $supplierCount,
                'top_creditors_returned' => $rows->count(),
            ],
            'top_creditors' => $rows->map(fn ($r) => [
                'supplier_id' => (int) $r->supplier_id,
                'supplier_name' => $r->supplier_name,
                'payment_terms_days' => $r->payment_terms_days !== null ? (int) $r->payment_terms_days : null,
                'po_count' => (int) $r->po_count,
                'outstanding' => round((float) $r->outstanding, 2),
                'oldest_unpaid_date' => $r->oldest_unpaid_date,
            ])->values(),
        ]);
    }

    /**
     * GET /v1/openclaw/suppliers/{supplier}/payable — per-supplier with PO breakdown.
     */
    public function payable(Request $request, Supplier $supplier): JsonResponse
    {
        $tenantUserId = (int) auth()->user()->user_id;

        if ((int) $supplier->user_id !== $tenantUserId) {
            abort(404);
        }

        $today = Carbon::today(config('app.timezone'));
        $termsDays = $supplier->payment_terms_days !== null ? (int) $supplier->payment_terms_days : null;

        $pos = Purchase::query()
            ->where('user_id', $tenantUserId)
            ->where('supplier_id', $supplier->id)
            ->where('approval_status', Purchase::APPROVAL_APPROVED)
            ->whereRaw('total > amount_paid')
            ->orderBy('purchased')
            ->get(['id', 'po', 'purchased', 'total', 'amount_paid', 'payment_status']);

        $purchaseOrders = $pos->map(function (Purchase $p) use ($today, $termsDays) {
            $outstanding = (float) $p->total - (float) $p->amount_paid;
            $dueDate = $termsDays !== null && $p->purchased
                ? Carbon::parse($p->purchased)->copy()->addDays($termsDays)
                : null;
            $daysOverdue = $dueDate !== null && $dueDate->lessThan($today)
                ? (int) $dueDate->diffInDays($today)
                : 0;

            return [
                'id' => $p->id,
                'po' => $p->po,
                'purchased' => $p->purchased instanceof \DateTimeInterface
                    ? $p->purchased->format('Y-m-d')
                    : (string) $p->purchased,
                'total' => round((float) $p->total, 2),
                'amount_paid' => round((float) $p->amount_paid, 2),
                'outstanding' => round($outstanding, 2),
                'payment_status' => (int) $p->payment_status,
                'due_date' => $dueDate?->toDateString(),
                'days_overdue' => $daysOverdue,
            ];
        });

        return $this->success([
            'supplier' => [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'payment_terms_days' => $termsDays,
                'contact' => $supplier->contact,
                'phone' => $supplier->number,
                'email' => $supplier->email,
            ],
            'totals' => [
                'outstanding' => round((float) $purchaseOrders->sum('outstanding'), 2),
                'po_count' => $purchaseOrders->count(),
                'overdue_po_count' => $purchaseOrders->where('days_overdue', '>', 0)->count(),
            ],
            'purchase_orders' => $purchaseOrders->values(),
        ]);
    }

    /**
     * PATCH /v1/openclaw/suppliers/{supplier}/payment-terms — set / clear net N days.
     */
    public function setPaymentTerms(Request $request, Supplier $supplier): JsonResponse
    {
        $tenantUserId = (int) auth()->user()->user_id;

        if ((int) $supplier->user_id !== $tenantUserId) {
            abort(404);
        }

        $validated = $request->validate([
            'payment_terms_days' => 'present|nullable|integer|min:0|max:365',
        ]);

        $supplier->forceFill(['payment_terms_days' => $validated['payment_terms_days']])->save();

        return $this->success([
            'supplier' => [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'payment_terms_days' => $supplier->payment_terms_days !== null ? (int) $supplier->payment_terms_days : null,
            ],
        ], $validated['payment_terms_days'] === null
            ? "Payment terms cleared for {$supplier->name}."
            : "Payment terms set to net {$validated['payment_terms_days']} days for {$supplier->name}.");
    }
}
