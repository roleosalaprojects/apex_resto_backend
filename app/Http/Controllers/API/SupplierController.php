<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupplierController extends Controller
{
    use ApiResponse;

    public function callSheet(Request $request)
    {
        $q = DB::select(
            "
            SELECT
            DAY(sl.created_at) as day,
            YEAR(sl.created_at) as year,
            MONTH(sl.created_at) as month,
            sum(sl.qty * sl.unit_qty) as qty,
            i.name as name,
            CONCAT(YEAR(sl.created_at), '/', MONTH(sl.created_at), '/', DAY(sl.created_at)) as date
            FROM 
            sale_lines sl
            LEFT JOIN 
            items i
            on
            i.id = sl.item_id
            WHERE
            i.supplier_id = $request->supplier_id
            AND 
            sl.created_at BETWEEN '$request->start' AND '$request->end'

            GROUP BY
            sl.item_id,
            day
            "
        );

        return datatables($q)->make(true);
    }
}
