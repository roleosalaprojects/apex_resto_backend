<?php

namespace App\Http\Controllers\API\v1\pos;

use App\Http\Controllers\Controller;
use App\Http\Requests\ZReading\StoreRequest;
use App\Http\Traits\ApiResponse;
use App\Jobs\API\v1\PosLog\PosLogJob;
use App\Models\Accounting\PosLog;
use App\Models\Pos\Sale;
use App\Models\Pos\Zreading;
use App\Models\Settings\Pos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ZreadingController extends Controller
{
    use ApiResponse;

    // Set Max Sales to double(15,2)
    private float $max_sales = 999999999999999.99;

    /*
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request): JsonResponse
    {
        $readings = Zreading::where('pos_id', $request->pos_id)->with('pos')->get();

        return $this->success(['readings' => $readings]);
    }

    /**
     * Store a newly created resource in storage.
     */

    // This is for Rolworks POS
    public function store(StoreRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $request->validate([
            'cash' => 'required',
            'refunds' => 'required',
            'vatable' => 'required',
            'vat' => 'required',
            'vat_exempt' => 'required',
            'zero_rated' => 'required',
            'current_sales' => 'required',
            'less_refunds' => 'required',
            'transactions' => 'required',
            'sc_discounts' => 'required',
            'pwd_discounts' => 'required',
            'reg_discounts' => 'required',
            'net_sales' => 'required',
            'first_or' => 'nullable',
            'last_or' => 'nullable',
            'pos_id' => 'required',
            'store_id' => 'required',
            'user_id' => 'required',
            'one_thousand' => 'nullable',
            'five_hundred' => 'nullable',
            'two_hundred' => 'nullable',
            'one_hundred' => 'nullable',
            'fifty' => 'nullable',
            'twenty' => 'nullable',
            'ten' => 'nullable',
            'five' => 'nullable',
            'one' => 'nullable',
            'fifty_cents' => 'nullable',
            'twenty_cents' => 'nullable',
            'ten_cents' => 'nullable',
            'five_cents' => 'nullable',
            'one_cents' => 'nullable',
            'total_cash' => 'required',
            'discrepancy' => 'required',
            'excess_vatable' => 'nullable',
            'excess_vat' => 'nullable',
            'excess_non_vat' => 'nullable',
            'cash_in' => 'nullable',
        ]);
        $counter = 0;
        $latestCounter = Zreading::where('pos_id', $request->pos_id)->orderBy('id', 'DESC')->get();
        if (count($latestCounter) > 0) {
            $counter = $latestCounter->first()->counter + 1;
        } else {
            $counter = 1;
        }

        $reading = Zreading::create([
            'counter' => $counter,
            'cash' => $request->cash,
            'refunds' => $request->refunds,
            'vatable' => $request->vatable,
            'vat' => $request->vat,
            'vat_exempt' => $request->vat_exempt,
            'zero_rated' => $request->zero_rated,
            'current_sales' => $request->current_sales,
            'less_refunds' => $request->less_refunds,
            'transactions' => $request->transactions,
            'sc_discounts' => $request->sc_discounts,
            'pwd_discounts' => $request->pwd_discounts,
            'reg_discounts' => $request->reg_discounts,
            'net_sales' => $request->net_sales,

            'first_or' => $request->first_or,
            'last_or' => $request->last_or,
            'pos_id' => $request->pos_id,
            'store_id' => $request->store_id,
            'user_id' => $request->user_id,
            'one_thousand' => $request->one_thousand,
            'five_hundred' => $request->five_hundred,
            'two_hundred' => $request->two_hundred,
            'one_hundred' => $request->one_hundred,
            'fifty' => $request->fifty,
            'twenty' => $request->twenty,
            'ten' => $request->ten,
            'five' => $request->five,
            'one' => $request->one,
            'fifty_cents' => $request->fifty_cents,
            'twenty_cents' => $request->twenty_five_cents,
            'ten_cents' => $request->ten_cents,
            'five_cents' => $request->five_cents,
            'one_cents' => $request->one_cent,
            'total_amount' => $request->total_cash,
            'discrepancy' => $request->discrepancy,
            'excess_vatable' => 0,
            'excess_vat' => 0,
            'excess_non_vat' => 0,
            'cash_in' => $request->cash_in,
        ]);
        // Update Sales Transactions Related to this reading
        $sales = Sale::where('z_reading_id', null)
            ->where('pos_id', $reading->pos_id)
            ->get();
        $salesArray = [];
        foreach ($sales as $sale) {
            $salesArray[] = [
                'id' => $sale->id,
                'z_reading_id' => $reading->id,
            ];
        }
        // Get Cash-Ins logged by the POS
        $user_id = \Auth::user()->id;
        $latestCashIns = PosLog::where('pos_id', $request->pos_id)
            ->where('so_id', null)
            ->where('type', 4)
            ->get();
        $posLogsArray = [];
        foreach ($latestCashIns as $cashIn) {
            $posLogsArray[] = [
                'id' => $cashIn->id,
                'so_id' => $reading->id,
            ];
        }
        // Update Tables
        \Batch::update(new Sale, $salesArray, 'id');
        \Batch::update(new PosLog, $posLogsArray, 'id');

        return $this->success(['zreading' => $reading]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Zreading $zreading, Request $request): JsonResponse
    {
        $zreading = Zreading::where('id', $zreading->id)
            ->with('pos')
            ->first();

        return $this->success($zreading);
    }

    /**
     * Update the specified resource in storage.
     *
     * @return Response
     */
    public function update(Request $request, Zreading $zreading)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @return Response
     */
    public function destroy(Zreading $zreading)
    {
        //
    }

    public function saveZReading(StoreRequest $request, Pos $pos): JsonResponse
    {
        $validated = $request->validated();

        // Get last zreading then increment bt 1 or set counter to 1.
        $last_counter = Zreading::where('pos_id', $request->pos_id)->orderBy('id', 'DESC')->first();
        if ($last_counter == null) {
            $last_counter = 1;
        } else {
            $last_counter = $last_counter->counter + 1;
        }

        /*
         * Override Preset Accumulated Sales Balance and Previous Accumulated Sales Balance
         * if $present_accumulated > $this->max_sales
         * $present_accumulated = $present_accumulated - $this->max_sales;
         * $previous_accumulated = $this->max_sales;
         * */

        if ($validated['present_accumulated_sales'] > $this->max_sales) {
            $validated['present_accumulated_sales'] = $validated['present_accumulated_sales'] - $this->max_sales;
            $validated['previous_accumulated_sales'] = $this->max_sales;
            // increment pos max_sales_reset_counter by 1
            $pos = Pos::where('id', $validated['pos_id'])->first();
            $pos->update(['max_sales_reset_counter' => $pos->max_sales_reset_counter++]);
        }

        $validated['counter'] = $last_counter;
        $validated['z_counter'] = $last_counter;
        $validated['user_id'] = \Auth::user()->id;
        $validated['pos_id'] = $pos->id;
        $validated['store_id'] = $pos->store_id;

        // Server-computed Annex F aggregates over the open (unswept,
        // non-training) sales for this terminal. Voids are reported
        // separately and excluded from gross/tender totals.
        $validated = array_merge($validated, $this->annexFAggregates($pos->id));
        $validated['txn_no'] = app(\App\Services\DocumentNumberService::class)->nextTransactionNumber($pos);

        // Create Z-Reading
        $zreading = Zreading::create($validated);

        // Update Sales Transactions Related to this reading. Training-mode
        // sales are never linked to a z-reading — they stay off the
        // official accumulated-sales roll-forward.
        $sales = Sale::where('z_reading_id', null)
            ->where('pos_id', $zreading->pos_id)
            ->where('is_training', false)
            ->get();
        $salesArray = [];
        foreach ($sales as $sale) {
            $salesArray[] = [
                'id' => $sale->id,
                'z_reading_id' => $zreading->id,
            ];
        }
        // Get Cash-Ins logged by the POS
        $user_id = \Auth::user()->id;
        $latestCashIns = PosLog::where('pos_id', $request->pos_id)
            ->where('so_id', null)
            ->where('type', 4)
            ->get();
        $posLogsArray = [];
        foreach ($latestCashIns as $cashIn) {
            $posLogsArray[] = [
                'id' => $cashIn->id,
                'so_id' => $zreading->id,
            ];
        }

        // Get Cash-Outs logged by the POS (type 12)
        $latestCashOuts = PosLog::where('pos_id', $request->pos_id)
            ->where('so_id', null)
            ->where('type', 12)
            ->get();
        foreach ($latestCashOuts as $cashOut) {
            $posLogsArray[] = [
                'id' => $cashOut->id,
                'so_id' => $zreading->id,
            ];
        }

        // Get Void Cash-Outs logged by the POS (type 13) that reference cash-outs from this session
        $latestVoidCashOuts = PosLog::where('pos_id', $request->pos_id)
            ->where('type', 13)
            ->whereIn('so_id', $latestCashOuts->pluck('id'))
            ->get();
        foreach ($latestVoidCashOuts as $voidCashOut) {
            $posLogsArray[] = [
                'id' => $voidCashOut->id,
                'so_id' => $zreading->id,
            ];
        }

        // Update Tables
        \Batch::update(new Sale, $salesArray, 'id');
        \Batch::update(new PosLog, $posLogsArray, 'id');

        // Sweep open customer credit transactions (payments) into this z-reading
        // so they can't be double-counted on a subsequent reading.
        \DB::table('customer_credit_transactions')
            ->where('pos_id', $zreading->pos_id)
            ->where('type', 'payment')
            ->whereNull('z_reading_id')
            ->update(['z_reading_id' => $zreading->id]);

        // Update $zreading variable to ZReading with pos details to get max_sales_reset_counter & reset_counter for Sales Invoice
        $zreading = Zreading::where('id', $zreading->id)
            ->with('pos')
            ->first();

        // Log Transaction
        PosLogJob::dispatch(
            null,
            null,
            null,
            10,
            'Z-Reading | Counter: '.$zreading->counter.' | Invoice Reset Counter: '.$pos->reset_counter.' | Sales Reset Counter: '.$pos->max_sales_reset_counter,
            null,
            $zreading->store_id,
            $zreading->pos_id,
            $zreading->user_id,
        );

        return $this->success($zreading);
    }

    /**
     * Compute BIR Annex F aggregates over the open, non-training sales for
     * a terminal: void/return document ranges, void totals, per-tender
     * breakdown, and gross sales (voids excluded).
     *
     * @return array<string, mixed>
     */
    private function annexFAggregates(int $posId): array
    {
        $base = Sale::where('pos_id', $posId)
            ->whereNull('z_reading_id')
            ->where('is_training', false);

        $valid = (clone $base)->where('cancelled', false);

        // Multi-tender sales sit under payment_type = PAYMENT_MULTI, so the
        // per-type sums below would skip them; fold their per-tender applied
        // amounts (which sum to each sale's total) into the same buckets.
        $multiTenderTotals = \DB::table('sale_payments')
            ->join('sales', 'sales.id', '=', 'sale_payments.sales_id')
            ->where('sales.pos_id', $posId)
            ->whereNull('sales.z_reading_id')
            ->where('sales.is_training', 0)
            ->where('sales.cancelled', 0)
            ->where('sales.type', 0)
            ->where('sales.payment_type', Sale::PAYMENT_MULTI)
            ->groupBy('sale_payments.payment_type')
            ->selectRaw('sale_payments.payment_type as payment_type, SUM(sale_payments.amount) as amount')
            ->pluck('amount', 'payment_type');

        $tender = fn (int $type) => (float) (clone $valid)
            ->where('type', false)
            ->where('payment_type', $type)
            ->sum('total') + (float) ($multiTenderTotals[$type] ?? 0);

        $voids = (clone $base)->where('cancelled', true)->where('type', false);
        $returns = (clone $base)->where('type', true);

        return [
            'gross_sales' => round((float) (clone $valid)->where('type', false)->sum('total'), 2),
            'cheque' => round($tender(Sale::PAYMENT_CHEQUE), 2),
            'card' => round($tender(Sale::PAYMENT_CARD), 2),
            'gift_cert' => round($tender(Sale::PAYMENT_GIFT_CERT), 2),
            'bank_transfer' => round($tender(Sale::PAYMENT_BANK_TRANSFER), 2),
            'void_amount' => round((float) (clone $voids)->sum('total'), 2),
            'void_count' => (clone $voids)->count(),
            'first_void_no' => (clone $voids)->min('void_no'),
            'last_void_no' => (clone $voids)->max('void_no'),
            'first_return_no' => (clone $returns)->min('return_no'),
            'last_return_no' => (clone $returns)->max('return_no'),
        ];
    }
}
