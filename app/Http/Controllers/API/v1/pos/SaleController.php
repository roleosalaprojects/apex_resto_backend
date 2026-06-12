<?php

namespace App\Http\Controllers\API\v1\pos;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\v1\mobile\Sale\RefundRequest;
use App\Http\Requests\API\v1\pos\Sale\StoreRequest;
use App\Http\Resources\SaleResource;
use App\Http\Traits\ApiResponse;
use App\Jobs\API\v1\PosLog\PosLogJob;
use App\Models\CustomerRelations\Customer;
use App\Models\CustomerRelations\CustomerCreditTransaction;
use App\Models\Pos\Sale;
use App\Models\Pos\SaleLine;
use App\Models\Settings\Pos;
use App\Services\Data\SaleCreationData;
use App\Services\FcmService;
use App\Services\SaleCreationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SaleController extends Controller
{
    use ApiResponse;

    private int $max_counter = 999999999999999;

    public function __construct(private SaleCreationService $saleCreation) {}

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage (validated via StoreRequest).
     */
    public function store(StoreRequest $request): JsonResponse
    {
        return $this->processSale($request);
    }

    /**
     * Process a sale from either a validated store request or an internal refund request.
     *
     * Counter math, header/footer copy, line normalization, points calc,
     * Sale + SaleLine writes, customer-points update, credit ledger, and
     * stock-deduction dispatch all live in SaleCreationService now so the
     * upcoming admin "Record Payment" flow shares the same pipeline.
     * What stays here is POS-only: counter computation (per pos_id),
     * PosLog dispatch, large-sale FCM notify, and response shaping.
     */
    private function processSale(Request $request): JsonResponse
    {
        $pos = Pos::with('store')->find($request->pos_id);
        [$counter, $sonType] = $this->computeCounter($pos, $request);

        $data = SaleCreationData::fromPosRequest($request, $pos, $counter, $sonType);
        $sale = $this->saleCreation->create($data);

        $sale->load([
            'lines.item:id,name,type',
            'pos',
            'customer',
            'store',
            'sold_by',
            'bank',
            'refund',
        ]);

        $reason = ! $sale->type ? 'Sale' : 'Refund';
        $reason .= ' - Invoice #:'.$sale->son.' sold by: '.$sale->sold_by->name;
        PosLogJob::dispatch(
            0,
            0,
            $sale->total,
            ! $sale->type ? 5 : 6,
            $reason,
            $sale->id,
            $sale->pos_id,
            $sale->store_id,
            $sale->sales_by
        );

        $this->notifyLargeSaleOrRefund($sale);

        return $this->success([
            'saleOrder' => new SaleResource($sale),
        ]);
    }

    /**
     * Returns [int $counter, int|string $sonType] for a POS sale.
     *
     * Sale (type=false): counter is the latest POS counter + 1, starting
     * at 100000 and rolling the pos.reset_counter when it overflows.
     * Refund (sale_id set): counter is the latest refund counter + 1,
     * starting at 1000000. sonType is 'R' so the invoice number reads
     * "R-counter-posId" instead of "{resetCounter}-counter-posId".
     *
     * @return array{0: int, 1: int|string}
     */
    private function computeCounter(Pos $pos, Request $request): array
    {
        $resetCounter = $pos->reset_counter;
        $counter = 0;

        if ($request->sale_id != null) {
            $latestSale = Sale::where('pos_id', $request->pos_id)->where('type', true)->latest()->first();
            $counter = $latestSale ? $latestSale->counter + 1 : 1000000;
        } else {
            $latestSale = Sale::where('pos_id', $request->pos_id)->where('type', false)->latest()->first();
            if ($latestSale) {
                if ($latestSale->counter == $this->max_counter) {
                    $pos->update(['reset_counter' => $pos->reset_counter + 1]);
                    $resetCounter += 1;
                    $counter = 100000;
                } else {
                    $counter = $latestSale->counter + 1;
                }
            } else {
                $counter = 100000;
            }
        }

        $sonType = $request->sale_id ? 'R' : $resetCounter;

        return [$counter, $sonType];
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Sale  $sale
     * @return \Illuminate\Http\Response
     */
    public function show(Sale $sale)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Sale  $sale
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Sale $sale)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Sale  $sale
     * @return \Illuminate\Http\Response
     */
    public function destroy(Sale $sale)
    {
        //
    }

    public function getReceiptsByPos(Pos $pos, Request $request): JsonResponse
    {
        $startDate = Carbon::parse($request->startDate)->startOfDay()->toDateTimeString();
        $endDate = Carbon::parse($request->endDate)->endOfDay()->toDateTimeString();
        $sales = Sale::where('pos_id', $pos->id)
            ->with([
                'sold_by',
                'lines' => function ($lines) {
                    $lines->with('item');
                },
                'pos',
                'store',
                'refund' => function ($refund) {
                    $refund->select(['id', 'son']);
                },
            ])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('id', 'DESC')
            ->get();

        return $this->success([
            'sales' => SaleResource::collection($sales),
            'dates' => [$startDate, $endDate],
        ]);
    }

    private function notifyLargeSaleOrRefund(Sale $sale): void
    {
        try {
            $storeName = $sale->store?->name ?? 'Unknown store';
            $amount = number_format($sale->total, 2);

            if ($sale->type) {
                // Refund
                $threshold = config('notifications.large_refund_threshold', 5000);
                if ($sale->total >= $threshold) {
                    app(FcmService::class)->sendToUsersWithPermission(
                        $sale->user_id,
                        'sls',
                        'Refund Alert',
                        "Refund alert: P{$amount} at {$storeName}",
                        ['type' => 'refund_alert', 'id' => (string) $sale->id]
                    );
                }
            } else {
                // Sale
                $threshold = config('notifications.large_sale_threshold', 10000);
                if ($sale->total >= $threshold) {
                    app(FcmService::class)->sendToUsersWithPermission(
                        $sale->user_id,
                        'sls',
                        'Large Sale',
                        "Large sale: P{$amount} at {$storeName}",
                        ['type' => 'large_sale', 'id' => (string) $sale->id]
                    );
                }
            }
        } catch (\Exception $e) {
            Log::warning('FCM notification failed for large sale/refund: '.$e->getMessage());
        }
    }

    public function refundReceipt(RefundRequest $request, Sale $sale)
    {
        $validated = $request->validated();
        $refundLine = $validated['line'];
        $forSaleLines = [];
        // create new sale details
        $total = 0;
        $profit = 0;
        $refundVatable = 0;
        $refundVat = 0;
        $refundVatExempt = 0;
        $refundZeroRated = 0;
        $refundScDiscount = 0;
        $refundPwdDiscount = 0;
        $refundSpDiscount = 0;
        $refundNaacDiscount = 0;
        $refundVatSpecialDiscounts = 0;
        foreach ($refundLine as $index => $line) {
            $saleLine = SaleLine::where('id', $line['product']['id'])
                ->with([
                    'item',
                    'unit',
                ])
                ->first();

            $profit += $line['qty'] * ($saleLine->price - $saleLine->cost);
            $total += $line['qty'] * ($saleLine->price - $saleLine->discount);
            // Sum per-line VAT components scaled by refunded qty so the
            // sale-level totals reflect the actual refunded amount, not
            // the original sale's full totals.
            $refundVatable += $line['qty'] * ($saleLine->vatable ?? 0);
            $refundVat += $line['qty'] * ($saleLine->vat ?? 0);
            $refundVatExempt += $line['qty'] * ($saleLine->exempt ?? 0);
            $refundZeroRated += $line['qty'] * ($saleLine->zero_rated ?? 0);
            $refundScDiscount += $line['qty'] * ($saleLine->sc_discount ?? 0);
            $refundPwdDiscount += $line['qty'] * ($saleLine->pwd_discount ?? 0);
            $refundSpDiscount += $line['qty'] * ($saleLine->sp_discount ?? 0);
            $refundNaacDiscount += $line['qty'] * ($saleLine->naac_discount ?? 0);
            $refundVatSpecialDiscounts += $line['qty'] * ($saleLine->vat_special_discounts ?? 0);
            $forSaleLines[] = [
                'qty' => $line['qty'],
                'product' => $saleLine->item,
                'unit_id' => $saleLine->unit_id,
                'unit_qty' => $saleLine->unit_qty,
                'discount' => $saleLine->discount,
                'cost' => $saleLine->cost,
                'price' => $saleLine->price,
                'unit' => $saleLine->unit,
                'profit' => $saleLine->profit,
                'sc_discount' => $saleLine->sc_discount,
                'pwd_discount' => $saleLine->pwd_discount,
                'sp_discount' => $saleLine->sp_discount,
                'naac_discount' => $saleLine->naac_discount,
                'vatable' => $saleLine->vatable,
                'vat' => $saleLine->vat,
                'vat_exempt' => $saleLine->vat_exempt,
                'zero_rated' => $saleLine->zero_rated,
                'vat_special_discounts' => $saleLine->vat_special_discounts,
            ];

            // Update SaleLine Row
            $refundable = $saleLine->refundable;
            $refundedCount = $saleLine->refunded;
            $saleLine->update([
                'refundable' => $refundable - $line['qty'],
                'refunded' => $refundedCount + $line['qty'],
            ]);
        }
        // Deduct points from Customer
        $customer = Customer::where('id', $sale->customer_id)
            ->first();
        $customer?->update([
            'accumulated_points' => $customer->accumulated_points - $sale->acquired_points,
        ]);
        // Create another sale with refund status = true.
        $refundData = [
            'details' => [
                'cash' => 0,
                'change' => 0,
                'total' => $total,
                'profit' => -$profit,
                'customer_id' => $sale->customer_id,
                // Make acquired points so that it will be deducted from the customer.
                'points' => -$sale->acquired_points,
                // BIR Details (scaled to the actual refunded quantity, not the original sale total)
                'vatable' => round($refundVatable, 2),
                'vat' => round($refundVat, 2),
                'vat_exempt' => round($refundVatExempt, 2),
                'zero_rated' => round($refundZeroRated, 2),
                // Special Discounts (scaled to the actual refunded quantity)
                'sc_discount' => round($refundScDiscount, 2),
                'pwd_discount' => round($refundPwdDiscount, 2),
                'sp_discount' => round($refundSpDiscount, 2),
                'naac_discount' => round($refundNaacDiscount, 2),
                'regular_discount' => $sale->regular_discount,
                'vat_special_discounts' => round($refundVatSpecialDiscounts, 2),
                'discount' => $sale->discount,
                'discount_type' => $sale->discount_type,
                // Special Discount Details
                'special_discount_type' => $sale->special_discount_type,
                'special_discount_name' => $sale->special_discount_name ?? '',
                'special_discount_id' => $sale->special_discount_id ?? '',
                'special_discount_tin' => $sale->special_discount_tin ?? '',
                'special_discount_child_name' => $sale->special_discount_child_name ?? '',
                'special_discount_child_birthdate' => $sale->special_discount_child_birthdate ?? '',
                'special_discount_child_age' => $sale->special_discount_child_age ?? '',
                'profit' => $sale->profit,
                // Bank E-Wallet Payments
                'payment_type' => $sale->payment_type,
                'reference_number' => $sale->reference_number,
                'bank_amount' => $sale->bank_amount,
                'bank_id' => $sale->bank_id,
                // Voucher Details
                'voucher_id' => $sale->voucher_id,
                'voucher_code' => $sale->voucher_code,
                'voucher_discount' => $sale->voucher_discount ?? 0,
            ],
            'pos_id' => $sale->pos_id,
            'line' => $forSaleLines,
            // make type true to set the sale type to refund.
            'type' => true,
            // to reference the sale that was refunded.
            'sale_id' => $sale->id,
        ];

        $response = $this->processSale(new Request($refundData));

        // Reverse credit balance if original sale was a credit sale
        if ($sale->payment_type == 3 && $customer) {
            DB::transaction(function () use ($sale, $customer, $total) {
                $customer->lockForUpdate();
                $customer->refresh();
                $newBalance = $customer->credit_balance - $total;
                $customer->update(['credit_balance' => max(0, $newBalance)]);

                CustomerCreditTransaction::create([
                    'customer_id' => $customer->id,
                    'type' => 'reversal',
                    'amount' => $total,
                    'balance_after' => max(0, $newBalance),
                    'reference_type' => 'refund',
                    'reference_id' => $sale->id,
                    'pos_id' => $sale->pos_id,
                    'store_id' => $sale->store_id,
                    'user_id' => Auth::guard('api')->id(),
                ]);
            });
        }

        return $response;
    }
}
