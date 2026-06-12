<?php

namespace App\Http\Controllers\API\v1\mobile;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Ecommerce\EcommerceOrder;
use App\Models\Ecommerce\EcommerceOrderPickupProof;
use App\Models\Pos\Sale;
use App\Models\Reports\AuditLog;
use App\Services\ReceiptStorage;
use App\Services\RecordOrderPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Mobile back-office endpoints for the apex_dashboard Flutter app.
 *
 * Mirrors the admin web flow for ecommerce orders: list / detail /
 * verify / cancel / record-payment / mark-preparing / mark-picked-up.
 * Mutations share RecordOrderPaymentService + EcommerceOrder helpers
 * with the web admin, so status_changes and audit_logs rows fire
 * identically regardless of which client triggered the transition.
 *
 * Authentication: Laravel Passport (auth:api). The authenticated user
 * is the actor on every transition. Tenant scoping mirrors openclaw —
 * orders are owned via the customer's user_id (with 0/NULL treated as
 * "unassigned, in-scope" until proper multi-tenancy enforcement
 * lands).
 */
class EcommerceOrderController extends Controller
{
    use ApiResponse;

    public function __construct(private RecordOrderPaymentService $payments) {}

    /**
     * GET /v1/mobile/ecommerce-orders — paginated list with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => 'nullable|integer|in:0,1,2,3,4,5',
            'reference' => 'nullable|string|max:60',
            'customer_id' => 'nullable|integer|min:1',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = $this->tenantOrdersQuery()
            ->with(['customer:id,name,code,phone', 'sale:id,ecommerce_order_id,son,total,payment_type,cheque_status'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', (int) $request->input('status')))
            ->when($request->filled('reference'), fn ($q) => $q->where('reference', 'like', '%'.$request->input('reference').'%'))
            ->when($request->filled('customer_id'), fn ($q) => $q->where('customer_id', (int) $request->input('customer_id')))
            ->when($request->filled('date_from'), fn ($q) => $q->where('created_at', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->where('created_at', '<=', $request->input('date_to')))
            ->orderByDesc('ecommerce_orders.id');

        return $this->success($query->paginate((int) $request->input('per_page', 20)));
    }

    /**
     * GET /v1/mobile/ecommerce-orders/pending — quick feed of pending
     * orders for the dashboard notification badge.
     */
    public function pending(): JsonResponse
    {
        $orders = $this->tenantOrdersQuery()
            ->with(['customer:id,name,code,phone'])
            ->where('status', EcommerceOrder::STATUS_PENDING)
            ->orderByDesc('ecommerce_orders.id')
            ->limit(50)
            ->get();

        return $this->success([
            'count' => $orders->count(),
            'orders' => $orders,
        ]);
    }

    /**
     * GET /v1/mobile/ecommerce-orders/{order} — full detail with lines,
     * sale summary, payment + pickup proofs, and status history.
     */
    public function show(EcommerceOrder $ecommerceOrder): JsonResponse
    {
        $this->authorizeTenant($ecommerceOrder);

        $ecommerceOrder->load([
            'customer:id,name,code,phone',
            'lines.item:id,barcode,name',
            'sale.bank:id,bank_name,account_name',
            'sale.paymentProofs',
            'pickupProofs',
            'statusChanges.changedBy:id,name',
            'verifiedBy:id,name',
            'cancelledBy:id,name',
        ]);

        return $this->success([
            'order' => $ecommerceOrder,
            'status_label' => $ecommerceOrder->statusLabel(),
            'status_badge_variant' => $ecommerceOrder->statusBadgeVariant(),
            'payment_intent_label' => $ecommerceOrder->paymentIntentLabel(),
            'intended_sale_payment_type' => $ecommerceOrder->intendedSalePaymentType(),
            'status_history' => $ecommerceOrder->statusChanges->map(fn ($c) => [
                'from_status' => $c->from_status,
                'from_label' => $c->fromLabel(),
                'to_status' => (int) $c->to_status,
                'to_label' => $c->toLabel(),
                'to_badge_variant' => $c->toBadgeVariant(),
                'changed_by' => $c->changedBy?->name,
                'note' => $c->note,
                'at' => $c->created_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    /**
     * POST /v1/mobile/ecommerce-orders/{order}/verify — pending → verified.
     */
    public function verify(EcommerceOrder $ecommerceOrder): JsonResponse
    {
        $this->authorizeTenant($ecommerceOrder);

        if (! $ecommerceOrder->isPending()) {
            return $this->error('Only pending orders can be verified.', 422);
        }

        $actorId = (int) auth()->id();

        DB::transaction(function () use ($ecommerceOrder, $actorId) {
            $ecommerceOrder->update([
                'status' => EcommerceOrder::STATUS_VERIFIED,
                'verified_by' => $actorId,
                'verified_at' => now(),
            ]);
            $ecommerceOrder->logStatusChange(
                EcommerceOrder::STATUS_PENDING,
                EcommerceOrder::STATUS_VERIFIED,
                $actorId,
                'Verified via mobile dashboard',
            );
        });

        $ecommerceOrder->refresh()->load(['customer:id,name,code,phone', 'lines.item:id,barcode,name']);

        return $this->success($ecommerceOrder, "Order {$ecommerceOrder->reference} has been verified.");
    }

    /**
     * POST /v1/mobile/ecommerce-orders/{order}/cancel — pending → cancelled.
     */
    public function cancel(Request $request, EcommerceOrder $ecommerceOrder): JsonResponse
    {
        $this->authorizeTenant($ecommerceOrder);

        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        if (! $ecommerceOrder->isPending()) {
            return $this->error('Only pending orders can be cancelled.', 422);
        }

        $actorId = (int) auth()->id();

        DB::transaction(function () use ($ecommerceOrder, $actorId, $validated) {
            $ecommerceOrder->update([
                'status' => EcommerceOrder::STATUS_CANCELLED,
                'cancelled_by' => $actorId,
                'cancelled_at' => now(),
            ]);
            $ecommerceOrder->logStatusChange(
                EcommerceOrder::STATUS_PENDING,
                EcommerceOrder::STATUS_CANCELLED,
                $actorId,
                $validated['reason'] ?? 'Cancelled via mobile dashboard',
            );
        });

        $ecommerceOrder->refresh()->load(['customer:id,name,code,phone', 'lines.item:id,barcode,name']);

        return $this->success($ecommerceOrder, "Order {$ecommerceOrder->reference} has been cancelled.");
    }

    /**
     * POST /v1/mobile/ecommerce-orders/{order}/record-payment — record
     * a cashless payment. Accepts optional proof[] file uploads,
     * mirroring the admin web endpoint.
     */
    public function recordPayment(Request $request, EcommerceOrder $ecommerceOrder): JsonResponse
    {
        $this->authorizeTenant($ecommerceOrder);

        $validated = $request->validate([
            'payment_method' => 'required',
            'store_id' => 'required|integer|exists:stores,id',
            'bank_id' => 'nullable|integer|exists:banks,id',
            'bank_amount' => 'nullable|numeric|min:0',
            'reference_number' => 'nullable|string|max:120',
            'note' => 'nullable|string|max:500',
            'proofs' => 'nullable|array|max:5',
            'proofs.*' => 'file|image|max:5120|mimes:jpg,jpeg,png,webp,heic',
        ]);

        $paymentType = $this->resolvePaymentType($validated['payment_method']);

        if ($paymentType === Sale::PAYMENT_CREDIT) {
            return $this->error('Credit sales must be rung up at the POS.', 422);
        }

        $bankRequired = in_array($paymentType, [
            Sale::PAYMENT_EWALLET,
            Sale::PAYMENT_BANK_TRANSFER,
            Sale::PAYMENT_CHEQUE,
        ], true);

        if ($bankRequired) {
            foreach (['bank_id', 'bank_amount', 'reference_number'] as $field) {
                if (empty($validated[$field])) {
                    return $this->error("{$field} is required for this payment method.", 422);
                }
            }
        }

        // The bridge request carries everything RecordOrderPaymentService
        // needs (payment_type as the int + the proof files), so the
        // shared pipeline doesn't care that this came from mobile.
        $bridge = $request->duplicate();
        $bridge->merge(['payment_type' => $paymentType]);

        try {
            $sale = $this->payments->record($ecommerceOrder, $bridge, auth()->user());
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->error(collect($e->errors())->flatten()->first() ?? 'Validation failed.', 422);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return $this->error($e->getMessage() ?: 'Forbidden.', 403);
        }

        $ecommerceOrder->refresh()->load(['customer:id,name,code,phone', 'sale.paymentProofs']);

        return $this->success([
            'order' => $ecommerceOrder,
            'sale' => $sale->fresh(['paymentProofs', 'bank']),
        ], "Payment recorded as Sale #{$sale->son}.");
    }

    /**
     * POST /v1/mobile/ecommerce-orders/{order}/mark-preparing.
     */
    public function markPreparing(EcommerceOrder $ecommerceOrder): JsonResponse
    {
        $this->authorizeTenant($ecommerceOrder);

        if (! $ecommerceOrder->isPaid()) {
            return $this->error('Only paid orders can be marked as preparing.', 422);
        }

        $actorId = (int) auth()->id();

        DB::transaction(function () use ($ecommerceOrder, $actorId) {
            $ecommerceOrder->update(['status' => EcommerceOrder::STATUS_PREPARING]);
            $ecommerceOrder->logStatusChange(
                EcommerceOrder::STATUS_PAID,
                EcommerceOrder::STATUS_PREPARING,
                $actorId,
                'Marked preparing via mobile dashboard',
            );
            AuditLog::record($ecommerceOrder, 'marked_preparing', [
                'from_status' => EcommerceOrder::STATUS_PAID,
                'to_status' => EcommerceOrder::STATUS_PREPARING,
                'via' => 'mobile',
            ]);
        });

        return $this->success($ecommerceOrder->fresh(), "Order {$ecommerceOrder->reference} is now being prepared.");
    }

    /**
     * POST /v1/mobile/ecommerce-orders/{order}/mark-picked-up — accepts
     * optional pickup proof photos (receipt signing, customer holding
     * goods, handover shot).
     */
    public function markPickedUp(Request $request, EcommerceOrder $ecommerceOrder, ReceiptStorage $storage): JsonResponse
    {
        $this->authorizeTenant($ecommerceOrder);

        $request->validate([
            'proofs' => 'nullable|array|max:5',
            'proofs.*' => 'file|image|max:5120|mimes:jpg,jpeg,png,webp,heic',
        ]);

        if (! $ecommerceOrder->isPreparing()) {
            return $this->error('Only preparing orders can be marked as picked up.', 422);
        }

        $actorId = (int) auth()->id();

        DB::transaction(function () use ($ecommerceOrder, $actorId) {
            $ecommerceOrder->update(['status' => EcommerceOrder::STATUS_PICKED_UP]);
            $ecommerceOrder->logStatusChange(
                EcommerceOrder::STATUS_PREPARING,
                EcommerceOrder::STATUS_PICKED_UP,
                $actorId,
                'Marked picked up via mobile dashboard',
            );
        });

        $proofCount = 0;
        foreach ($request->file('proofs', []) as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }
            $path = $storage->store($file, ReceiptStorage::DIR_ORDER_PICKUP_PROOFS);
            EcommerceOrderPickupProof::create([
                'ecommerce_order_id' => $ecommerceOrder->id,
                'path' => $path,
                'uploaded_by' => $actorId,
            ]);
            $proofCount++;
        }

        AuditLog::record($ecommerceOrder, 'marked_picked_up', [
            'from_status' => EcommerceOrder::STATUS_PREPARING,
            'to_status' => EcommerceOrder::STATUS_PICKED_UP,
            'via' => 'mobile',
            'pickup_proof_count' => $proofCount,
        ]);

        return $this->success(
            $ecommerceOrder->fresh()->load('pickupProofs'),
            "Order {$ecommerceOrder->reference} has been picked up.",
        );
    }

    private function tenantOrdersQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $tenantUserId = (int) auth()->user()->user_id;

        return EcommerceOrder::query()
            ->whereHas('customer', function ($q) use ($tenantUserId) {
                $q->where(function ($inner) use ($tenantUserId) {
                    $inner->where('user_id', $tenantUserId)
                        ->orWhere('user_id', 0)
                        ->orWhereNull('user_id');
                });
            });
    }

    private function authorizeTenant(EcommerceOrder $order): void
    {
        $tenantUserId = (int) auth()->user()->user_id;
        $customerTenant = (int) ($order->customer?->user_id ?? 0);

        if ($customerTenant !== 0 && $customerTenant !== $tenantUserId) {
            abort(404);
        }
    }

    private function resolvePaymentType(int|string $value): int
    {
        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            $id = (int) $value;
            abort_unless(in_array($id, [
                Sale::PAYMENT_CASH,
                Sale::PAYMENT_EWALLET,
                Sale::PAYMENT_CREDIT,
                Sale::PAYMENT_BANK_TRANSFER,
                Sale::PAYMENT_CHEQUE,
            ], true), 422, 'Invalid payment_method id.');

            return $id;
        }

        return match (strtolower(trim((string) $value))) {
            'cash' => Sale::PAYMENT_CASH,
            'gcash', 'e-wallet', 'ewallet' => Sale::PAYMENT_EWALLET,
            'credit' => Sale::PAYMENT_CREDIT,
            'bank transfer', 'bank_transfer', 'transfer' => Sale::PAYMENT_BANK_TRANSFER,
            'cheque', 'check' => Sale::PAYMENT_CHEQUE,
            default => abort(422, "Unknown payment_method '{$value}'. Valid: cash, gcash, bank_transfer, cheque."),
        };
    }
}
