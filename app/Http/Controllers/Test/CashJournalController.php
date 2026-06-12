<?php

namespace App\Http\Controllers\Test;

use App\Http\Controllers\Controller;
use App\Sale;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Yajra\DataTables\DataTables;

class CashJournalController extends Controller
{
    public function index(){
        return view('test.cash_journal');
    }

    public function data(Request $request){
        $startDate = Carbon::parse($request->startDate)->startOfDay();
        $endDate = Carbon::parse($request->endDate)->endOfDay();

        $startDayString = $startDate->toDateTimeString();
        $endDayString = $endDate->toDateTimeString();

        $sales = Sale::whereBetween('created_at', [$startDayString, $endDayString])
            ->with([
                'customer' => function($q){
                    return $q->select('id', 'name');
                },
                'lines' => function($q){
                    $q->with(['item' => function($q){
                        return $q->select('id', 'name');
                    }]);
                    return $q->select('sales_id', 'item_id', 'qty');
                }
            ])
            ->get();
        $table = DataTables($sales)->make(true);
        return response()->json($table);
    }
}
