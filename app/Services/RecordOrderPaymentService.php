<?php

namespace App\Services;

use App\Models\Ecommerce\EcommerceOrder;
use App\Models\Pos\Sale;
use App\Models\Pos\SalePaymentProof;
use App\Models\Reports\AuditLog;
use App\Models\Settings\Store;
use App\Models\User;
use App\Services\Data\SaleCreationData;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Admin-side conversion of an EcommerceOrder into a Sale.
 *
 * Delegates the heavy lifting to SaleCreationService so admin sales and
 * POS sales share one pipeline (stock deduction, points, credit ledger,
 * e-wallet bank flow). What lives here is the Order-specific orchestration:
 * tenancy boundary, status checks, the store-picker resolution, and
 * advancing EcommerceOrder.status from pending→verified when needed.
 */
class RecordOrderPaymentService
{
    public function __construct(
        private SaleCreationService $saleCreation,
        private ReceiptStorage $storage,
    ) {}

    public function record(
        EcommerceOrder $order,
        Request $request,
        User $admin,
    ): Sale {
        $this->guardCancelled($order);
        $this->guardAlreadyFulfilled($order);

        $store = Store::findOrFail($request->input('store_id'));
        $this->guardTenancy($order, $admin, $store);

        $data = SaleCreationData::fromOrder($order, $request, $admin, $store);
        $sale = $this->saleCreation->create($data);

        // Status advancement to PAID + verified_by/verified_at are
        // handled inside SaleCreationService::advanceLinkedOrderToPaid
        // so POS-rung ecommerce orders advance the same way.

        $this->savePaymentProofs($sale, $request, $admin);

        $this->writeAuditTrail($order, $sale, $admin);

        return $sale;
    }

    /**
     * Persist optional proof-of-payment photos uploaded with the form.
     * Files are stored under public/img/sale-payment-proofs and tracked
     * one row per file in sale_payment_proofs.
     */
    private function savePaymentProofs(Sale $sale, Request $request, User $admin): void
    {
        $files = $request->file('proofs', []);
        if (empty($files)) {
            return;
        }

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }

            $path = $this->storage->store($file, ReceiptStorage::DIR_SALE_PAYMENT_PROOFS);

            SalePaymentProof::create([
                'sale_id' => $sale->id,
                'path' => $path,
                'uploaded_by' => $admin->id,
            ]);
        }
    }

    /**
     * Two audit_log rows for one admin action: one on the Order (the
     * thing the admin acted on) and one on the Sale (the new row that
     * now lives in the books). Memory rule narrowly overridden for
     * pos_id IS NULL sales only — POS sales still go through pos_logs.
     */
    private function writeAuditTrail(EcommerceOrder $order, Sale $sale, User $admin): void
    {
        AuditLog::record($order, 'payment_recorded', [
            'sale_id' => $sale->id,
            'sale_son' => $sale->son,
            'payment_type' => $sale->payment_type,
            'cheque_status' => $sale->cheque_status,
            'bank_id' => $sale->bank_id,
            'reference_number' => $sale->reference_number,
            'bank_amount' => $sale->bank_amount,
            'total' => $sale->total,
            'store_id' => $sale->store_id,
        ], userId: $admin->id);

        AuditLog::record($sale, 'created_via_admin', [
            'ecommerce_order_id' => $order->id,
            'order_reference' => $order->reference,
            'customer_id' => $sale->customer_id,
            'payment_type' => $sale->payment_type,
            'cheque_status' => $sale->cheque_status,
            'total' => $sale->total,
            'store_id' => $sale->store_id,
            'pos_id' => $sale->pos_id,
        ], userId: $admin->id);
    }

    private function guardCancelled(EcommerceOrder $order): void
    {
        if ($order->isCancelled()) {
            throw ValidationException::withMessages([
                'order' => 'This order is cancelled and cannot be paid.',
            ]);
        }
    }

    private function guardAlreadyFulfilled(EcommerceOrder $order): void
    {
        if ($order->isFulfilled()) {
            throw ValidationException::withMessages([
                'order' => 'This order has already been paid.',
            ]);
        }
    }

    /**
     * The order's customer belongs to a tenant (customer.user_id). The
     * admin and the chosen store must belong to the same tenant. We use
     * the explicit user_id columns rather than auth() boundaries so the
     * check stays correct even if a superadmin is moderating.
     *
     * Customers registered via /shop currently get user_id = 0 (a stub
     * — see CustomerAuthController::register and the memory note about
     * sparse multi-tenancy enforcement). Treat 0 the same as null:
     * "unassigned, no tenant boundary to check." When customer.user_id
     * gets properly backfilled per tenant in a future multi-tenancy
     * pass, this guard tightens automatically — anything other than 0
     * or null still has to match.
     */
    private function guardTenancy(EcommerceOrder $order, User $admin, Store $store): void
    {
        $orderTenantId = $order->customer?->user_id;

        if ($orderTenantId === null || (int) $orderTenantId === 0) {
            return;
        }

        if ($admin->user_id !== $orderTenantId) {
            throw new AuthorizationException(
                'You do not have access to record payment on this order.',
            );
        }

        if ($store->user_id !== $orderTenantId) {
            throw ValidationException::withMessages([
                'store_id' => 'The chosen store does not belong to this order.',
            ]);
        }
    }
}
