<?php

namespace App\Http\Controllers\API\v1\openclaw;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Ecommerce\EcommerceOrder;
use App\Models\Pos\Sale;
use App\Models\Reports\AuditLog;
use App\Services\RecordOrderPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Ecommerce Order endpoints for OpenClaw.
 *
 * Tenant scoping: the bot's token resolves to the tenant owner via
 * auth()->user()->user_id. Orders are tenant-scoped via their
 * customer's user_id (customers.user_id = tenant owner). Shop-side
 * registrations currently stamp customer.user_id = 0 as a placeholder
 * (see CustomerAuthController), so we treat 0 the same way the admin
 * tenancy guard does — operate freely until proper multi-tenancy
 * enforcement lands.
 *
 * Mutations share the exact same pipeline the admin endpoints use:
 *   - recordPayment → RecordOrderPaymentService → SaleCreationService
 *     so status auto-advances to PAID and the status_changes /
 *     audit_logs rows fire identically
 *   - markPreparing / markPickedUp / verify / cancel write the same
 *     ecommerce_order_status_changes log + audit_logs rows
 */
class EcommerceOrderController extends Controller
{
    use ApiResponse;

    public function __construct(private RecordOrderPaymentService $payments) {}

    /**
     * GET /v1/openclaw/ecommerce-orders — paginated list with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => 'nullable|integer|in:0,1,2,3,4,5',
            'reference' => 'nullable|string|max:60',
            'customer_id' => 'nullable|integer|min:1',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'limit' => 'nullable|integer|min:1|max:200',
            'cursor' => 'nullable|integer|min:0',
        ]);

        $limit = (int) $request->input('limit', 50);
        $cursor = (int) $request->input('cursor', 0);

        $query = $this->tenantOrdersQuery()
            ->with(['customer:id,name,code', 'sale:id,ecommerce_order_id,son,total,payment_type,cheque_status'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', (int) $request->input('status')))
            ->when($request->filled('reference'), fn ($q) => $q->where('reference', 'like', '%'.$request->input('reference').'%'))
            ->when($request->filled('customer_id'), fn ($q) => $q->where('customer_id', (int) $request->input('customer_id')))
            ->when($request->filled('date_from'), fn ($q) => $q->where('created_at', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($q) => $q->where('created_at', '<=', $request->input('date_to')))
            ->when($cursor > 0, fn ($q) => $q->where('ecommerce_orders.id', '<', $cursor))
            ->orderByDesc('ecommerce_orders.id')
            ->limit($limit + 1);

        $rows = $query->get();
        $hasMore = $rows->count() > $limit;
        $items = $rows->take($limit);

        return $this->success([
            'limit' => $limit,
            'next_cursor' => $hasMore ? (int) $items->last()->id : null,
            'orders' => $items->map(fn (EcommerceOrder $o) => $this->present($o))->values(),
        ]);
    }

    /**
     * GET /v1/openclaw/ecommerce-orders/pending — quick "what needs my OK".
     */
    public function pending(): JsonResponse
    {
        $orders = $this->tenantOrdersQuery()
            ->with(['customer:id,name,code'])
            ->where('status', EcommerceOrder::STATUS_PENDING)
            ->orderByDesc('ecommerce_orders.id')
            ->limit(50)
            ->get();

        return $this->success([
            'count' => $orders->count(),
            'orders' => $orders->map(fn (EcommerceOrder $o) => $this->present($o))->values(),
        ]);
    }

    /**
     * GET /v1/openclaw/ecommerce-orders/{order} — full detail with lines,
     * sale, payment proofs, pickup proofs, and status history.
     */
    public function show(EcommerceOrder $ecommerceOrder): JsonResponse
    {
        $this->authorizeTenant($ecommerceOrder);

        $ecommerceOrder->load([
            'customer:id,name,code,phone',
            'lines.item:id,name,barcode',
            'sale.bank:id,bank_name,account_name',
            'sale.paymentProofs',
            'pickupProofs',
            'statusChanges.changedBy:id,name',
            'verifiedBy:id,name',
            'cancelledBy:id,name',
        ]);

        return $this->success([
            'order' => $this->present($ecommerceOrder) + [
                'note' => $ecommerceOrder->note,
                'verified_by' => $ecommerceOrder->verifiedBy?->name,
                'verified_at' => $ecommerceOrder->verified_at?->toIso8601String(),
                'cancelled_by' => $ecommerceOrder->cancelledBy?->name,
                'cancelled_at' => $ecommerceOrder->cancelled_at?->toIso8601String(),
                'lines' => $ecommerceOrder->lines->map(fn ($line) => [
                    'id' => $line->id,
                    'item_id' => $line->item_id,
                    'item_name' => $line->item_name,
                    'item_barcode' => $line->item?->barcode,
                    'qty' => (float) $line->qty,
                    'price' => round((float) $line->price, 2),
                    'sub_total' => round((float) $line->sub_total, 2),
                ])->values(),
                'sale' => $ecommerceOrder->sale ? [
                    'id' => $ecommerceOrder->sale->id,
                    'son' => $ecommerceOrder->sale->son,
                    'total' => round((float) $ecommerceOrder->sale->total, 2),
                    'payment_type' => (int) $ecommerceOrder->sale->payment_type,
                    'payment_method_label' => $this->paymentMethodLabel((int) $ecommerceOrder->sale->payment_type),
                    'cheque_status' => $ecommerceOrder->sale->cheque_status,
                    'reference_number' => $ecommerceOrder->sale->reference_number,
                    'bank' => $ecommerceOrder->sale->bank ? [
                        'id' => $ecommerceOrder->sale->bank->id,
                        'bank_name' => $ecommerceOrder->sale->bank->bank_name,
                        'account_name' => $ecommerceOrder->sale->bank->account_name,
                    ] : null,
                    'created_at' => $ecommerceOrder->sale->created_at?->toIso8601String(),
                    'payment_proofs' => $ecommerceOrder->sale->paymentProofs->map(fn ($p) => [
                        'id' => $p->id,
                        'url' => $p->url,
                    ])->values(),
                ] : null,
                'pickup_proofs' => $ecommerceOrder->pickupProofs->map(fn ($p) => [
                    'id' => $p->id,
                    'url' => $p->url,
                    'created_at' => $p->created_at?->toIso8601String(),
                ])->values(),
                'status_history' => $ecommerceOrder->statusChanges->map(fn ($c) => [
                    'from_status' => $c->from_status,
                    'from_label' => $c->fromLabel(),
                    'to_status' => (int) $c->to_status,
                    'to_label' => $c->toLabel(),
                    'changed_by' => $c->changedBy?->name,
                    'note' => $c->note,
                    'at' => $c->created_at?->toIso8601String(),
                ])->values(),
            ],
        ]);
    }

    /**
     * POST /v1/openclaw/ecommerce-orders/{order}/verify — pending → verified.
     */
    public function verify(EcommerceOrder $ecommerceOrder): JsonResponse
    {
        $this->authorizeTenant($ecommerceOrder);

        if (! $ecommerceOrder->isPending()) {
            return $this->conflict('Only pending orders can be verified.', $ecommerceOrder);
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
                'Verified via OpenClaw',
            );
        });

        return $this->success([
            'order' => $this->present($ecommerceOrder->refresh()),
        ], "Order {$ecommerceOrder->reference} verified.");
    }

    /**
     * POST /v1/openclaw/ecommerce-orders/{order}/cancel — pending → cancelled.
     */
    public function cancel(Request $request, EcommerceOrder $ecommerceOrder): JsonResponse
    {
        $this->authorizeTenant($ecommerceOrder);

        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        if (! $ecommerceOrder->isPending()) {
            return $this->conflict('Only pending orders can be cancelled.', $ecommerceOrder);
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
                $validated['reason'] ?? 'Cancelled via OpenClaw',
            );
        });

        return $this->success([
            'order' => $this->present($ecommerceOrder->refresh()),
        ], "Order {$ecommerceOrder->reference} cancelled.");
    }

    /**
     * POST /v1/openclaw/ecommerce-orders/{order}/record-payment — record
     * a cashless payment against an order. Mirrors the admin endpoint —
     * uses the same RecordOrderPaymentService so audit + status history
     * + bank deposits / cheque pending state fire identically.
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
        ]);

        $paymentType = $this->resolvePaymentType($validated['payment_method']);

        // Bot path bans credit sales — those are POS-only with a
        // customer credit-line check.
        if ($paymentType === Sale::PAYMENT_CREDIT) {
            return response()->json([
                'success' => false,
                'message' => 'Credit sales must be rung up at the POS, not via the bot.',
            ], 422);
        }

        $bankRequired = in_array($paymentType, [
            Sale::PAYMENT_EWALLET,
            Sale::PAYMENT_BANK_TRANSFER,
            Sale::PAYMENT_CHEQUE,
        ], true);

        if ($bankRequired) {
            foreach (['bank_id', 'bank_amount', 'reference_number'] as $field) {
                if (empty($validated[$field])) {
                    return response()->json([
                        'success' => false,
                        'message' => "{$field} is required for this payment method.",
                    ], 422);
                }
            }
        }

        $admin = auth()->user();
        $bridge = new Request;
        $bridge->merge([
            'payment_type' => $paymentType,
            'store_id' => $validated['store_id'],
            'bank_id' => $validated['bank_id'] ?? null,
            'bank_amount' => $validated['bank_amount'] ?? null,
            'reference_number' => $validated['reference_number'] ?? null,
            'note' => $validated['note'] ?? null,
        ]);

        try {
            $sale = $this->payments->record($ecommerceOrder, $bridge, $admin);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first() ?? 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'Forbidden.',
            ], 403);
        }

        return $this->success([
            'order' => $this->present($ecommerceOrder->refresh()),
            'sale' => [
                'id' => $sale->id,
                'son' => $sale->son,
                'total' => round((float) $sale->total, 2),
                'payment_type' => (int) $sale->payment_type,
                'payment_method_label' => $this->paymentMethodLabel((int) $sale->payment_type),
                'cheque_status' => $sale->cheque_status,
            ],
        ], "Payment recorded as Sale #{$sale->son}.");
    }

    /**
     * POST /v1/openclaw/ecommerce-orders/{order}/mark-preparing.
     */
    public function markPreparing(EcommerceOrder $ecommerceOrder): JsonResponse
    {
        $this->authorizeTenant($ecommerceOrder);

        if (! $ecommerceOrder->isPaid()) {
            return $this->conflict('Only paid orders can be marked as preparing.', $ecommerceOrder);
        }

        $actorId = (int) auth()->id();

        DB::transaction(function () use ($ecommerceOrder, $actorId) {
            $ecommerceOrder->update(['status' => EcommerceOrder::STATUS_PREPARING]);
            $ecommerceOrder->logStatusChange(
                EcommerceOrder::STATUS_PAID,
                EcommerceOrder::STATUS_PREPARING,
                $actorId,
                'Marked preparing via OpenClaw',
            );
            AuditLog::record($ecommerceOrder, 'marked_preparing', [
                'from_status' => EcommerceOrder::STATUS_PAID,
                'to_status' => EcommerceOrder::STATUS_PREPARING,
                'via' => 'openclaw',
            ]);
        });

        return $this->success([
            'order' => $this->present($ecommerceOrder->refresh()),
        ], "Order {$ecommerceOrder->reference} is now being prepared.");
    }

    /**
     * POST /v1/openclaw/ecommerce-orders/{order}/mark-picked-up.
     */
    public function markPickedUp(EcommerceOrder $ecommerceOrder): JsonResponse
    {
        $this->authorizeTenant($ecommerceOrder);

        if (! $ecommerceOrder->isPreparing()) {
            return $this->conflict('Only preparing orders can be marked as picked up.', $ecommerceOrder);
        }

        $actorId = (int) auth()->id();

        DB::transaction(function () use ($ecommerceOrder, $actorId) {
            $ecommerceOrder->update(['status' => EcommerceOrder::STATUS_PICKED_UP]);
            $ecommerceOrder->logStatusChange(
                EcommerceOrder::STATUS_PREPARING,
                EcommerceOrder::STATUS_PICKED_UP,
                $actorId,
                'Marked picked up via OpenClaw',
            );
            AuditLog::record($ecommerceOrder, 'marked_picked_up', [
                'from_status' => EcommerceOrder::STATUS_PREPARING,
                'to_status' => EcommerceOrder::STATUS_PICKED_UP,
                'via' => 'openclaw',
                'pickup_proof_count' => 0,
            ]);
        });

        return $this->success([
            'order' => $this->present($ecommerceOrder->refresh()),
        ], "Order {$ecommerceOrder->reference} has been picked up.");
    }

    /**
     * Tenant-scoped query. Joined to customers so we can filter by the
     * customer's user_id (the order itself has no tenant column).
     * customers.user_id = 0 is a placeholder for /shop-registered
     * customers — treated as in-scope until proper multi-tenancy
     * enforcement lands (mirrors the admin guardTenancy logic).
     */
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

    /**
     * @return array<string, mixed>
     */
    private function present(EcommerceOrder $o): array
    {
        return [
            'id' => $o->id,
            'reference' => $o->reference,
            'status' => (int) $o->status,
            'status_label' => $o->statusLabel(),
            'customer_id' => $o->customer_id,
            'customer_name' => $o->customer?->name,
            'qty' => (int) $o->qty,
            'total' => round((float) $o->total, 2),
            'placed_at' => $o->created_at?->toIso8601String(),
            'has_sale' => $o->isFulfilled(),
            'payment_intent' => $o->payment_intent,
            'payment_intent_label' => $o->paymentIntentLabel(),
        ];
    }

    /**
     * Accept either a Sale::PAYMENT_* int (1, 2, 4, 5) or a friendly
     * slug (cash, gcash, bank_transfer, cheque) — the bot will most
     * often send slugs.
     */
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

    private function paymentMethodLabel(int $type): string
    {
        return match ($type) {
            Sale::PAYMENT_CASH => 'Cash',
            Sale::PAYMENT_EWALLET => 'GCash / E-Wallet',
            Sale::PAYMENT_CREDIT => 'Credit',
            Sale::PAYMENT_BANK_TRANSFER => 'Bank Transfer',
            Sale::PAYMENT_CHEQUE => 'Cheque',
            default => 'Unknown',
        };
    }

    private function conflict(string $message, EcommerceOrder $order): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => [
                'current_status' => (int) $order->status,
                'current_status_label' => $order->statusLabel(),
            ],
        ], 409);
    }
}
