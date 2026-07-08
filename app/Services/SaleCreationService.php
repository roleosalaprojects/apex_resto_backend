<?php

namespace App\Services;

use App\Jobs\API\v1\Payment\ProcessEWalletPaymentJob;
use App\Jobs\API\v1\Sale\UpdateItemStocksJob;
use App\Models\CustomerRelations\Customer;
use App\Models\CustomerRelations\CustomerCreditTransaction;
use App\Models\Ecommerce\EcommerceOrder;
use App\Models\Pos\Sale;
use App\Models\Pos\SaleLine;
use App\Models\Pos\SalePayment;
use App\Services\Data\SaleCreationData;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Source-of-truth Sale-creation pipeline.
 *
 * Called from two places today:
 *  - SaleController::store / refundReceipt (POS path, pos_id NOT NULL)
 *  - RecordOrderPaymentService (admin cashless path, pos_id NULL) — added
 *    in the next phase of this branch
 *
 * Anything that must happen on every Sale (line insert, customer points,
 * credit ledger, stock deduction job, e-wallet payment job) lives here so
 * the two callers can't drift. Per-caller concerns (PosLog, audit_log,
 * response shaping) stay in the calling controllers.
 */
class SaleCreationService
{
    public function create(SaleCreationData $data): Sale
    {
        $sale = DB::transaction(function () use ($data) {
            $sale = Sale::create($data->saleAttributes);

            $saleLines = array_map(static function (array $line) use ($sale) {
                $line['sales_id'] = $sale->id;

                return $line;
            }, $data->saleLineRows);

            SaleLine::insert($saleLines);

            if ($data->tenderRows !== []) {
                SalePayment::insert(array_map(static function (array $tender) use ($sale) {
                    $tender['sales_id'] = $sale->id;

                    return $tender;
                }, $data->tenderRows));
            }

            if ($data->customer) {
                if ($data->pointsUsed == 0) {
                    $data->customer->update([
                        'accumulated_points' => $data->customer->accumulated_points + $data->earnedPoints,
                    ]);
                } else {
                    $data->customer->update([
                        'accumulated_points' => $data->customer->accumulated_points - $data->pointsUsed,
                    ]);
                }
            }

            return $sale;
        });

        if ($sale->payment_type == Sale::PAYMENT_CREDIT && $data->customer) {
            $this->writeCreditCharge($sale, $data->customer);
        }

        // E-wallet and bank transfer both deposit immediately. Cheque
        // (PAYMENT_CHEQUE) stays pending until admin marks it cleared —
        // no BankTransaction is written here, and the bank's balance
        // doesn't move until the cheque actually clears. A multi-tender
        // sale deposits each e-wallet/bank tender separately at its
        // applied amount.
        if ($sale->payment_type == Sale::PAYMENT_MULTI) {
            foreach ($data->tenderRows as $tender) {
                if (in_array((int) $tender['payment_type'], [Sale::PAYMENT_EWALLET, Sale::PAYMENT_BANK_TRANSFER], true)) {
                    ProcessEWalletPaymentJob::dispatch($sale, (float) $tender['amount'], $tender['bank_id'] ?? null);
                }
            }
        } elseif (in_array($sale->payment_type, [Sale::PAYMENT_EWALLET, Sale::PAYMENT_BANK_TRANSFER], true)) {
            ProcessEWalletPaymentJob::dispatch($sale);
        }

        UpdateItemStocksJob::dispatch($sale);

        $this->advanceLinkedOrderToPaid($sale);

        return $sale;
    }

    /**
     * If the sale converted an ecommerce order, push the order forward.
     *
     * Two paths, intentionally different:
     *   - POS sale (sale.pos_id IS NOT NULL): the customer is AT the
     *     counter — paying and physically collecting the goods happen
     *     in the same action. Advance straight verified → paid →
     *     picked_up, writing both transitions so the timeline reflects
     *     the full path.
     *   - Cashless / web-admin / dashboard sale (sale.pos_id IS NULL):
     *     payment came in remotely, customer hasn't picked up yet.
     *     Stop at PAID. Admin advances preparing/picked_up manually
     *     once the goods actually move.
     *
     * Skips refunds (sale.type=true) — a refund shouldn't roll a
     * "preparing" order back to "paid". Skips orders already past the
     * intended final status for the same reason — forward-only.
     */
    private function advanceLinkedOrderToPaid(Sale $sale): void
    {
        if (! $sale->ecommerce_order_id || $sale->type) {
            return;
        }

        $order = EcommerceOrder::find($sale->ecommerce_order_id);
        if (! $order || (int) $order->status >= EcommerceOrder::STATUS_PAID) {
            return;
        }

        $fromStatus = (int) $order->status;
        $isPosSale = $sale->pos_id !== null;
        $finalStatus = $isPosSale
            ? EcommerceOrder::STATUS_PICKED_UP
            : EcommerceOrder::STATUS_PAID;

        $order->update([
            'status' => $finalStatus,
            // Preserve a prior verifier; if the order had never been
            // verified, the cashier/admin who took payment counts.
            'verified_by' => $order->verified_by ?? $sale->sales_by,
            'verified_at' => $order->verified_at ?? now(),
        ]);

        // Always log the entry into PAID so the timeline never skips
        // the "paid" event. POS sales then immediately log the second
        // hop into PICKED_UP.
        $paidNote = $isPosSale ? 'POS sale #'.$sale->son : 'Cashless payment recorded';
        $order->logStatusChange($fromStatus, EcommerceOrder::STATUS_PAID, $sale->sales_by, $paidNote);

        if ($isPosSale) {
            $order->logStatusChange(
                EcommerceOrder::STATUS_PAID,
                EcommerceOrder::STATUS_PICKED_UP,
                $sale->sales_by,
                'Picked up at POS — customer collected at the counter',
            );
        }
    }

    /**
     * Credit sale: lock the customer row, bump credit_balance, write
     * a charge ledger entry due in `credit_term_days` (defaults to 30).
     */
    private function writeCreditCharge(Sale $sale, Customer $customer): void
    {
        DB::transaction(function () use ($sale, $customer) {
            $customer->lockForUpdate();
            $customer->refresh();
            $newBalance = $customer->credit_balance + $sale->total;
            $customer->update(['credit_balance' => $newBalance]);

            $dueDate = Carbon::now()->addDays($customer->credit_term_days ?? 30);

            CustomerCreditTransaction::create([
                'customer_id' => $customer->id,
                'type' => 'charge',
                'amount' => $sale->total,
                'balance_after' => $newBalance,
                'due_date' => $dueDate,
                'reference_type' => 'sale',
                'reference_id' => $sale->id,
                'pos_id' => $sale->pos_id,
                'store_id' => $sale->store_id,
                'user_id' => $sale->sales_by,
            ]);
        });
    }
}
