<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Controller;
use App\Models\Pos\Receipt;
use App\Models\Pos\Sale;
use App\Models\Pos\Zreading;
use App\Models\Settings\Pos;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BirController extends Controller
{
    public function vat()
    {
        return view('admin.reports.bir.vat.index');
    }

    public function vatData(Request $request)
    {
        ini_set('memory_limit', -1);
        ini_set('max_execution_time', -1);
        $startDate = Carbon::parse($request->startDate)->startOfDay();
        $endDate = Carbon::parse($request->endDate)->endOfDay();

        $startDayString = $startDate->toDateTimeString();
        $endDayString = $endDate->toDateTimeString();
        $data = Pos::where(function ($query) use ($startDayString, $endDayString) {
            $query->whereRelation('sales', 'created_at', '>=', $startDayString);
            $query->whereRelation('sales', 'created_at', '<=', $endDayString);
        });
        $table = DataTables($data)
            ->addColumn('actions', function ($q) {
                return '<a href="'.route('reports.bir.vat.individual.new', $q->id).'" class="btn btn-info btn-icon btn-sm" target="_blank"><i class="fas fa-print"></i></a>';
            })
            ->rawColumns(['actions'])
            ->make(true);

        return $table;
    }

    public function vatIndividual(Request $request)
    {
        $pos = Pos::with(['owner', 'store'])->where('id', $request->pos_id)->first();
        // return $pos;
        $dateSelected = $request->year.'-'.$request->month;
        $date = Carbon::parse($dateSelected);
        $start = Sale::where('user_id', $request->user)->first();
        $receipt = Receipt::where('user_id', $request->user)->first();
        if ($receipt->hocus_pocus) {
            $query = '
                SELECT
                    date_format(s.created_at, "%b %d, %Y") as `date`,
                    st.name as store_name,
                    p.name as terminal_name,
                    p.min as min,
                    min(s.son) as start_son,
                    max(s.son) as end_son,
                    (
                        select sum(
                            if(type = 0,
                                total  - (excess_vat + excess_non_vat + excess_vatable),
                                0
                            )
                        )
                        FROM sales
                        WHERE
                        created_at < date_format(s.created_at, "%y-%m-%d 23:59:59")
                        AND pos_id = '.$pos->id.'
                    ) as end_acc,
                    (
                        select sum(
                            if(type = 0,
                                total  - (excess_vat + excess_non_vat + excess_vatable),
                                0
                            )
                        )
                        FROM sales
                        WHERE
                        created_at < date_format(s.created_at, "%y-%m-%d 00:00:00")
                        AND pos_id = '.$pos->id.'
                    ) as start_acc,
                    sum(if(s.type, 0, s.total - (s.excess_vat + s.excess_non_vat + s.excess_vatable))) as gross,
                    sum(if(s.type, 0, s.vatable - s.excess_vatable)) as vatable,
                    sum(if(s.type, 0, s.vat - s.excess_vat)) as vat,
                    sum(if(s.type, 0, (s.vat_exempt + s.non_vat) - s.excess_non_vat)) as vat_exempt,
                    sum(s.zero_rated) as zero_rated,
                    sum(if(sale_type IN ("senior", "pwd"), vat, 0)) as sp_vat,
                    sum(if(sale_type = "", s.discount, 0)) as discount,
                    sum(if(sale_type IN ("senior", "pwd"), discount, 0)) as sp_discount,
                    sum(if(s.type = 1, total, 0)) as refund,
                    0 as void,
                    0 as total_deductions,
                    sum(if(s.type = 1, s.vat, 0)) as r_vat,
                    0 as others,
                    0 as total_vat_adjustments,
                    0 as vat_payable,
                    "N/A" as reset_counter,
                    "" as remarks
                FROM sales as s

                left join pos as p
                on p.id = s.pos_id
                left join stores as st
                on p.store_id = st.id
                WHERE
                    (
                        s.created_at
                        BETWEEN
                        "'.Carbon::parse($dateSelected)->startOfMonth().'"
                        AND
                        " '.Carbon::parse($dateSelected)->endOfMonth().'"
                    )
                    AND
                    pos_id = '.$pos->id.'
                group by
                    `date`
                order by
                    `date`
                ';
        } else {
            $query = '
                SELECT
                date_format(s.created_at, "%b %d, %Y") as `date`,
                st.name as `store_name`,
                p.name as `terminal_name`,
                p.min as `min`,
                min(s.son) as "start_son",
                max(s.son) as "end_son",
                (
                    select sum(
                        if(type = 0,
                            total,
                            0
                        )
                    )
                    FROM sales
                    WHERE
                    created_at < date_format(s.created_at, "%y-%m-%d 23:59:59")
                    AND pos_id = 1
                ) as end_acc,
                (
                    select sum(
                        if(type = 0,
                            total,
                            0
                        )
                    )
                    FROM sales
                    WHERE
                    created_at < date_format(s.created_at, "%y-%m-%d 00:00:00")
                    AND pos_id = 1
                ) as start_acc,
                sum(if(s.type, 0, s.total)) as gross,
                sum(if(s.type, 0, s.vatable)) as vatable,
                sum(if(s.type, 0, s.vat)) as vat,
                sum(if(s.type, 0, s.vat_exempt + s.non_vat)) as vat_exempt,
                sum(s.zero_rated) as zero_rated,
                sum(if(sale_type IN ("senior", "pwd"), vat, 0)) as sp_vat,
                sum(if(sale_type = "", s.discount, 0)) as discount,
                sum(if(sale_type IN ("senior", "pwd"), discount, 0)) as sp_discount,
                sum(if(s.type = 1, total, 0)) as refund,
                sum(if(s.type = 1, s.vat, 0)) as r_vat
                FROM sales as s

                left join pos as p
                    on p.id = s.pos_id
                left join stores as st
                    on p.store_id = st.id

                WHERE
                    (
                       s.created_at
                        BETWEEN
                        "'.Carbon::parse($dateSelected)->startOfMonth().'"
                        AND
                        " '.Carbon::parse($dateSelected)->endOfMonth().'"
                    )
                    AND
                    s.pos_id = '.$pos->id.'
                group by
                    `date`
                order by
                    `date`
                ';
        }
        // dd($query);
        $query = DB::select($query);

        // dd($query);
        return view('admin.reports.receipts.sales_report_bir_individual', compact('pos', 'query', 'dateSelected'));
    }

    public function vatOverall(Request $request)
    {
        // return $pos;
        $dateSelected = $request->year.'-'.$request->month;
        $date = Carbon::parse($dateSelected);
        $start = Sale::where('user_id', $request->user)->first();
        $receipt = Receipt::where('user_id', $request->user)->first();
        if ($receipt->hocus_pocus) {
            $query = '
                SELECT
                    p.number as terminal,
                    st.name as store,
                    st.id as branch,
                    p.name,
                    sum(if(s.type, -s.total + (s.excess_vat + s.excess_non_vat + s.excess_vatable), s.total - (s.excess_vat + s.excess_non_vat + s.excess_vatable))) as gross,
                    sum(if(s.type, -s.vatable - s.excess_vatable, s.vatable - s.excess_vatable)) as vatable,
                    sum(if(s.type, -s.vat + s.excess_vat, s.vat - s.excess_vat)) as vat,
                    sum(if(s.type, -(s.vat_exempt + s.non_vat) + s.excess_non_vat, (s.vat_exempt + s.non_vat) - s.excess_non_vat)) as vat_exempt,
                    sum(if(s.type, -s.non_vat + excess_non_vat, s.non_vat  - excess_non_vat)) as non_vat,
                    sum(s.zero_rated) as zero_rated,
                    sum(if(sale_type IN ("senior", "pwd"), vat, 0)) as sp_vat,
                    sum(if(s.type, 0, s.profit) - (s.excess_vat + s.excess_non_vat + s.excess_vatable)) as revenue,
                    sum(if(sale_type = "", s.discount, 0)) as discount,
                    sum(if(sale_type IN ("senior", "pwd"), discount, 0)) as sp_discount,
                    sum(if(s.type = true, total, 0)) as refund,
                    sum(if(s.type = true, s.vat, 0)) as r_vat,
                    DAY(s.created_at) as sale_day,
                    MONTH(s.created_at) as sale_month,
                    YEAR(s.created_at) as sale_year,
                    son.min_son,
                    son.max_son,
                    p.`number` as terminal,
                    (
                        select sum(if(type = 0, total - (if(excess_vat is not null, excess_vat, 0) + if(excess_non_vat is not null, excess_non_vat, 0) + excess_vatable), -total))
                        FROM sales
                        WHERE
                        created_at <= concat(YEAR(s.created_at), "-",MONTH(s.created_at),"-",DAY(s.created_at), " 23:59:59")
                    ) as end_acc,
                    (
                        select sum(if(type = 0, total - (if(excess_vat is not null, excess_vat, 0) + if(excess_non_vat is not null, excess_non_vat, 0) + excess_vatable), -total))
                        FROM sales
                        WHERE
                        created_at < concat(YEAR(s.created_at), "-",MONTH(s.created_at),"-",DAY(s.created_at), " 00:00:00")
                    ) as start_acc
                FROM pos p

                left join
                    sales as s
                    on
                    s.pos_id = p.id

                left join
                    (
                        select
                            day(created_at) as sub_day,
                            max(counter) as max_son,
                            min(counter) as min_son,
                            pos_id
                        from sales
                        where
                            created_at
                            BETWEEN
                            "'.$date->startOfYear()->toDateTimeString().'"
                            AND
                            "'.$date->endOfYear()->toDateTimeString().'"
                        group by
                            pos_id,
                            sub_day
                    )
                    as son

                    on son.pos_id = p.id AND son.sub_day = DAY(s.created_at)
                left join
                    stores as st
                    on
                    st.id = p.store_id

                WHERE
                    s.created_at
                        BETWEEN
                        "'.$date->startOfYear()->toDateTimeString().'"
                        AND
                        "'.$date->endOfYear()->toDateTimeString().'"
                group by
                    p.id,
                    sale_day,
                    sale_month,
                    sale_year,
                    son.min_son,
                    son.max_son
                order by
                    sale_year,
                    sale_month,
                    sale_day,
                    p.id,
                    end_acc,
                    start_acc
                ';
        } else {
            $query = '
                SELECT
                    p.number as terminal,
                    st.name as store,
                    st.id as branch,
                    p.name,
                    sum(s.total) as gross,
                    sum(s.vatable) as vatable,
                    sum(vat) as vat,
                    sum(s.vat_exempt + s.non_vat) as vat_exempt,
                    sum(s.non_vat) as non_vat,
                    sum(s.zero_rated) as zero_rated,
                    sum(if(sale_type IN ("senior", "pwd"), vat, 0)) as sp_vat,
                    sum(s.profit) as revenue,
                    sum(if(sale_type = "", s.discount, 0)) as discount,
                    sum(if(sale_type IN ("senior", "pwd"), discount, 0)) as sp_discount,
                    sum(if(s.type = true, total, 0)) as refund,
                    sum(if(s.type = true, s.vat, 0)) as r_vat,
                    DAY(s.created_at) as sale_day,
                    MONTH(s.created_at) as sale_month,
                    YEAR(s.created_at) as sale_year,
                    son.min_son,
                    son.max_son,
                    p.`number` as terminal,
                    (
                        select sum(if(type = 0, total, 0))
                        FROM sales
                        WHERE
                        created_at <= concat(YEAR(s.created_at), "-",MONTH(s.created_at),"-",DAY(s.created_at), " 23:59:59")
                        AND pos_id = p.id
                    ) as end_acc,
                    (
                        select sum(if(type = 0, total, 0))
                        FROM sales
                        WHERE
                        created_at < concat(YEAR(s.created_at), "-",MONTH(s.created_at),"-",DAY(s.created_at), " 00:00:00")
                        AND pos_id = p.id
                    ) as start_acc
                FROM pos p

                left join
                    sales as s
                    on
                    s.pos_id = p.id

                left join
                    (
                        select
                            day(created_at) as sub_day,
                            max(counter) as max_son,
                            min(counter) as min_son,
                            pos_id
                        from sales
                        where
                            created_at
                            BETWEEN
                            "'.$date->startOfYear()->toDateTimeString().'"
                            AND
                            "'.$date->endOfYear()->toDateTimeString().'"
                        group by
                            pos_id,
                            sub_day
                    )
                    as son

                    on son.pos_id = p.id AND son.sub_day = DAY(s.created_at)
                left join
                    stores as st
                    on
                    st.id = p.store_id

                WHERE
                    s.created_at
                        BETWEEN
                        "'.$date->startOfYear()->toDateTimeString().'"
                        AND
                        "'.$date->endOfYear()->toDateTimeString().'"
                group by
                    p.id,
                    sale_day,
                    sale_month,
                    sale_year,
                    son.min_son,
                    son.max_son
                order by
                    sale_year,
                    sale_month,
                    sale_day,
                    p.id,
                    end_acc,
                    start_acc
                ';
        }
        // dd($query);
        $query = DB::select($query);

        // dd($query);
        return view('admin.reports.receipts.sales_report_bir_overall', compact('query', 'dateSelected'));
    }

    public function individualVatTable(Pos $pos, Request $request)
    {
        $pos = Pos::with(['owner', 'store'])->where('id', $request->pos_id)->first();
        // return $pos;
        $dateSelected = $request->year.'-'.$request->month;
        $date = Carbon::parse($dateSelected);
        $start = Sale::where('user_id', $request->user)->first();
        $receipt = Receipt::where('user_id', $request->user)->first();
        if ($receipt->hocus_pocus) {
            $query = '
                SELECT
                    date_format(s.created_at, "%b %d, %Y") as `date`,
                    st.name as store,
                    p.name as terminal,
                    p.min as min,
                    min(s.son) as start_son,
                    max(s.son) as end_son,
                    (
                        select sum(
                            if(type = 0,
                                total  - (excess_vat + excess_non_vat + excess_vatable),
                                0
                            )
                        )
                        FROM sales
                        WHERE
                        created_at < date_format(s.created_at, "%y-%m-%d 23:59:59")
                        AND pos_id = '.$pos->id.'
                    ) as end_acc,
                    (
                        select sum(
                            if(type = 0,
                                total  - (excess_vat + excess_non_vat + excess_vatable),
                                0
                            )
                        )
                        FROM sales
                        WHERE
                        created_at < date_format(s.created_at, "%y-%m-%d 00:00:00")
                        AND pos_id = '.$pos->id.'
                    ) as start_acc,
                    sum(if(s.type, 0, s.total - (s.excess_vat + s.excess_non_vat + s.excess_vatable))) as gross,
                    sum(if(s.type, 0, s.vatable - s.excess_vatable)) as vatable,
                    sum(if(s.type, 0, s.vat - s.excess_vat)) as vat,
                    sum(if(s.type, 0, (s.vat_exempt + s.non_vat) - s.excess_non_vat)) as vat_exempt,
                    sum(s.zero_rated) as zero_rated,
                    sum(if(sale_type IN ("senior", "pwd"), vat, 0)) as sp_vat,
                    sum(if(sale_type = "", s.discount, 0)) as discount,
                    sum(if(sale_type IN ("senior", "pwd"), discount, 0)) as sp_discount,
                    sum(if(s.type = 1, total, 0)) as refund,
                    0 as void,
                    0 as total_deductions,
                    sum(if(s.type = 1, s.vat, 0)) as r_vat,
                    0 as others,
                    0 as total_vat_adjustments,
                    0 as vat_payable,
                    sum(if(s.type = 0, total, -total)) as net_sales,
                    "N/A" as reset_counter,
                    "" as remarks
                FROM sales as s

                left join pos as p
                on p.id = s.pos_id
                left join stores as st
                on p.store_id = st.id
                WHERE
                    (
                        s.created_at
                        BETWEEN
                        '.Carbon::parse($dateSelected)->startOfMonth().'
                        AND
                        '.Carbon::parse($dateSelected)->endOfMonth().'
                    )
                group by
                    `date`
                order by
                    `date`
                ';
        } else {
            $query = '
                SELECT
                date_format(s.created_at, "%b %d, %Y") as `date`,
                st.name as `Store Name`,
                p.name as `Terminal Name`,
                p.min as `MIN`,
                min(s.son) as "start_son",
                max(s.son) as "end_son",
                (
                    select sum(
                        if(type = 0,
                            total,
                            0
                        )
                    )
                    FROM sales
                    WHERE
                    created_at < date_format(s.created_at, "%y-%m-%d 23:59:59")
                    AND pos_id = 1
                ) as end_acc,
                (
                    select sum(
                        if(type = 0,
                            total,
                            0
                        )
                    )
                    FROM sales
                    WHERE
                    created_at < date_format(s.created_at, "%y-%m-%d 00:00:00")
                    AND pos_id = 1
                ) as start_acc,
                sum(if(s.type, 0, s.total)) as gross,
                sum(if(s.type, 0, s.vatable)) as vatable,
                sum(if(s.type, 0, s.vat)) as vat,
                sum(if(s.type, 0, s.vat_exempt + s.non_vat)) as vat_exempt,
                sum(s.zero_rated) as zero_rated,
                sum(if(sale_type IN ("senior", "pwd"), vat, 0)) as sp_vat,
                sum(if(sale_type = "", s.discount, 0)) as discount,
                sum(if(sale_type IN ("senior", "pwd"), discount, 0)) as sp_discount,
                sum(if(s.type = 1, total, 0)) as refund,
                sum(if(s.type = 1, s.vat, 0)) as r_vat
                FROM sales as s

                left join pos as p
                    on p.id = s.pos_id
                left join stores as st
                    on p.store_id = st.id

                WHERE
                    (
                        s.created_at
                        BETWEEN
                        '.Carbon::parse($dateSelected)->startOfMonth().'
                        AND
                        '.Carbon::parse($dateSelected)->endOfMonth().'
                    )
                group by
                    `date`
                order by
                    `date`
                ';
        }
        // dd($query);
        $query = DB::select($query);

        return DataTables($query)
            ->make(true);
    }

    public function printVAT(Request $request, Pos $pos)
    {
        $pos->with([
            'owner',
            'store',
        ]);
        $zreadings = ZReading::where('pos_id', $pos->id)->get();

        return view('admin.reports.bir.vat.vat_individual_report', compact('pos', 'zreadings'));
    }
}
