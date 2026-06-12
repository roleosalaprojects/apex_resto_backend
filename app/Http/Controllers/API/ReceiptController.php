<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Pos\Receipt;
use App\Models\Pos\SaleLine;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReceiptController extends Controller
{
    use ApiResponse;

    public function getBIRMonthlySalesChart(Request $request)
    {
        $dateSelected = $request->year.'-'.$request->month;
        $date = Carbon::parse($dateSelected);
        // begin::Hocus-Pocus
        $receipt = Receipt::where('user_id', $request->user)->first();
        if ($receipt->hocus_pocus) {
            $output = DB::table('pos as p')
                ->leftJoin('sales as s', 's.pos_id', 'p.id')
                ->leftJoin('stores as st', 'st.id', 'p.store_id')
                ->whereBetween('s.created_at', [$date->startOfMonth()->toDateTimeString(), $date->endOfMonth()->toDateTimeString()])
                ->groupBy('p.id')
                ->where('p.user_id', $request->user)
                ->where('p.status', true)
                ->groupBy('sale_month', 'sale_year')
                ->select(
                    DB::raw('
                    format(
                        if(s.type = 0, 
                            sum(s.total - (excess_vatable + ifnull(s.excess_vat, 0) + ifnull(s.excess_non_vat, 0)))
                            , -(sum(s.total - (excess_vatable + ifnull(s.excess_vat, 0) + ifnull(s.excess_non_vat, 0))))
                        )
                    , 2) as total'),
                    DB::raw('format(if(s.type = 0, sum(s.vatable - s.excess_vatable), -sum(s.vatable - s.excess_vatable)), 2) as vatable'),
                    DB::raw('format(if(s.type = 0, sum(s.vat - ifnull(s.excess_vat, 0)), -sum(s.vat - ifnull(s.excess_vat, 0))), 2) as vat'),
                    DB::raw('format(if(s.type = 0, sum((s.non_vat + s.vat_exempt) - ifnull(s.excess_non_vat, 0)), -sum((s.non_vat + s.vat_exempt) - ifnull(s.excess_non_vat, 0))), 2) as non_vat'),
                    DB::raw('format(sum(s.zero_rated), 2) as zero_rated'),
                    DB::raw('p.number'),
                    DB::raw('format(sum(if(s.type, -s.profit, s.profit) - (ifnull(s.excess_vat, 0) - ifnull(s.excess_non_vat, 0))), 2) as revenue'),
                    DB::raw('MONTH(s.created_at) as sale_month'),
                    DB::raw('YEAR(s.created_at) as sale_year'),
                    'p.number as terminal',
                    'st.name as store',
                    'st.id as branch',
                    'p.name',
                    'p.id as pos_id',
                )
                ->get();
        } else {
            $output = DB::table('pos as p')
                ->leftJoin('sales as s', 's.pos_id', 'p.id')
                ->leftJoin('stores as st', 'st.id', 'p.store_id')
                ->whereBetween('s.created_at', [$date->startOfMonth()->toDateTimeString(), $date->endOfMonth()->toDateTimeString()])
                ->groupBy('p.id')
                ->where('p.user_id', $request->user)
                ->where('p.status', true)
                ->groupBy('sale_month', 'sale_year')
                ->select(
                    DB::raw('format(sum(if(s.type, -s.total, s.total)), 2) as total'),
                    DB::raw('format(sum(if(s.type, -s.vatable, s.vatable)), 2) as vatable'),
                    DB::raw('format(sum(if(s.type, -s.vat, s.vat)), 2) as vat'),
                    DB::raw('format(sum(if(s.type, -(s.non_vat + s.vat_exempt), s.non_vat + s.vat_exempt)), 2) as non_vat'),
                    DB::raw('format(sum(if(s.type, -s.zero_rated, s.zero_rated)), 2) as zero_rated'),
                    DB::raw('p.number'),
                    DB::raw('format(sum(if(s.type, -s.profit, s.profit)), 2) as revenue'),
                    DB::raw('MONTH(s.created_at) as sale_month'),
                    DB::raw('YEAR(s.created_at) as sale_year'),
                    'p.number as terminal',
                    'st.name as store',
                    'st.id as branch',
                    'p.name',
                    'p.id as pos_id',
                )
                ->get();
        }

        // end::Hocus-Pocus
        return datatables($output)->make(true);
    }

    public function salesByItem(Request $request)
    {
        $query = SaleLine::leftJoin('items as i', 'i.id', 'sale_lines.item_id')
            ->leftJoin('sales as s', 's.id', 'sale_lines.sales_id')
            ->where('s.user_id', $request->user)
            ->whereBetween('sale_lines.created_at', [Carbon::parse($request->start)->startOfDay()->toDateTimeString(), Carbon::parse($request->end)->endOfDay()->toDateTimeString()])
            ->select(
                'sale_lines.id as id',
                'sale_lines.item_id as item_id',
                'sale_lines.unit as unit',
                'sale_lines.qty as qty',
                'sale_lines.unit_qty as unit_qty',
                'sale_lines.cost as cost',
                'sale_lines.price as price',
                'sale_lines.discount as discount',
                'sale_lines.sub_total as sub_total',
                'i.name',
                'i.vatable as vat',
                's.son',
                's.id as sale_id',
            );

        return datatables($query)
            ->addColumn('item_name', function (SaleLine $saleLine) {
                return "<a href='".route('items.show', $saleLine->item_id)."'>$saleLine->name</a>";
            })
            ->addColumn('son', function (SaleLine $saleLine) {
                return "<a href='".route('show.receipts', $saleLine->sale_id)."'>$saleLine->son</a>";
            })
            ->addColumn('profit', function (SaleLine $saleLine) {
                $profit = number_format($saleLine->sub_total - ($saleLine->qty * $saleLine->unit_qty * $saleLine->cost), 2);

                return ($profit > 0) ? $profit : "<span class='text-danger'>$profit</span>";
            })
            ->addColumn('vatable', function (SaleLine $saleLine) {
                return ($saleLine->vat) ? 'VATABLE' : 'NON-VATABLE';
            })
            ->rawColumns(['item_name', 'son', 'profit'])
            ->make(true);
    }
}
