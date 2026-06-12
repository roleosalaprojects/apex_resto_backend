<?php

namespace App\Http\Controllers\API\v1\pos;

use App\Http\Controllers\Controller;
use App\Http\Requests\ShiftReading\StoreRequest;
use App\Http\Traits\ApiResponse;
use App\Jobs\API\v1\PosLog\PosLogJob;
use App\Models\Accounting\PosLog;
use App\Models\Employees\ShiftReading;
use App\Models\Pos\Sale;
use App\Models\Pos\Zreading;
use App\Models\Settings\Pos;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ShiftReadingController extends Controller
{
    use ApiResponse;

    private float $max_sales = 999999999999999.99;

    /**
     * Save a shift reading (denomination per shift).
     * If is_store_closure=true, also auto-generate Z-Reading.
     */
    public function save(StoreRequest $request, Pos $pos): JsonResponse
    {
        $validated = $request->validated();

        $validated['user_id'] = \Auth::user()->id;
        $validated['pos_id'] = $pos->id;
        $validated['store_id'] = $pos->store_id;

        $shiftReading = ShiftReading::create($validated);

        // Log shift reading
        PosLogJob::dispatch(
            null,
            null,
            null,
            14, // New log type for shift reading
            'Shift Reading | User: '.\Auth::user()->name.' | Shift ID: '.$shiftReading->id,
            null,
            $shiftReading->store_id,
            $shiftReading->pos_id,
            $shiftReading->user_id,
        );

        // Link current user's unlinked pos_logs to this shift reading
        $userId = \Auth::user()->id;

        // Cash-in (type 4)
        PosLog::where('pos_id', $pos->id)
            ->where('user_id', $userId)
            ->where('type', 4)
            ->whereNull('so_id')
            ->whereNull('shift_reading_id')
            ->update(['shift_reading_id' => $shiftReading->id]);

        // Cash-out (type 12)
        $cashOutIds = PosLog::where('pos_id', $pos->id)
            ->where('user_id', $userId)
            ->where('type', 12)
            ->whereNull('so_id')
            ->whereNull('shift_reading_id')
            ->pluck('id');

        PosLog::whereIn('id', $cashOutIds)
            ->update(['shift_reading_id' => $shiftReading->id]);

        // Void cash-out (type 13) that reference the cash-outs above
        PosLog::where('pos_id', $pos->id)
            ->where('user_id', $userId)
            ->where('type', 13)
            ->whereIn('so_id', $cashOutIds)
            ->update(['shift_reading_id' => $shiftReading->id]);

        // Sweep open customer credit payments for this user/POS into the shift,
        // so the next shift reading doesn't re-count them.
        \DB::table('customer_credit_transactions')
            ->where('pos_id', $pos->id)
            ->where('user_id', $userId)
            ->where('type', 'payment')
            ->whereNull('shift_reading_id')
            ->update(['shift_reading_id' => $shiftReading->id]);

        $responseData = ['shift_reading' => $shiftReading];

        // If store closure, auto-generate Z-Reading
        if ($request->boolean('is_store_closure')) {
            $zreading = $this->generateZReading($request, $pos, $shiftReading);
            if ($zreading) {
                // Link this shift reading to the Z-Reading
                $shiftReading->update(['z_reading_id' => $zreading->id]);

                // Also link any other unlinked shift readings from today to this Z-Reading
                ShiftReading::where('pos_id', $pos->id)
                    ->whereNull('z_reading_id')
                    ->where('id', '!=', $shiftReading->id)
                    ->update(['z_reading_id' => $zreading->id]);

                $responseData['z_reading'] = $zreading;
            }
        }

        return $this->success($responseData);
    }

    /**
     * Auto-generate Z-Reading using the same logic as ZreadingController::saveZReading()
     * but without denomination (each shift has its own denomination).
     */
    private function generateZReading(StoreRequest $request, Pos $pos, ShiftReading $shiftReading): ?Zreading
    {
        $posId = $pos->id;

        // Generate reading data (same SQL as XreadingController::apexReading)
        $reading = DB::select(
            'SELECT count(id) as transactions, '.
            'ROUND(sum(if(type = 0, if(payment_type = 1, total, 0), 0)), 2) as cash,'.
            'ROUND(sum(if(type = 0, if(payment_type = 2, total, 0), 0)), 2) as e_wallet,'.
            'ROUND(sum(if(type = 1, total, 0)), 2) as refund,'.
            'ROUND(sum(if(type = 1, vat, 0)), 2) as vat_on_refunds,'.
            'ROUND(sum(if(sale_type = 0, total, -total)), 2) as net_sales,'.
            'ROUND(sum(if(sale_type = 0, vatable, -vatable)), 2) as vatable,'.
            'ROUND(sum(if(sale_type = 0, vat, -vat)), 2) as vat,'.
            'ROUND(sum(if(sale_type = 0, vat_exempt, -vat_exempt)), 2) as vat_exempt,'.
            'ROUND(sum(if(sale_type = 0, zero_rated, -zero_rated)), 2) as zero_rated,'.
            'ROUND(sum(if(sale_type = 0, discount, -discount)), 2) as reg_discount,'.
            'ROUND(sum(if(sale_type = 0, sc_discount, -sc_discount)), 2) as sc_discount,'.
            'ROUND(sum(if(sale_type = 0, pwd_discount, -pwd_discount)), 2) as pwd_discount, '.
            'ROUND(sum(if(sale_type = 0, sp_discount, -sp_discount)), 2) as solo_parent_discount, '.
            'ROUND(sum(if(sale_type = 0, naac_discount, -naac_discount)), 2) as naac_discount, '.
            'ROUND(sum(if(sale_type = 0, vat_special_discounts, -vat_special_discounts)), 2) as vat_special_discounts, '.
            'ROUND(sum(if(special_discount_type = 1, vat_special_discounts, -0)), 2) as sc_vat_adjustment, '.
            'ROUND(sum(if(special_discount_type = 2, vat_special_discounts, -0)), 2) as pwd_vat_adjustment, '.
            'ROUND(sum(if(special_discount_type = 3, vat_special_discounts, -0)), 2) as sp_vat_adjustment, '.
            'ROUND(sum(if(special_discount_type = 4, vat_special_discounts, -0)), 2) as naac_vat_adjustment, '.
            'SUM(case WHEN special_discount_type = 1 THEN 1 ELSE 0 END) as sc_transactions, '.
            'SUM(case WHEN special_discount_type = 2 THEN 1 ELSE 0 END) as pwd_transactions, '.
            'SUM(case WHEN special_discount_type = 3 THEN 1 ELSE 0 END) as sp_transactions, '.
            'SUM(case WHEN special_discount_type = 4 THEN 1 ELSE 0 END) as naac_transactions, '.
            'SUM(case WHEN special_discount_type = 0 THEN IF(discount > 0, 1, 0) ELSE 0 END) as reg_disc_transactions, '.
            'son.min_son as first_or, '.
            'son.max_son as last_or, '.
            'refund.min_son as refund_first_or, '.
            'refund.max_son as refund_last_or, '.
            '(select created_at from sales where id = son.min_id) as begin_date, '.
            '(select created_at from sales where id = son.max_id) as end_date '.
            'FROM sales, '.
            '(select max(son) as max_son, min(son) as min_son, max(id) as max_id, min(id) as min_id from sales WHERE created_at AND pos_id = ? AND type = 0 AND z_reading_id IS NULL) son, '.
            '(select max(son) as max_son, min(son) as min_son, max(id) as max_id, min(id) as min_id from sales WHERE created_at AND pos_id = ? AND type = 1 AND z_reading_id IS NULL) refund '.
            'WHERE pos_id = ? AND z_reading_id IS NULL',
            [$posId, $posId, $posId]
        );

        $readingData = $reading[0] ?? null;

        // Get previous accumulated sales
        $previous_accumulated_sales = 0;
        $zreadingLatest = Zreading::where('pos_id', $pos->id)->latest()->first();
        if ($zreadingLatest) {
            $previous_accumulated_sales = round($zreadingLatest->present_accumulated_sales, 2);
        }

        // Get Z-Reading counter
        $last_counter = Zreading::where('pos_id', $pos->id)->orderBy('id', 'DESC')->first();
        $counter = $last_counter ? $last_counter->counter + 1 : 1;

        // Calculate net sales
        $net_sales = $readingData ? round(floatval($readingData->net_sales ?? 0), 2) : 0;
        $present_accumulated_sales = $net_sales + $previous_accumulated_sales;

        // Max sales overflow check
        if ($present_accumulated_sales > $this->max_sales) {
            $present_accumulated_sales = $present_accumulated_sales - $this->max_sales;
            $previous_accumulated_sales = $this->max_sales;
            $pos->update(['max_sales_reset_counter' => $pos->max_sales_reset_counter + 1]);
        }

        // Sum transaction summary and denomination from ALL unlinked shift readings for this POS
        $shiftTotals = ShiftReading::where('pos_id', $pos->id)
            ->whereNull('z_reading_id')
            ->selectRaw(
                'SUM(cash_in) as total_cash_in, SUM(cash_out) as total_cash_out, '
                .'SUM(cash_sales) as total_cash_sales, SUM(e_wallet_sales) as total_e_wallet_sales, '
                .'SUM(refunds) as total_refunds, SUM(net_sales) as total_net_sales, '
                .'SUM(total_cash) as total_cash_in_drawer, '
                .'SUM(one_thousand) as total_one_thousand, SUM(five_hundred) as total_five_hundred, '
                .'SUM(two_hundred) as total_two_hundred, SUM(one_hundred) as total_one_hundred, '
                .'SUM(fifty) as total_fifty, SUM(twenty) as total_twenty, '
                .'SUM(ten) as total_ten, SUM(five) as total_five, '
                .'SUM(one) as total_one, SUM(centavos) as total_centavos, '
                .'SUM(denomination) as total_denomination'
            )
            ->first();

        $totalCashIn = $shiftTotals->total_cash_in ?? 0;
        $totalCashOut = $shiftTotals->total_cash_out ?? 0;
        $totalDenomination = $shiftTotals->total_denomination ?? 0;

        // Calculate Z-Reading discrepancy from summed denomination
        $totalCashSales = round(floatval($shiftTotals->total_cash_sales ?? 0), 2);
        $totalRefunds = round(floatval($shiftTotals->total_refunds ?? 0), 2);
        $expectedCash = $totalCashSales - $totalRefunds + $totalCashIn - $totalCashOut;
        $discrepancy = round($totalDenomination - $expectedCash, 2);

        // Build Z-Reading data with denomination summed from all shift readings
        $zreadingData = [
            'counter' => $counter,
            'user_id' => \Auth::user()->id,
            'pos_id' => $pos->id,
            'store_id' => $pos->store_id,
            'transactions' => $readingData->transactions ?? 0,
            'cash' => $totalCashSales,
            'e_wallet' => round(floatval($shiftTotals->total_e_wallet_sales ?? 0), 2),
            'cash_in' => $totalCashIn,
            'cash_out' => $totalCashOut,
            'refund' => $totalRefunds,
            'vat_on_refunds' => $readingData->vat_on_refunds ?? 0,
            'net_sales' => $net_sales,
            'vatable' => $readingData->vatable ?? 0,
            'vat' => $readingData->vat ?? 0,
            'vat_exempt' => $readingData->vat_exempt ?? 0,
            'zero_rated' => $readingData->zero_rated ?? 0,
            'reg_discount' => $readingData->reg_discount ?? 0,
            'sc_discount' => $readingData->sc_discount ?? 0,
            'pwd_discount' => $readingData->pwd_discount ?? 0,
            'solo_parent_discount' => $readingData->solo_parent_discount ?? 0,
            'naac_discount' => $readingData->naac_discount ?? 0,
            'vat_special_discounts' => $readingData->vat_special_discounts ?? 0,
            'sc_vat_adjustment' => $readingData->sc_vat_adjustment ?? 0,
            'pwd_vat_adjustment' => $readingData->pwd_vat_adjustment ?? 0,
            'sp_vat_adjustment' => $readingData->sp_vat_adjustment ?? 0,
            'naac_vat_adjustment' => $readingData->naac_vat_adjustment ?? 0,
            'sc_transactions' => $readingData->sc_transactions ?? 0,
            'pwd_transactions' => $readingData->pwd_transactions ?? 0,
            'sp_transactions' => $readingData->sp_transactions ?? 0,
            'naac_transactions' => $readingData->naac_transactions ?? 0,
            'reg_disc_transactions' => $readingData->reg_disc_transactions ?? 0,
            'first_or' => $readingData->first_or ?? 'N/A',
            'last_or' => $readingData->last_or ?? 'N/A',
            'refund_first_or' => $readingData->refund_first_or ?? 'N/A',
            'refund_last_or' => $readingData->refund_last_or ?? 'N/A',
            'begin_date' => $readingData->begin_date ?? now(),
            'end_date' => $readingData->end_date ?? now(),
            'previous_accumulated_sales' => $previous_accumulated_sales,
            'present_accumulated_sales' => $present_accumulated_sales,
            'one_thousand' => $shiftTotals->total_one_thousand ?? 0,
            'five_hundred' => $shiftTotals->total_five_hundred ?? 0,
            'two_hundred' => $shiftTotals->total_two_hundred ?? 0,
            'one_hundred' => $shiftTotals->total_one_hundred ?? 0,
            'fifty' => $shiftTotals->total_fifty ?? 0,
            'twenty' => $shiftTotals->total_twenty ?? 0,
            'ten' => $shiftTotals->total_ten ?? 0,
            'five' => $shiftTotals->total_five ?? 0,
            'one' => $shiftTotals->total_one ?? 0,
            'centavos' => $shiftTotals->total_centavos ?? 0,
            'total_cash' => round(floatval($shiftTotals->total_cash_in_drawer ?? 0), 2),
            'denomination' => $totalDenomination,
            'discrepancy' => $discrepancy,
        ];

        $zreading = Zreading::create($zreadingData);

        // Link sales to Z-Reading
        $sales = Sale::where('z_reading_id', null)
            ->where('pos_id', $pos->id)
            ->get();
        $salesArray = [];
        foreach ($sales as $sale) {
            $salesArray[] = [
                'id' => $sale->id,
                'z_reading_id' => $zreading->id,
            ];
        }

        // Link cash-in pos_logs (type 4)
        $posLogsArray = [];
        $latestCashIns = PosLog::where('pos_id', $pos->id)
            ->where('so_id', null)
            ->where('type', 4)
            ->get();
        foreach ($latestCashIns as $cashIn) {
            $posLogsArray[] = [
                'id' => $cashIn->id,
                'so_id' => $zreading->id,
            ];
        }

        // Link cash-out pos_logs (type 12)
        $latestCashOuts = PosLog::where('pos_id', $pos->id)
            ->where('so_id', null)
            ->where('type', 12)
            ->get();
        foreach ($latestCashOuts as $cashOut) {
            $posLogsArray[] = [
                'id' => $cashOut->id,
                'so_id' => $zreading->id,
            ];
        }

        // Link void cash-out pos_logs (type 13)
        $latestVoidCashOuts = PosLog::where('pos_id', $pos->id)
            ->where('type', 13)
            ->whereIn('so_id', $latestCashOuts->pluck('id'))
            ->get();
        foreach ($latestVoidCashOuts as $voidCashOut) {
            $posLogsArray[] = [
                'id' => $voidCashOut->id,
                'so_id' => $zreading->id,
            ];
        }

        // Batch update
        if (count($salesArray) > 0) {
            \Batch::update(new Sale, $salesArray, 'id');
        }
        if (count($posLogsArray) > 0) {
            \Batch::update(new PosLog, $posLogsArray, 'id');
        }

        // Reload with pos details
        $zreading = Zreading::where('id', $zreading->id)
            ->with('pos')
            ->first();

        // Log Z-Reading closure
        PosLogJob::dispatch(
            null,
            null,
            null,
            10,
            'Z-Reading (via Shift Closure) | Counter: '.$zreading->counter.' | Invoice Reset Counter: '.$pos->reset_counter.' | Sales Reset Counter: '.$pos->max_sales_reset_counter,
            null,
            $zreading->store_id,
            $zreading->pos_id,
            $zreading->user_id,
        );

        return $zreading;
    }
}
