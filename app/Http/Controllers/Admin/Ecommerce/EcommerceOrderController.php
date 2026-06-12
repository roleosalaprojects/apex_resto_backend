<?php

namespace App\Http\Controllers\Admin\Ecommerce;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Ecommerce\RecordOrderPaymentRequest;
use App\Models\Ecommerce\EcommerceOrder;
use App\Models\Ecommerce\EcommerceOrderPickupProof;
use App\Models\Reports\AuditLog;
use App\Services\ReceiptStorage;
use App\Services\RecordOrderPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\View\View;

class EcommerceOrderController extends Controller
{
    public function index(): View
    {
        return view('admin.ecommerce.ecommerce-orders.index');
    }

    /**
     * Resolve a customer-facing reference (ECO-XXXX) to the admin show
     * page. Used by the QR code printed on /customer/orders/{reference}:
     * cashier scans → lands here → redirected to the id-keyed admin
     * show route. Keeps the numeric id off the customer's screen.
     */
    public function lookupByReference(string $reference): RedirectResponse
    {
        $order = EcommerceOrder::where('reference', $reference)->firstOrFail();

        return redirect()->route('ecommerce-orders.show', $order->id);
    }

    public function show(EcommerceOrder $ecommerceOrder): View
    {
        $ecommerceOrder->load([
            'customer', 'lines.item', 'verifiedBy', 'cancelledBy', 'sale',
            'statusChanges.changedBy:id,name',
        ]);

        // Store + bank lists in the Record Payment modal are fetched
        // live via Select2 AJAX (stores.select / banks.select) so admins
        // see banks added in Settings without reloading this page.
        return view('admin.ecommerce.ecommerce-orders.show', compact('ecommerceOrder'));
    }

    /**
     * Admin records a cashless payment against an EcommerceOrder.
     * Creates a Sale row with pos_id = NULL via the shared
     * SaleCreationService pipeline.
     */
    public function recordPayment(
        EcommerceOrder $ecommerceOrder,
        RecordOrderPaymentRequest $request,
        RecordOrderPaymentService $service,
    ): RedirectResponse {
        $this->assertSalesAccess();
        $sale = $service->record($ecommerceOrder, $request, $request->user());

        return redirect()
            ->route('ecommerce-orders.show', $ecommerceOrder->id)
            ->with('success', "Payment recorded as Sale #{$sale->son}.");
    }

    /**
     * Lightweight JSON feed for the navbar bell — count of pending
     * (status=0) orders + up to ten most recent for the dropdown
     * preview. Polled every few seconds, so we keep the payload tight.
     */
    public function pendingFeed(): JsonResponse
    {
        $query = EcommerceOrder::query()
            ->where('status', EcommerceOrder::STATUS_PENDING)
            ->orderByDesc('created_at');

        $count = (clone $query)->count();

        $orders = $query->with('customer:id,name')
            ->take(10)
            ->get(['id', 'reference', 'customer_id', 'total', 'qty', 'created_at'])
            ->map(fn (EcommerceOrder $order) => [
                'id' => $order->id,
                'reference' => $order->reference,
                'customer_name' => $order->customer?->name ?? 'Guest',
                'total' => (float) $order->total,
                'qty' => (int) $order->qty,
                'created_at' => $order->created_at?->format('M d, h:i A'),
                'url' => route('ecommerce-orders.show', $order->id),
            ]);

        return response()->json([
            'count' => $count,
            'orders' => $orders,
        ]);
    }

    public function table(Request $request): JsonResponse
    {
        $query = EcommerceOrder::with('customer')
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $data = $query->get();

        return datatables($data)
            ->addColumn('actions', function (EcommerceOrder $order) {
                return '<a href="'.route('ecommerce-orders.show', $order->id).'" class="btn btn-icon btn-bg-light btn-active-color-primary btn-sm"><i class="fas fa-eye"></i></a>';
            })
            ->rawColumns(['actions'])
            ->make(true);
    }

    public function verify(EcommerceOrder $ecommerceOrder): RedirectResponse
    {
        $this->assertSalesAccess();
        $ecommerceOrder = $this->lockedOrder($ecommerceOrder);

        if (! $ecommerceOrder->isPending()) {
            return redirect()->back()->with('error', 'Only pending orders can be verified.');
        }

        $fromStatus = (int) $ecommerceOrder->status;
        $ecommerceOrder->update([
            'status' => EcommerceOrder::STATUS_VERIFIED,
            'verified_by' => auth()->id(),
            'verified_at' => now(),
        ]);
        $ecommerceOrder->logStatusChange($fromStatus, EcommerceOrder::STATUS_VERIFIED, auth()->id());

        return redirect()->back()->with('success', "Order {$ecommerceOrder->reference} has been verified.");
    }

    /**
     * Cancel an ecommerce order — works at any state EXCEPT already-
     * cancelled. For PENDING/VERIFIED no sale exists yet, so the
     * service just flips status. For PAID/PREPARING/PICKED_UP a
     * refund Sale is written, stock returns, and the bank balance
     * is restored if the original payment was bank/e-wallet.
     */
    public function cancel(\Illuminate\Http\Request $request, EcommerceOrder $ecommerceOrder, \App\Services\CancelEcommerceOrderService $service): RedirectResponse
    {
        $this->assertSalesAccess();

        if ($ecommerceOrder->isCancelled()) {
            return redirect()->back()->with('error', 'This order is already cancelled.');
        }

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $service->cancel($ecommerceOrder, auth()->id(), $validated['reason'] ?? null);
        } catch (\DomainException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        $verb = $ecommerceOrder->fresh()->sale && $ecommerceOrder->fresh()->sale->refundSales()->exists()
            ? 'refunded'
            : 'cancelled';

        return redirect()->back()->with('success', "Order {$ecommerceOrder->reference} has been {$verb}.");
    }

    /**
     * Mark a paid order as "preparing" — store is packing it up. Forward-only
     * transition; refuses if the order isn't currently STATUS_PAID.
     */
    public function markPreparing(EcommerceOrder $ecommerceOrder): RedirectResponse
    {
        $this->assertSalesAccess();
        $ecommerceOrder = $this->lockedOrder($ecommerceOrder);

        if (! $ecommerceOrder->isPaid()) {
            return redirect()->back()->with('error', 'Only paid orders can be marked as preparing.');
        }

        $ecommerceOrder->update(['status' => EcommerceOrder::STATUS_PREPARING]);
        $ecommerceOrder->logStatusChange(EcommerceOrder::STATUS_PAID, EcommerceOrder::STATUS_PREPARING, auth()->id());

        AuditLog::record($ecommerceOrder, 'marked_preparing', [
            'from_status' => EcommerceOrder::STATUS_PAID,
            'to_status' => EcommerceOrder::STATUS_PREPARING,
        ]);

        return redirect()->back()->with('success', "Order {$ecommerceOrder->reference} is now being prepared.");
    }

    /**
     * Mark a preparing order as "picked up" — customer collected the goods
     * from the store. Terminal-happy state. Accepts optional proof
     * photos (signed receipt, customer holding goods, handover shot).
     */
    public function markPickedUp(
        Request $request,
        EcommerceOrder $ecommerceOrder,
        ReceiptStorage $storage,
    ): RedirectResponse {
        $this->assertSalesAccess();
        $ecommerceOrder = $this->lockedOrder($ecommerceOrder);

        if (! $ecommerceOrder->isPreparing()) {
            return redirect()->back()->with('error', 'Only orders being prepared can be marked as picked up.');
        }

        $validated = $request->validate([
            'proofs' => ['nullable', 'array', 'max:5'],
            'proofs.*' => ['file', 'image', 'max:5120', 'mimes:jpg,jpeg,png,webp,heic'],
        ], [
            'proofs.max' => 'You can attach at most 5 proof photos.',
            'proofs.*.image' => 'Each proof must be an image file.',
            'proofs.*.mimes' => 'Proof photos must be JPG, PNG, WEBP, or HEIC.',
            'proofs.*.max' => 'Each proof photo must be smaller than 5 MB.',
        ]);

        $ecommerceOrder->update(['status' => EcommerceOrder::STATUS_PICKED_UP]);
        $ecommerceOrder->logStatusChange(EcommerceOrder::STATUS_PREPARING, EcommerceOrder::STATUS_PICKED_UP, auth()->id());

        // Persist any attached pickup proofs through the shared storage
        // service so the on-disk layout matches sale_payment_proofs and
        // bank-transaction proofs.
        $proofCount = 0;
        foreach ($request->file('proofs', []) as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }
            $path = $storage->store($file, ReceiptStorage::DIR_ORDER_PICKUP_PROOFS);
            EcommerceOrderPickupProof::create([
                'ecommerce_order_id' => $ecommerceOrder->id,
                'path' => $path,
                'uploaded_by' => auth()->id(),
            ]);
            $proofCount++;
        }

        AuditLog::record($ecommerceOrder, 'marked_picked_up', [
            'from_status' => EcommerceOrder::STATUS_PREPARING,
            'to_status' => EcommerceOrder::STATUS_PICKED_UP,
            'pickup_proof_count' => $proofCount,
        ]);

        return redirect()->back()->with('success', "Order {$ecommerceOrder->reference} has been picked up.");
    }

    /**
     * Gate every state-mutation endpoint on the sales role flag. The
     * route group is just `auth` middleware, so without this an admin
     * with non-sales access (POS-only, settings-only, etc.) could
     * cancel/refund orders just by guessing the URL.
     */
    private function assertSalesAccess(): void
    {
        abort_unless(
            (bool) auth()->user()?->role?->sls,
            403,
            'You do not have permission to manage ecommerce orders.'
        );
    }

    /**
     * Reload the order with a row lock so two admins clicking
     * Verify/Mark Preparing/Mark Picked Up/Cancel at the same time
     * can't race each other into an inconsistent state. The cancel
     * service does its own locking; this covers the other handlers.
     */
    private function lockedOrder(EcommerceOrder $order): EcommerceOrder
    {
        return EcommerceOrder::query()
            ->whereKey($order->id)
            ->lockForUpdate()
            ->firstOrFail();
    }
}
