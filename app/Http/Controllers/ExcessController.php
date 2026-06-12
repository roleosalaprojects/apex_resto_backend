<?php

namespace App\Http\Controllers;

use App\Models\Pos\Sale;
use App\Models\Pos\SaleLine;

class ExcessController extends Controller
{
    //
    public function normalizeProfit()
    {

        $sales = Sale::where('status', auth()->user()->user_id)->get();
        foreach ($sales as $sale) {
            $sale_line = SaleLine::where('sales_id')->get();
            foreach ($sale_line as $line) {

            }
        }
        echo 'ok!';
    }
}
