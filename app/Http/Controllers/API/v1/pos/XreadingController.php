<?php

namespace App\Http\Controllers\API\v1\pos;

use App\Http\Controllers\Controller;
use App\Http\Requests\XReading\StoreRequest;
use App\Http\Traits\ApiResponse;
use App\Jobs\API\v1\PosLog\PosLogJob;
use App\Models\Accounting\PosLog;
use App\Models\Pos\Xreading;
use App\Models\Pos\Zreading;
use App\Models\Settings\Pos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class XreadingController extends Controller
{
    use ApiResponse;

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index() {}

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    public function show(Xreading $xreading): JsonResponse
    {
        return $this->success($xreading);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Xreading  $xreading
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Xreading $xreading)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Xreading  $xreading
     * @return \Illuminate\Http\Response
     */
    public function destroy(Xreading $xreading)
    {
        //
    }

    // This is for Rolworks POS
    public function generateReading(Pos $pos)
    {
        $posId = $pos->id;
        $reading = DB::select(
            'SELECT count(id) as transactions,'.
            'sum(if(type = 0, if(payment_type = 1, total, 0), 0)) as cash,'.
            'sum(if(type = 0, if(payment_type = 2, total, 0), 0)) as e_wallet,'.
            'sum(if(type = 1, total, 0)) as refund,'.
            'sum(if(type = 0, vatable, -vatable)) as vatable,'.
            'sum(if(type = 0, non_vat, -non_vat)) as non_vat,'.
            'sum(if(type = 0, vat, -vat)) as vat,'.
            'sum(if(type = 0, vat_exempt, -vat_exempt)) as vat_exempt,'.
            'sum(if(type = 0, zero_rated, -zero_rated)) as zero_rated,'.
            "sum(if(sale_type = '', discount, 0)) as reg_discount,".
            "sum(if(sale_type = 'senior', discount, 0)) as sc_discount,".
            "sum(if(sale_type = 'pwd', discount, 0)) as pwd_discount, ".
            'sum(excess_vat) as excess_vat,'.
            'sum(excess_non_vat) as excess_non_vat,'.
            'son.min_son as first_or, '.
            'son.max_son as last_or '.
            'FROM sales, '.
            '(select max(son) as max_son, min(son) as min_son from sales WHERE created_at AND pos_id = ? AND z_reading_id IS NULL) son '.
            'WHERE pos_id = ? AND z_reading_id IS NULL',
            [$posId, $posId]
        );
        //        $ciq = DB::select("SELECT * from pos_logs WHERE pos_id = $pos->id AND type = 3 AND so_id = 0 AND store_id = $pos->store_id");
        // ciq = Cash-In Query
        $ciq = PosLog::where('pos_id', $pos->id)
            ->where('so_id', null)
            ->where('type', '4')
            ->where('store_id', $pos->store_id)
            ->get();

        return $this->success([
            'reading' => $reading,
            'cash_in' => $ciq,
        ]);
    }

    // This is for Apex POS
    public function apexReading(Request $request, Pos $pos)
    {
        $posId = $pos->id;

        // When user_scope=1, filter sales/cash-in/cash-out by current user (for shift reading)
        $userScope = $request->user_scope ? true : false;
        $userId = \Auth::user()->id;
        $userFilter = $userScope ? 'AND sales_by = ?' : '';
        $userParams = $userScope ? [$userId] : [];

        $reading = DB::select(
            'SELECT count(id) as transactions, '.
            'ROUND(sum(if(type = 0, if(payment_type = 1, total, 0), 0)), 2) as cash,'.
            'ROUND(sum(if(type = 0, if(payment_type = 2, total, 0), 0)), 2) as e_wallet,'.
            'ROUND(sum(if(type = 0, if(payment_type = 3, total, 0), 0)), 2) as credit_sales,'.
            'ROUND(sum(if(type = 1, total, 0)), 2) as refund,'.
            'ROUND(sum(if(type = 1, vat, 0)), 2) as vat_on_refunds,'.
            'ROUND(sum(if(type = 1, vatable, 0)), 2) as vatable_on_refunds,'.
            'ROUND(sum(if(type = 1, vat_exempt, 0)), 2) as vat_exempt_on_refunds,'.
            'ROUND(sum(if(type = 1, zero_rated, 0)), 2) as zero_rated_on_refunds,'.
            'ROUND(sum(if(sale_type = 0, total, -total)), 2) as net_sales,'.
            'ROUND(sum(if(sale_type = 0, vatable, 0)), 2) as vatable,'.
            'ROUND(sum(if(sale_type = 0, vat, 0)), 2) as vat,'.
            'ROUND(sum(if(sale_type = 0, vat_exempt, 0)), 2) as vat_exempt,'.
            'ROUND(sum(if(sale_type = 0, zero_rated, 0)), 2) as zero_rated,'.
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
            "(select max(son) as max_son, min(son) as min_son, max(id) as max_id, min(id) as min_id from sales WHERE created_at AND pos_id = ? AND type = 0 AND z_reading_id IS NULL $userFilter) son, ".
            "(select max(son) as max_son, min(son) as min_son, max(id) as max_id, min(id) as min_id from sales WHERE created_at AND pos_id = ? AND type = 1 AND z_reading_id IS NULL $userFilter) refund ".
            "WHERE pos_id = ? AND z_reading_id IS NULL $userFilter",
            array_merge([$posId], $userParams, [$posId], $userParams, [$posId], $userParams)
        );
        $previous_accumulated_sales = 0;
        if ($request->type) {
            $zreadingLatest = Zreading::where('pos_id', $pos->id)->latest()->first();
            if ($zreadingLatest) {
                $previous_accumulated_sales = round(Zreading::where('pos_id', $pos->id)->latest()->first()->present_accumulated_sales, 2);
            }
        }
        // ciq = Cash-In Query
        $ciq = PosLog::where('pos_id', $pos->id)
            ->where('so_id', null)
            ->where('type', '4')
            ->where('store_id', $pos->store_id)
            ->whereNull('shift_reading_id')
            ->when($userScope, fn ($q) => $q->where('user_id', $userId))
            ->get();

        // coq = Cash-Out Query (type 12, unlinked to z-reading)
        $coq = PosLog::where('pos_id', $pos->id)
            ->where('so_id', null)
            ->where('type', 12)
            ->where('store_id', $pos->store_id)
            ->whereNull('shift_reading_id')
            ->when($userScope, fn ($q) => $q->where('user_id', $userId))
            ->get();

        // Voided cash-out IDs (type 13)
        $voidedCashOutIds = PosLog::where('pos_id', $pos->id)
            ->where('store_id', $pos->store_id)
            ->where('type', 13)
            ->whereNotNull('so_id')
            ->whereNull('shift_reading_id')
            ->when($userScope, fn ($q) => $q->where('user_id', $userId))
            ->pluck('so_id')
            ->toArray();

        // Credit sales detail (payment_type = 3) within this open reading,
        // so the Z/X-Reading print can list which customers took credit.
        $creditSalesQuery = DB::table('sales')
            ->leftJoin('customers', 'customers.id', '=', 'sales.customer_id')
            ->where('sales.pos_id', $posId)
            ->where('sales.type', 0)
            ->where('sales.payment_type', 3)
            ->whereNull('sales.z_reading_id')
            ->when($userScope, fn ($q) => $q->where('sales.sales_by', $userId))
            ->select(
                'sales.id',
                'sales.son',
                'sales.total',
                'sales.created_at',
                'sales.customer_id',
                'customers.name as customer_name'
            )
            ->orderBy('sales.id', 'asc')
            ->get();

        // Credit payments collected since last close-out. We track per-method
        // so the reading can fold cash payments into cash-in-drawer and report
        // the rest under their own collection lines. All column refs are
        // fully qualified because the detail query joins customers + banks
        // which both expose their own user_id / id columns.
        $creditPaymentsBase = DB::table('customer_credit_transactions')
            ->where('customer_credit_transactions.pos_id', $posId)
            ->where('customer_credit_transactions.type', 'payment')
            ->when(
                $userScope,
                fn ($q) => $q->where(
                    'customer_credit_transactions.user_id',
                    $userId,
                ),
            );
        $scopeColumn = $userScope
            ? 'customer_credit_transactions.shift_reading_id'
            : 'customer_credit_transactions.z_reading_id';
        $creditPaymentsBase->whereNull($scopeColumn);

        $creditPaymentsDetail = (clone $creditPaymentsBase)
            ->leftJoin('customers', 'customers.id', '=', 'customer_credit_transactions.customer_id')
            ->leftJoin('banks', 'banks.id', '=', 'customer_credit_transactions.bank_id')
            ->select(
                'customer_credit_transactions.id',
                'customer_credit_transactions.amount',
                'customer_credit_transactions.payment_method',
                'customer_credit_transactions.reference_number',
                'customer_credit_transactions.notes',
                'customer_credit_transactions.created_at',
                'customer_credit_transactions.customer_id',
                'customers.name as customer_name',
                'banks.bank_name as bank_name'
            )
            ->orderBy('customer_credit_transactions.id', 'asc')
            ->get();

        $creditPaymentMethodTotal = fn (string $method) => (clone $creditPaymentsBase)
            ->where('payment_method', $method)
            ->sum('amount');

        $creditPaymentsTotals = [
            'cash' => round((float) $creditPaymentMethodTotal('cash'), 2),
            'ewallet' => round((float) $creditPaymentMethodTotal('e-wallet'), 2),
            'bank' => round((float) $creditPaymentMethodTotal('bank_transfer'), 2),
            'cheque' => round((float) $creditPaymentMethodTotal('cheque'), 2),
        ];

        // If no sales, fall back begin_date to earliest PosLog and end_date to now
        if (! empty($reading) && empty($reading[0]->begin_date)) {
            $earliestLog = PosLog::where('pos_id', $pos->id)
                ->where('store_id', $pos->store_id)
                ->whereNull('so_id')
                ->whereNull('shift_reading_id')
                ->when($userScope, fn ($q) => $q->where('user_id', $userId))
                ->orderBy('created_at', 'asc')
                ->first();
            if ($earliestLog) {
                $reading[0]->begin_date = $earliestLog->created_at;
            }
        }
        if (! empty($reading) && empty($reading[0]->end_date)) {
            $reading[0]->end_date = now();
        }

        return $this->success([
            'reading' => $reading,
            'cash_in' => $ciq,
            'cash_out' => $coq,
            'voided_cash_out_ids' => $voidedCashOutIds,
            'previous_accumulated_sales' => $previous_accumulated_sales,
            'credit_sales_detail' => $creditSalesQuery,
            'credit_payments_detail' => $creditPaymentsDetail,
            'credit_payments_totals' => $creditPaymentsTotals,
        ]);
    }

    public function saveReading(StoreRequest $request, Pos $pos): JsonResponse
    {
        $validated = $request->validated();
        $xreading = Xreading::create($validated);
        // Log Reading
        PosLogJob::dispatch(
            null,
            null,
            null,
            11,
            'X-Reading Generated | reading_id: '.$xreading->id.' Pos id: '.$pos->id,
            null,
            $pos->id,
            $xreading->store_id,
            $xreading->user_id
        );

        return $this->success(['reading' => $xreading]);
    }
}
