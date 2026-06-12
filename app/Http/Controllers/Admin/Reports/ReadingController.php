<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Controller;
use App\Models\Employees\Role;
use App\Models\Pos\Receipt;
use App\Models\Pos\Xreading;
use App\Models\Pos\Zreading;

class ReadingController extends Controller
{
    public function getXReading($id)
    {
        $reading = Xreading::where('id', $id)->first();
        $access = Role::where('id', auth()->user()->role_id)->first();
        $receipt = Receipt::where('user_id', $reading->user_id)->first();
        $type = 'x';

        return view('admin.reports.receipts.readings_show', compact('reading', 'access', 'type', 'receipt'));
    }

    public function getZReading($id)
    {
        // dd($id);
        $reading = Zreading::where('id', $id)->first();
        $access = Role::where('id', auth()->user()->role_id)->first();
        $receipt = Receipt::where('user_id', $reading->user_id)->first();
        $type = 'z';

        return view('admin.reports.receipts.readings_show', compact('reading', 'access', 'type', 'receipt'));
    }
}
