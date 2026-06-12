<?php

namespace App\Services;

use App\Jobs\API\v1\Payment\ProcessEWalletPaymentJob;
use App\Jobs\API\v1\Sale\UpdateItemStocksJob;
use App\Models\Ecommerce\EcommerceOrder;
use App\Models\Pos\Sale;
use App\Models\Pos\SaleLine;
use App\Models\Reports\AuditLog;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * One chokepoint for cancelling any non-PENDING ecommerce order.
 *
 * Why a service instead of inline controller logic: the reversal has
 * to be atomic — refund Sale insert, refund SaleLines, order status
 * flip, status-change row — or we leave a half-cancelled order behind.
 * Wrapping it in a single DB transaction with a row lock on the order
 * also serialises two admins clicking "Cancel" at the same time.
 *
 * Reuses the POS refund pattern verbatim: refund = new Sale with
 * type=true and sale_id pointing back at the original. The existing
 * UpdateItemStocksJob switches on Sale.type to either decrement
 * (sale) or increment (refund) ItemStore.stock, so we get stock
 * return for free.
 *
 * The refund Sale's ecommerce_order_id stays NULL. The link from
 * order → refund flows through the original sale's refundSales()
 * relation, so EcommerceOrder::sale() keeps returning the original
 * paid sale unambiguously.
 */
class CancelEcommerceOrderService
{
    /**
     * @param  int  $userId  the admin performing the cancellation
     * @param  string|null  $reason  optional free-text appended to the status-change row + order note
     *
     * @throws DomainException if the order can't be cancelled (already cancelled).
     */
    public function cancel(EcommerceOrder $order, int $userId, ?string $reason = null): void
    {
        // Capture the refund Sale from inside the transaction so we
        // can dispatch its jobs OUTSIDE — if the tx rolls back, the
        // jobs never fire and we don't end up with a queue worker
        // trying to return stock for a sale that doesn't exist.
        $refundSale = DB::transaction(function () use ($order, $userId, $reason) {
            // Reload with a row lock so two parallel cancels can't both
            // race past the status guard below.
            $locked = EcommerceOrder::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->first();

            if (! $locked) {
                throw new DomainException('Order not found.');
            }

            if ($locked->isCancelled()) {
                throw new DomainException('This order is already cancelled.');
            }

            $fromStatus = (int) $locked->status;
            $originalSale = $locked->sale; // null if order never reached PAID
            $refundSale = null;

            if ($originalSale !== null) {
                $refundSale = $this->writeRefundSale($originalSale, $userId);
            }

            $noteAppendage = $reason !== null && trim($reason) !== ''
                ? trim($reason)
                : null;

            $locked->update([
                'status' => EcommerceOrder::STATUS_CANCELLED,
                'cancelled_by' => $userId,
                'cancelled_at' => now(),
                'note' => $this->mergeNotes($locked->note, $noteAppendage),
            ]);

            // logStatusChange creates the status-change row; the
            // observer wired earlier this session picks it up and
            // dispatches SendOrderUpdateSmsJob → customer gets the
            // `order.cancelled` SMS automatically.
            $locked->logStatusChange(
                $fromStatus,
                EcommerceOrder::STATUS_CANCELLED,
                $userId,
                $noteAppendage
            );

            // Forensic audit row. Captures everything an investigator
            // would want months later: who, when, why, how much was
            // refunded, which bank rolled back, how many items returned
            // to inventory. Per memory note `feedback_audit_scope`, we
            // audit the EcommerceOrder (config/business-state model)
            // but NOT the Sale rows — pos_logs is the source of truth
            // for sale activity.
            $hadBankRefund = $originalSale !== null
                && in_array($originalSale->payment_type, [Sale::PAYMENT_EWALLET, Sale::PAYMENT_BANK_TRANSFER], true)
                && $originalSale->bank_id !== null
                && (float) ($originalSale->bank_amount ?? 0) > 0;

            AuditLog::record(
                $locked,
                'order_cancelled',
                [
                    'from_status' => $fromStatus,
                    'to_status' => EcommerceOrder::STATUS_CANCELLED,
                    'reason' => $noteAppendage,
                    'refund_sale_id' => $refundSale?->id,
                    'refund_sale_son' => $refundSale?->son,
                    'original_sale_id' => $originalSale?->id,
                    'original_sale_son' => $originalSale?->son,
                    'refund_total' => $originalSale?->total,
                    'payment_type' => $originalSale?->payment_type,
                    'bank_refund' => $hadBankRefund ? [
                        'bank_id' => $originalSale->bank_id,
                        'amount' => (float) $originalSale->bank_amount,
                    ] : null,
                    'lines_returned_to_stock' => $originalSale?->lines->count() ?? 0,
                ],
                userId: $userId,
            );

            return $refundSale;
        });

        // Post-commit side-effects. queue.php has after_commit=false
        // globally, so without this restructure the jobs could fire
        // BEFORE the tx commits. A rollback (e.g. lockForUpdate timing
        // out) would then leave a queue worker dispatching stock-return
        // for a sale that was never persisted.
        if ($refundSale !== null) {
            UpdateItemStocksJob::dispatch($refundSale);

            if (in_array($refundSale->payment_type, [Sale::PAYMENT_EWALLET, Sale::PAYMENT_BANK_TRANSFER], true)
                && $refundSale->bank_id !== null
                && (float) ($refundSale->bank_amount ?? 0) > 0) {
                ProcessEWalletPaymentJob::dispatch($refundSale);
            }
        }
    }

    /**
     * Build the refund Sale that mirrors the original — same lines,
     * same amounts, just flagged `type = true` and back-pointing at
     * the original via sale_id.
     */
    private function writeRefundSale(Sale $original, int $userId): Sale
    {
        $refund = Sale::create([
            // `R-` prefix is the convention POS refunds use too
            // (SaleController::computeCounter → sonType='R'); keeping
            // it here makes the audit trail uniform.
            'son' => 'R-'.$original->son,
            'sale_id' => $original->id,
            'type' => true,
            'total' => $original->total,
            'cash' => $original->cash,
            'change' => $original->change,
            'vatable' => $original->vatable,
            'vat' => $original->vat,
            'non_vat' => $original->non_vat,
            'zero_rated' => $original->zero_rated,
            'sales_by' => $userId,
            'user_id' => $original->user_id,
            'store_id' => $original->store_id,
            'pos_id' => null, // refund is admin-driven, not rung up at a till
            'customer_id' => $original->customer_id,
            'payment_type' => $original->payment_type,
            'reference_number' => $original->reference_number,
            'bank_amount' => $original->bank_amount,
            'bank_id' => $original->bank_id,
            'discount' => $original->discount,
            'profit' => $original->profit !== null ? -$original->profit : null,
            // ecommerce_order_id intentionally NULL — the link to the
            // order flows through original.refundSales(), so the
            // EcommerceOrder::sale() hasOne keeps returning a single
            // unambiguous row.
        ]);

        foreach ($original->lines as $line) {
            SaleLine::create([
                'sales_id' => $refund->id,
                'item_id' => $line->item_id,
                'qty' => $line->qty,
                'price' => $line->price,
                'sub_total' => $line->sub_total,
                'cost' => $line->cost,
                'unit_qty' => $line->unit_qty,
            ]);
        }

        // Stock-return + bank-refund jobs are dispatched by cancel()
        // AFTER the surrounding DB::transaction commits — see comment
        // in cancel() for the after_commit ordering rationale.

        return $refund;
    }

    private function mergeNotes(?string $existing, ?string $appendage): ?string
    {
        if ($appendage === null) {
            return $existing;
        }
        $tagged = 'Cancellation reason: '.$appendage;
        if ($existing === null || trim($existing) === '') {
            return $tagged;
        }

        return $existing."\n\n".$tagged;
    }
}
