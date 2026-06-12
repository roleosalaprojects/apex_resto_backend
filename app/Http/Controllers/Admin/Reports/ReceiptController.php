<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Controller;
use App\Models\Employees\Role;
use App\Models\Pos\Receipt;
use App\Models\Pos\Sale;
use App\Models\Pos\SaleLine;
use App\Models\Settings\Pos;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReceiptController extends Controller
{
    //
    public function index()
    {
        $access = Role::find(auth()->user()->role_id);

        return view('admin.reports.receipts.index', compact('access'));
    }

    public function show($id)
    {
        $access = Role::find(auth()->user()->role_id);
        $receipt = DB::table('receipts as r')->first();
        $sale = DB::table('sales as s')
            ->leftJoin('users as u', 's.sales_by', 'u.id')
            ->leftJoin('stores as st', 'st.id', 's.store_id')
            ->leftJoin('employees as e', 'e.user_id', 'u.id')
            ->leftJoin('pos as p', 'p.id', 's.pos_id')
            ->leftJoin('customers as c', 'c.id', 's.customer_id')
            ->where('s.user_id', auth()->user()->user_id)
            ->where('s.id', $id)
            ->select(
                's.*',
                'u.name as sold_by',
                'u.email as email',
                'e.phone as pn',
                'st.name as store',
                'st.header',
                'st.created_at as date',
                'st.tin as TIN',
                'st.footer as message',
                'p.min as min',
                'p.serial as serial',
                'c.*')
            ->first();
        // dd($receipt);
        $lines = DB::table('sale_lines as sl')
            ->leftJoin('items as i', 'i.id', 'sl.item_id')
            ->leftJoin('users as u', 'u.id', 'sl.discount_by')
            ->where('sl.sales_id', $id)
            ->select('sl.*', 'i.name as item', 'u.name as user')
            ->get();

        return view('admin.reports.receipts.show', compact('sale', 'lines', 'access', 'receipt'));
    }

    public function getSalesReport(Request $request)
    {
        ini_set('memory_limit', '1024M');
        $output = '';
        $sale_summary = collect();
        $sale_time = collect();
        $time_format = collect(['00:00 AM', '01:00 AM', '02:00 AM', '03:00 AM', '04:00 AM', '05:00 AM', '06:00 AM', '07:00 AM', '08:00 AM', '09:00 AM', '10:00 AM', '11:00 AM', '12:00 NN', '01:00 PM', '02:00 PM', '03:00 AM', '04:00 PM', '05:00 PM', '06:00 PM', '07:00 PM', '08:00 PM', '09:00 PM', '10:00 PM', '11:00 PM', '12:00 MN']);
        // $output .= $request->start . " " . $request->end;

        // DB::enableQueryLog();

        $diff = date_diff(date_create($request->start), date_create($request->end))->format('%a');
        if ($diff > 0) {
            $day = Carbon::parse($request->start);
            for ($i = 0; $i < $diff + 1; $i++) {
                $sale = DB::table('sales')->where('user_id', auth()->user()->user_id)->where('cancelled', false)->whereBetween('created_at', [$day->startOfDay()->format('Y-m-d H:m:s'), $day->endOfDay()->format('Y-m-d H:m:s')])->sum('sales.total');
                $sale_summary->push(number_format($sale, 2, '.', ''));
                $sale_time->push($day->format('M d, Y'));
                $day->addDays(1);
            }
        } elseif ($diff == 0) {

            $day = $request->start.' 00:00:00';
            $day = Carbon::parse($day);
            $time = $day;

            for ($i = 0; $i < 24; $i++) {

                $j = $i + 1;
                $sale = DB::table('sales')->where('user_id', auth()->user()->user_id)->where('cancelled', false)->whereBetween('created_at', [$day->toDateString().' '.$i.':01:00', $day->toDateString().' '.$j.':00:00'])->sum('sales.total');

                $sale_summary->push(number_format($sale, 2, '.', ''));
                $sale_time = $time_format;
            }
        }
        $receipts = DB::table('sales as s')
            ->leftJoin('users as u', 's.sales_by', 'u.id')
            ->leftJoin('stores as st', 'st.id', 's.store_id')
            ->leftJoin('pos as p', 'p.id', 's.pos_id')
            ->where('s.user_id', auth()->user()->user_id)
            ->select('s.*', 'u.name as sold_by', 'st.name as store', 'p.number as terminal')
            ->whereBetween('s.created_at', [Carbon::parse($request->start)->startOfDay()->format('Y-m-d H:m:s'), Carbon::parse($request->end)->endOfDay()->format('Y-m-d H:m:s')])
            ->orderBy('created_at')
            ->get();

        $refunds = DB::table('sales')
            ->where('user_id', auth()->user()->user_id)
            ->where('cancelled', false)
            ->where('type', true)
            ->whereBetween('sales.created_at', [Carbon::parse($request->start)->startOfDay(), Carbon::parse($request->end)->endOfDay()])
            ->sum('sales.total');
        $sales = DB::table('sales')
            ->where('user_id', auth()->user()->user_id)
            ->whereBetween('sales.created_at', [Carbon::parse($request->start)->startOfDay(), Carbon::parse($request->end)->endOfDay()]);
        $refunds = number_format($refunds, 2);
        $total_gross = number_format($sales->sum('sales.total'), 2);
        $gross_sales = number_format($sales->where('type', false)->sum('sales.total'), 2);
        $profit = number_format($sales->where('type', false)->sum('sales.profit'), 2);

        $receipts2 = [];
        $receipt3 = [];

        foreach ($receipts as $item) {
            $type = $item->type;
            if ($type == 1) {
                $type = '<span class="text-danger">Refund</span>';
            } else {
                $type = '<span class="text-success">Sales</span>';
            }
            $receipts2[] = [
                $item->son,
                $type,
                $item->sold_by,
                '₱'.number_format($item->total, 2),
                $item->terminal,
                date('M-d-Y - h:m:i A', strtotime($item->created_at)),
                '<a href='.route('show.receipts', $item->id).'}}" class="btn btn-info "><i class="far fa-eye"></i></a>',
            ];

            $output .= '<tr>';
            $output .= '<td>'.$item->son.'</td>
                <td>';
            if ($item->type) {
                $output .= '<span class="text-danger">Refund</span>';
            } else {
                $output .= '<span class="text-success">Sales</span>';
            }
            $output .= '</td>';
            $output .= '<td>'.$item->sold_by.'</td>';
            $output .= '<td>₱'.number_format($item->total, 2).'</td>';
            $output .= '<td>'.$item->terminal.'</td>';
            $output .= '<td>'.date('M-d-Y - h:m:i A', strtotime($item->created_at)).'</td>
                <td>';
            $output .= '<a href="'.route('show.receipts', $item->id).'" class="btn btn-info "><i class="far fa-eye"></i></a>
                </td>';
            $output .= '</tr>';
        }
        $served = number_format(count($receipts));

        // $queries = DB::getQueryLog();
        return response()->json(compact('sale_summary', 'sale_time', 'diff', 'receipts', 'output', 'total_gross', 'refunds', 'gross_sales', 'profit', 'receipts2', 'served'));
    }

    public function adjustProfit()
    {
        $sales = DB::table('sales')->where('user_id', auth()->user()->user_id)->get();
        // dd($sales);
        ini_set('max_execution_time', 2400);
        foreach ($sales as $sale) {
            $profit = 0;
            $sale_line = SaleLine::where('sales_id', $sale->id)->get();
            foreach ($sale_line as $line) {
                $profit += ($line->price * $line->qty) - ($line->cost * $line->qty * $line->unit_qty);
            }
            Sale::find($sale->id)->update([
                'profit' => $profit,
            ]);
        }
        echo 'ok!';
    }

    public function salesByItem()
    {
        $access = Role::find(auth()->user()->role_id);
        // $items = DB::table('sale_lines as sl')
        //     ->leftJoin('items as i', 'i.id', 'sl.item_id')
        //     ->leftJoin('sales as s', 's.id', 'sl.sales_id')
        //     ->where('s.user_id', auth()->user()->user_id)
        //     ->select('sl.*', 'i.name', 'i.vatable', 's.son', 's.id as sale_id')
        //     ->whereBetween('s.created_at', [Carbon::today()->startOfDay(), Carbon::today()->endOfDay()])
        //     ->get();

        // DB::enableQueryLog();
        // $queries = DB::getQueryLog();
        // // dd($queries);
        $top_items = DB::table('sale_lines as sl')
            ->leftJoin('sales as s', 's.id', 'sl.sales_id')
            ->leftJoin('items as i', 'i.id', 'sl.item_id')
            ->where('s.user_id', auth()->user()->user_id)
            ->where('s.type', false)
            ->whereBetween('s.created_at', [Carbon::today()->startOfDay(), Carbon::today()->endOfDay()])
            ->select(DB::raw('sl.unit_qty * sl.qty as added'), 'i.name as item')
            ->take(10)
            ->groupBy('i.name')
            ->orderBy('added', 'DESC')
            ->get();
        $item_name = $top_items->pluck('item');
        $item_total = $top_items->pluck('added');

        return view('admin.reports.receipts.sales_by_item', compact('access', 'item_name', 'item_total', 'top_items'));
    }

    public function getItems(Request $request)
    {
        $items = [];
        $query = DB::table('sale_lines as sl')
            ->leftJoin('items as i', 'i.id', 'sl.item_id')
            ->leftJoin('sales as s', 's.id', 'sl.sales_id')
            ->where('s.user_id', auth()->user()->user_id)
            ->whereBetween('sl.created_at', [Carbon::parse($request->start)->startOfDay()->format('Y-m-d H:m:s'), Carbon::parse($request->end)->endOfDay()->format('Y-m-d H:m:s')])
            ->select('sl.*', 'i.name', 'i.vatable', 's.son', 's.id as sale_id')
            ->get();

        foreach ($query as $item) {
            $items[] = [
                "<a href='".route('items.show', $item->item_id)."'>".$item->name.'</a>',
                "<a href='".route('items.show', $item->sale_id)."'>".$item->son.'</a>',
                $item->qty,
                $item->unit,
                $item->unit_qty,
                number_format($item->discount, 2),
                number_format($item->cost, 2),
                number_format($item->price, 2),
                number_format($item->sub_total, 2),
                (($item->sub_total) - ($item->qty * $item->unit_qty * $item->cost)) <= 0 ? "<span class='text-danger'>".number_format(($item->sub_total) - ($item->qty * $item->unit_qty * $item->cost), 2).'</span>' : number_format(($item->sub_total) - ($item->qty * $item->unit_qty * $item->cost), 2),
                ($item->vatable) ? 'Vatable' : 'Non-Vat',
            ];
        }
        $top_items = DB::table('sale_lines as sl')
            ->leftJoin('sales as s', 's.id', 'sl.sales_id')
            ->leftJoin('items as i', 'i.id', 'sl.item_id')
            ->where('s.user_id', auth()->user()->user_id)
            ->where('s.type', false)
            ->whereBetween('sl.created_at', [Carbon::parse($request->start)->startOfDay()->format('Y-m-d H:m:s'), Carbon::parse($request->end)->endOfDay()->format('Y-m-d H:m:s')])
            ->select(DB::raw('sl.unit_qty * sl.qty as added'), 'i.name as item')
            ->take(10)
            ->orderBy('added', 'DESC')
            ->get();
        $item_name = collect();
        $item_total = collect();
        foreach ($top_items as $top) {
            $item_name->push($top->item);
            $item_total->push($top->added);
        }

        return response()->json(compact('items', 'item_name', 'item_total'));
    }

    public function sales_bir()
    {
        $access = Role::find(auth()->user()->role_id);
        if ($access->sls) {
            $years = Sale::where('cancelled', false)
                ->select(DB::raw('YEAR(created_at) as year'))
                ->groupBy(DB::raw('YEAR(created_at)'))
                ->orderBy(DB::raw('YEAR(created_at)'), 'DESC')
                ->get();

            return view('admin.reports.receipts.sales_report_bir', compact('access', 'years'));
        }

        return redirect('/home')->with('error', "You don't have rights to access this. Please contact administrator if there are any concerns.");
    }

    public function sales_bir_individual(Request $request)
    {
        $pos = Pos::find($request->pos_id);
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
                        AND pos_id = '.$request->pos_id.'
                    ) as end_acc,
                    (
                        select sum(if(type = 0, total - (if(excess_vat is not null, excess_vat, 0) + if(excess_non_vat is not null, excess_non_vat, 0) + excess_vatable), -total))
                        FROM sales
                        WHERE
                        created_at < concat(YEAR(s.created_at), "-",MONTH(s.created_at),"-",DAY(s.created_at), " 00:00:00")
                        AND pos_id = '.$request->pos_id.'
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
                            "'.$date->startOfMonth()->toDateTimeString().'"
                            AND
                            "'.$date->endOfMonth()->toDateTimeString().'"
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
                    p.status = true
                    AND
                    p.id = '.$pos->id.'
                    AND
                    s.created_at
                        BETWEEN
                        "'.$date->startOfMonth()->toDateTimeString().'"
                        AND
                        "'.$date->endOfMonth()->toDateTimeString().'"
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
                        AND pos_id = '.$request->pos_id.'
                    ) as end_acc,
                    (
                        select sum(if(type = 0, total, 0))
                        FROM sales
                        WHERE
                        created_at < concat(YEAR(s.created_at), "-",MONTH(s.created_at),"-",DAY(s.created_at), " 00:00:00")
                        AND pos_id = '.$request->pos_id.'
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
                            "'.$date->startOfMonth()->toDateTimeString().'"
                            AND
                            "'.$date->endOfMonth()->toDateTimeString().'"
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
                    p.status = true
                    AND
                    p.id = '.$pos->id.'
                    AND
                    s.created_at
                        BETWEEN
                        "'.$date->startOfMonth()->toDateTimeString().'"
                        AND
                        "'.$date->endOfMonth()->toDateTimeString().'"
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

        return view('admin.reports.receipts.sales_report_bir_individual', compact('pos', 'query', 'dateSelected'));
    }

    public function posReadings()
    {
        $access = Role::find(auth()->user()->role_id);

        return view('admin.reports.receipts.readings', compact('access'));
    }

    public function printReceipt($id)
    {
        $sale = DB::table('sales as s')
            ->leftJoin('users as u', 's.sales_by', 'u.id')
            ->leftJoin('stores as st', 'st.id', 's.store_id')
            ->leftJoin('employees as e', 'e.user_id', 'u.id')
            ->leftJoin('pos as p', 'p.id', 's.pos_id')
            ->leftJoin('customers as c', 'c.id', 's.customer_id')
            ->where('s.user_id', auth()->user()->user_id)
            ->where('s.id', $id)
            ->select('s.*', 'u.name as sold_by', 'u.email as email', 'e.phone as pn', 'st.name as store', 'st.header', 'st.created_at as date', 'st.tin as TIN', 'st.footer as message', 'p.min as min', 'p.serial as serial', 'c.*')
            ->first();
        // dd($sale);
        $receipt = DB::table('receipts as r')->first();
        // dd($receipt);
        $lines = DB::table('sale_lines as sl')
            ->leftJoin('items as i', 'i.id', 'sl.item_id')
            ->leftJoin('users as u', 'u.id', 'sl.discount_by')
            ->where('sl.sales_id', $id)
            ->select('sl.*', 'i.name as item', 'u.name as user')
            ->get();

        return view('admin.reports.receipts.print', compact('sale', 'lines', 'receipt'));
    }

    public function viewNonVat()
    {
        $access = Role::find(auth()->user()->role_id);
        if ($access->sls) {
            $years = Sale::where('cancelled', false)
                ->select(DB::raw('YEAR(created_at) as year'))
                ->groupBy(DB::raw('YEAR(created_at)'))
                ->orderBy(DB::raw('YEAR(created_at)'), 'DESC')
                ->get();

            return view('admin.reports.receipts.non-vat', compact('access', 'years'));
        }

        return redirect('/home')->with('error', "You don't have rights to access this. Please contact administrator if there are any concerns.");
    }

    public function viewIndividualNonVat($date, $id)
    {
        $qDate = Carbon::parse($date);
        $receipt = Receipt::where('user_id', $id)->first();
        if ($receipt->hocus_pocus) {
            $q = DB::select(
                '
                SELECT
                    format(sum(if(type = false, non_vat - excess_non_vat, 0)) - sum(if(type = true, non_vat, 0)), 2) as net_non_vat,
                    format(sum(if(type=true, non_vat, 0)), 2) as rnon_vat,
                    format(sum(if(type=false, non_vat - excess_non_vat, 0)), 2) as snon_vat,
                    format(sum(if(type=false, total - excess_vat - excess_non_vat, 0)), 2) as sales,
                    format(sum(if(type=true, total, 0)), 2) as refunds,
                    p.number as terminal,
                    concat(year(sales.created_at), "/", month(sales.created_at), "/",day(sales.created_at)) as day
                FROM
                    sales
                LEFT JOIN
                    pos p
                ON
                    p.id = pos_id
                WHERE
                    sales.created_at
                    BETWEEN
                    "'.$qDate->startOfMonth()->toDateTimeString().'"
                    AND
                    "'.$qDate->endOfMonth()->toDateTimeString().'"
                GROUP BY
                    day(sales.created_at), pos_id
            '
            );
        } else {
            $q = DB::select(
                '
                    SELECT
                        format(sum(if(type = false, non_vat, 0)) - sum(if(type = true, non_vat, 0)), 2) as net_non_vat,
                        format(sum(if(type=true, non_vat, 0)), 2) as rnon_vat,
                        format(sum(if(type=false, non_vat, 0)), 2) as snon_vat,
                        format(sum(if(type=false, total, 0)), 2) as sales,
                        format(sum(if(type=true, total, 0)), 2) as refunds,
                        p.number as terminal,
                        concat(year(sales.created_at), "/", month(sales.created_at), "/",day(sales.created_at)) as day
                    FROM
                        sales
                    LEFT JOIN
                        pos p
                    ON
                        p.id = pos_id
                    WHERE
                        sales.created_at
                        BETWEEN
                        "'.$qDate->startOfMonth()->toDateTimeString().'"
                        AND
                        "'.$qDate->endOfMonth()->toDateTimeString().'"
                    GROUP BY
                        day(sales.created_at), pos_id
                '
            );
        }

        return view('admin.reports.receipts.non_vat_individual', compact('q', 'qDate'));
    }
}
