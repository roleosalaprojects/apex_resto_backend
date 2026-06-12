<?php

namespace App\Http\Controllers\Test;

use App\Http\Controllers\Controller;
use App\Sale;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CalendarController extends Controller
{
    public function index(){
        return view('test.calendar');
    }
    public function data(Request $request){
        $startDate = Carbon::parse($request->start)->startOfDay()->toDateTimeString();
        $endDate = Carbon::parse($request->end)->endOfDay()->toDateTimeString();
        $data = Sale::whereBetween('sales.created_at', [$startDate, $endDate])
//            ->leftJoin('admin as u', 'u.id', 'sales_by')
            ->select(
//                DB::raw('concat(u.name, " - Net Sales: ",format(sum(if(type = 0, total, -total)), 2)) as title'),
                DB::raw('concat("Net Sales: ",format(sum(if(type = 0, total, -total)), 2)) as title'),
                DB::raw('date_format(sales.created_at, "%Y-%m-%dT%TZ") as start'),
                DB::raw('date_format(sales.created_at, "%Y-%m-%d %H") as formatted_date'),
//                DB::raw('u.name as transacted_by'),
                DB::raw('"#378006" as color')
            )
//            ->groupBy('formatted_date', 'transacted_by')
            ->groupBy('formatted_date')
            ->get();
        return response()->json($data);
    }
}
