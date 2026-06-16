<?php

namespace App\Services\Data;

use App\Models\CustomerRelations\Customer;
use App\Models\Ecommerce\EcommerceOrder;
use App\Models\Pos\Order;
use App\Models\Pos\OrderLine;
use App\Models\Pos\Sale;
use App\Models\Settings\Pos;
use App\Models\Settings\Store;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Read-only payload for SaleCreationService.
 *
 * Two factories build it today:
 *  - fromPosRequest: a validated POS create-sale request
 *  - fromInternalRefund: the same payload shape but generated server-side
 *    by SaleController::refundReceipt (which currently passes a raw Request)
 *
 * A third factory (fromOrder) will be added in the next phase for the
 * admin "Record Payment" flow against an EcommerceOrder.
 */
final readonly class SaleCreationData
{
    /**
     * @param  array<string, mixed>  $saleAttributes  payload for Sale::create
     * @param  array<int, array<string, mixed>>  $saleLineRows  payload for SaleLine::insert (sales_id appended by the service)
     */
    public function __construct(
        public array $saleAttributes,
        public array $saleLineRows,
        public ?Customer $customer,
        public float $earnedPoints,
        public float $pointsUsed,
    ) {}

    /**
     * Build from the validated POS create-sale request. Mirrors the
     * exact shape SaleController::processSale used to assemble inline,
     * so a refactor introduces zero behavior change.
     */
    public static function fromPosRequest(
        Request $request,
        Pos $pos,
        int $counter,
        int|string $sonType,
        int $txnNo = 0,
        bool $isTraining = false,
        ?int $returnNo = null,
    ): self {
        $now = Carbon::now();
        $discount = 0;
        $creditableTotal = 0;
        $saleLineRows = [];

        foreach ($request->line as $line) {
            $qty = $line['qty'];
            $price = $line['price'];
            $subDiscount = $line['discount'];
            $discount += $subDiscount;

            if (($line['product']['creditable_to_points'] ?? 0) == 1) {
                $creditableTotal += $qty * ($price - $subDiscount);
            }

            $saleLineRows[] = [
                'qty' => $qty,
                'unit' => $line['unit'],
                'discount' => $subDiscount,
                'price' => $price,
                'sub_total' => $qty * ($price - $subDiscount),
                'vatable' => $line['vatable'],
                'vat' => $line['vat'],
                'exempt' => $line['vat_exempt'],
                'zero_rated' => $line['zero_rated'],
                'cost' => $line['product']['cost'],
                'refundable' => $request->type ? 0 : $qty,
                'refunded' => $request->type ? $qty : 0,
                'item_id' => $line['product']['id'],
                'unit_id' => $line['unit_id'] == -1 ? null : $line['unit_id'],
                'unit_qty' => $line['unit_qty'],
                'discount_id' => null,
                'discount_by' => null,
                'vat_special_discounts' => $line['vat_special_discounts'],
                'sc_discount' => $line['sc_discount'],
                'pwd_discount' => $line['pwd_discount'],
                'sp_discount' => $line['sp_discount'],
                'naac_discount' => $line['naac_discount'],
                'profit' => $line['profit'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $customer = Customer::find($request->details['customer_id']);
        $pointsUsed = $request->details['points_used'] ?? 0;
        $earnedPoints = $request->details['points'];

        if ($customer && $pointsUsed == 0) {
            $earnedPoints = $creditableTotal * $customer->points;
        }

        $saleAttributes = [
            'counter' => $counter,
            'txn_no' => $txnNo,
            'is_training' => $isTraining,
            'return_no' => $returnNo,
            'son' => $sonType.'-'.$counter.'-'.$request->pos_id,
            'pax' => $request->details['pax'] ?? null,
            'sc_count' => $request->details['sc_count'] ?? null,
            'pwd_count' => $request->details['pwd_count'] ?? null,
            'payment_type' => $request->details['payment_type'],
            'reference_number' => $request->details['reference_number'],
            'bank_amount' => $request->details['bank_amount'],
            'bank_id' => $request->details['bank_id'],
            'total' => $request->details['total'],
            'cash' => $request->details['cash'],
            'change' => $request->details['change'],
            'header' => $pos->store->header,
            'footer' => $pos->store->footer,
            'type' => (bool) $request->type,
            'sales_by' => Auth::guard('api')->user()->id,
            'pos_id' => $request->pos_id,
            'store_id' => $pos->store_id,
            'user_id' => Auth::guard('api')->user()->user_id,
            'created_at' => $now,
            'updated_at' => $now,
            'profit' => $request->details['profit'],
            'vatable' => $request->details['vatable'],
            'vat' => $request->details['vat'],
            'vat_exempt' => $request->details['vat_exempt'],
            'zero_rated' => $request->details['zero_rated'],
            'non_vat' => 0,
            'discount' => $discount,
            'cancelled' => false,
            'approved_by' => null,
            'sale_id' => $request->sale_id != null ? $request->sale_id : 0,
            'sale_type' => $request->sale_id != null,
            'sc_discount' => $request->details['sc_discount'],
            'pwd_discount' => $request->details['pwd_discount'],
            'sp_discount' => $request->details['sp_discount'],
            'naac_discount' => $request->details['naac_discount'],
            'vat_special_discounts' => $request->details['vat_special_discounts'],
            'special_discount_type' => $request->details['special_discount_type'] ?? 0,
            'special_discount_name' => $request->details['special_discount_name'],
            'special_discount_id' => $request->details['special_discount_id'],
            'special_discount_tin' => $request->details['special_discount_tin'],
            'special_discount_child_name' => $request->details['special_discount_child_name'] ?? null,
            'special_discount_child_birthdate' => $request->details['special_discount_child_birthdate'] ?? null,
            'special_discount_child_age' => $request->details['special_discount_child_age'] ?? null,
            'customer_id' => $request->details['customer_id'],
            'acquired_points' => $earnedPoints,
            'points_used' => $pointsUsed,
            'ecommerce_order_id' => $request->ecommerce_order_id,
            'voucher_id' => ($request->details['voucher_id'] ?? null) ?: null,
            'voucher_code' => ($request->details['voucher_code'] ?? null) ?: null,
            'voucher_discount' => $request->details['voucher_discount'] ?? 0,
        ];

        return new self(
            saleAttributes: $saleAttributes,
            saleLineRows: $saleLineRows,
            customer: $customer,
            earnedPoints: (float) $earnedPoints,
            pointsUsed: (float) $pointsUsed,
        );
    }

    /**
     * Build from an EcommerceOrder being converted to a Sale by an admin
     * recording payment. pos_id is NULL — that's the marker for an
     * admin-recorded sale. Counter is 0 (POS counter doesn't apply).
     * SON is "WEB-{order.reference}" so it's unique and traces back.
     *
     * BIR tax breakdown (vatable/vat/exempt/zero_rated) is left at 0
     * here because EcommerceOrderLine has no tax columns — that's a
     * deliberate scope decision until owner completes BIR research.
     * Discounts likewise default to 0 because shop checkout has no
     * discount UX yet.
     *
     * Points accrue identically to POS: creditableTotal × customer.points
     * across lines where item.creditable_to_points = 1.
     */
    public static function fromOrder(
        EcommerceOrder $order,
        Request $request,
        User $admin,
        Store $store,
    ): self {
        $now = Carbon::now();
        $customer = $order->customer;

        $order->loadMissing('lines.item:id,cost,creditable_to_points');

        $saleLineRows = [];
        $creditableTotal = 0;
        $totalProfit = 0;

        foreach ($order->lines as $line) {
            $item = $line->item;
            $qty = (float) $line->qty;
            $price = (float) $line->price;
            $cost = (float) ($item?->cost ?? 0);
            $lineProfit = $qty * ($price - $cost);
            $totalProfit += $lineProfit;

            if ($item && (int) $item->creditable_to_points === 1) {
                $creditableTotal += $qty * $price;
            }

            $saleLineRows[] = [
                'qty' => $qty,
                // sale_lines.unit is NOT NULL — for ecommerce conversion
                // we don't track a UOM yet, so default to empty string
                // to satisfy the column without inventing a unit.
                'unit' => '',
                'discount' => 0,
                'price' => $price,
                'sub_total' => $qty * $price,
                'vatable' => 0,
                'vat' => 0,
                'exempt' => 0,
                'zero_rated' => 0,
                'cost' => $cost,
                'refundable' => $qty,
                'refunded' => 0,
                'item_id' => $line->item_id,
                'unit_id' => null,
                'unit_qty' => 1,
                'discount_id' => null,
                'discount_by' => null,
                'vat_special_discounts' => 0,
                'sc_discount' => 0,
                'pwd_discount' => 0,
                'sp_discount' => 0,
                'naac_discount' => 0,
                'profit' => $lineProfit,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $earnedPoints = ($customer && $creditableTotal > 0)
            ? $creditableTotal * (float) $customer->points
            : 0;

        $paymentType = (int) $request->input('payment_type');
        $chequeStatus = $paymentType === Sale::PAYMENT_CHEQUE
            ? Sale::CHEQUE_PENDING
            : null;

        $bankAmount = $request->input('bank_amount');
        $bankAmount = $bankAmount !== null ? (float) $bankAmount : null;

        $saleAttributes = [
            'counter' => 0,
            'son' => 'WEB-'.$order->reference,
            'payment_type' => $paymentType,
            'cheque_status' => $chequeStatus,
            'reference_number' => $request->input('reference_number'),
            'bank_amount' => $bankAmount,
            'bank_id' => $request->input('bank_id'),
            'total' => (float) $order->total,
            'cash' => $paymentType === Sale::PAYMENT_CASH ? (float) $order->total : 0,
            'change' => 0,
            'header' => $store->header,
            'footer' => $store->footer,
            'type' => false,
            'sales_by' => $admin->id,
            'pos_id' => null,
            'store_id' => $store->id,
            // Tenant owner derived from the order's customer — not the
            // admin's own user_id, which may differ in multi-tenant setups.
            'user_id' => $customer?->user_id ?? $admin->user_id,
            'created_at' => $now,
            'updated_at' => $now,
            'profit' => $totalProfit,
            'vatable' => 0,
            'vat' => 0,
            'vat_exempt' => 0,
            'zero_rated' => 0,
            'non_vat' => 0,
            'discount' => 0,
            'cancelled' => false,
            'approved_by' => null,
            'sale_id' => 0,
            'sale_type' => false,
            'sc_discount' => 0,
            'pwd_discount' => 0,
            'sp_discount' => 0,
            'naac_discount' => 0,
            'vat_special_discounts' => 0,
            'special_discount_type' => 0,
            'special_discount_name' => null,
            'special_discount_id' => null,
            'special_discount_tin' => null,
            'special_discount_child_name' => null,
            'special_discount_child_birthdate' => null,
            'special_discount_child_age' => null,
            'customer_id' => $order->customer_id,
            'acquired_points' => $earnedPoints,
            'points_used' => 0,
            'ecommerce_order_id' => $order->id,
            'voucher_id' => null,
            'voucher_code' => null,
            'voucher_discount' => 0,
        ];

        return new self(
            saleAttributes: $saleAttributes,
            saleLineRows: $saleLineRows,
            customer: $customer,
            earnedPoints: (float) $earnedPoints,
            pointsUsed: 0.0,
        );
    }

    /**
     * Build from a restaurant Order being settled at the cashier. Mirrors
     * fromPosRequest's counter/SON conventions so settled dine-in sales
     * share the official SI series. Only non-voided lines settle.
     *
     * VAT split is kept simple here (vatable lines → vatable+vat, others →
     * vat_exempt); BIR Annex F group-discount/VAT refinement lands in the
     * next phase via DiscountAllocationService.
     *
     * @param  array<string, mixed>  $payment  payment_type/cash/change/reference/bank fields
     */
    public static function fromRestaurantOrder(
        Order $order,
        Pos $pos,
        int $counter,
        int|string $sonType,
        array $payment,
    ): self {
        $now = Carbon::now();
        $order->loadMissing('lines.item:id,cost,creditable_to_points,vatable');

        $saleLineRows = [];
        $total = 0;
        $totalProfit = 0;
        $vatableTotal = 0;
        $vatTotal = 0;
        $exemptTotal = 0;
        $creditableTotal = 0;

        $settleLines = $order->lines->reject(
            fn (OrderLine $line) => (int) $line->line_status === OrderLine::LINE_VOIDED
        );

        foreach ($settleLines as $line) {
            $qty = (float) $line->qty;
            $price = (float) $line->price;
            $cost = (float) $line->cost;
            $unitQty = (float) ($line->unit_qty ?: 1);
            $discount = (float) ($line->discount ?? 0);
            $subTotal = $qty * ($price - $discount);
            $total += $subTotal;
            $lineProfit = $qty * ($price - $cost);
            $totalProfit += $lineProfit;

            $isVatable = (int) ($line->item?->vatable ?? 0) === 1;
            $lineVat = $isVatable ? round($subTotal * 12 / 112, 2) : 0;
            $lineVatable = $isVatable ? round($subTotal - $lineVat, 2) : 0;
            $lineExempt = $isVatable ? 0 : $subTotal;
            $vatableTotal += $lineVatable;
            $vatTotal += $lineVat;
            $exemptTotal += $lineExempt;

            if ((int) ($line->item?->creditable_to_points ?? 0) === 1) {
                $creditableTotal += $subTotal;
            }

            $saleLineRows[] = [
                'qty' => $qty,
                'unit' => $line->unit_name ?? '',
                'discount' => $discount,
                'price' => $price,
                'sub_total' => $subTotal,
                'vatable' => $lineVatable,
                'vat' => $lineVat,
                'exempt' => $lineExempt,
                'zero_rated' => 0,
                'cost' => $cost,
                'refundable' => $qty,
                'refunded' => 0,
                'item_id' => $line->item_id,
                'unit_id' => $line->unit_id,
                'unit_qty' => $unitQty,
                'discount_id' => null,
                'discount_by' => null,
                'vat_special_discounts' => 0,
                'sc_discount' => 0,
                'pwd_discount' => 0,
                'sp_discount' => 0,
                'naac_discount' => 0,
                'profit' => $lineProfit,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $customer = Customer::find($payment['customer_id'] ?? null);
        $earnedPoints = ($customer && $creditableTotal > 0)
            ? $creditableTotal * (float) $customer->points
            : 0;

        $cash = (float) ($payment['cash'] ?? $total);

        $saleAttributes = [
            'counter' => $counter,
            'son' => $sonType.'-'.$counter.'-'.$pos->id,
            'payment_type' => (int) ($payment['payment_type'] ?? Sale::PAYMENT_CASH),
            'reference_number' => $payment['reference_number'] ?? null,
            'bank_amount' => $payment['bank_amount'] ?? null,
            'bank_id' => $payment['bank_id'] ?? null,
            'total' => $total,
            'cash' => $cash,
            'change' => max(0, $cash - $total),
            'header' => $pos->store->header ?? null,
            'footer' => $pos->store->footer ?? null,
            'type' => false,
            'order_type' => (int) ($order->order_type ?? Order::TYPE_DINE_IN),
            'table_id' => $order->table_id,
            'pax' => $order->pax,
            'sc_count' => $order->sc_count,
            'pwd_count' => $order->pwd_count,
            'sales_by' => Auth::guard('api')->id() ?? $order->waiter_id ?? $order->user_id,
            'pos_id' => $pos->id,
            'store_id' => $pos->store_id,
            'user_id' => $order->user_id,
            'created_at' => $now,
            'updated_at' => $now,
            'profit' => $totalProfit,
            'vatable' => round($vatableTotal, 2),
            'vat' => round($vatTotal, 2),
            'vat_exempt' => round($exemptTotal, 2),
            'zero_rated' => 0,
            'non_vat' => 0,
            'discount' => 0,
            'cancelled' => false,
            'approved_by' => null,
            'sale_id' => 0,
            'sale_type' => false,
            'sc_discount' => 0,
            'pwd_discount' => 0,
            'sp_discount' => 0,
            'naac_discount' => 0,
            'vat_special_discounts' => 0,
            'special_discount_type' => 0,
            'special_discount_name' => null,
            'special_discount_id' => null,
            'special_discount_tin' => null,
            'customer_id' => $payment['customer_id'] ?? null,
            'acquired_points' => $earnedPoints,
            'points_used' => 0,
            'ecommerce_order_id' => null,
            'voucher_id' => null,
            'voucher_code' => null,
            'voucher_discount' => 0,
        ];

        return new self(
            saleAttributes: $saleAttributes,
            saleLineRows: $saleLineRows,
            customer: $customer,
            earnedPoints: (float) $earnedPoints,
            pointsUsed: 0.0,
        );
    }
}
