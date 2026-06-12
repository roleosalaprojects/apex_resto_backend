<?php

namespace App\Http\Controllers\Admin\Reports\SpecialDiscountReports;

use App\Http\Controllers\Controller;
use App\Models\Settings\Pos;
use Carbon\Carbon;
use Illuminate\Http\Request;

class SoloParentDiscountReportController extends Controller
{
    public function index()
    {
        return view('admin.reports.bir.special_discounts.solo_parent.index');
    }

    public function spIndividualPosReport(Pos $pos)
    {
        $pos->with([
            'owner' => function ($owner) {
                $owner->select('id', 'name', 'address');
                $owner->with('details');
            },
            'store',
        ]);

        return view('admin.reports.bir.special_discounts.solo_parent.individual')->with('pos', $pos);
    }

    public function spIndividualPosReportTable(Request $request)
    {
        $startDate = Carbon::parse($request->startDate)->startOfDay()->toDateTimeString();
        $endDate = Carbon::parse($request->endDate)->endOfDay()->toDateTimeString();
        $sales = \App\Models\Pos\Sale::whereBetween('created_at', [$startDate, $endDate])
            ->where('special_discount_type', '=', 3)
            ->where('pos_id', $request->pos_id)
            ->get();

        return response()->json([
            'success' => true,
            'data' => datatables($sales)->make(true),
        ]);
    }
}
