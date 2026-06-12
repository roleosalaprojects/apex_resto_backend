<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Pos\Receipt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReadingController extends Controller
{
    use ApiResponse;

    public function getNonVat(Request $request): JsonResponse
    {
        $receipt = Receipt::where('user_id', $request->user)->first();
        if ($receipt->hocus_pocus) {
            $q = DB::select(
                "
                SELECT
                    concat(year(created_at), '-', month(created_at)) as y,
                    format(sum(if(type = false, total - excess_non_vat, 0)), 2) as sales,
                    format(sum(if(type = true, total, 0)), 2) as refunds,
                    format(sum(if(type = false, non_vat - excess_non_vat, 0)), 2) as snon_vat,
                    format(sum(if(type = true, non_vat, 0)), 2) as rnon_vat,
                    format(sum(if(type = false, non_vat - excess_non_vat, 0)) - sum(if(type = true, non_vat, 0)), 2) as non_vat_net
                FROM sales
                WHERE
                    quarter(created_at) = ?
                    AND
                    year(created_at) = ?
                GROUP BY
                    YEAR(created_at), month(created_at)
                ",
                [$request->q, $request->year]
            );
        } else {
            $q = DB::select(
                "
                SELECT
                    concat(year(created_at), '-', month(created_at)) as y,
                    sum(if(type = false, total, 0)) as sales,
                    sum(if(type = true, total, 0)) as refunds,
                    sum(if(type = false, non_vat, 0)) as snon_vat,
                    sum(if(type = true, non_vat, 0)) as rnon_vat,
                    sum(if(type = false, non_vat, 0)) - sum(if(type = true, non_vat, 0)) as non_vat_net
                FROM sales
                WHERE
                    quarter(created_at) = ?
                    AND
                    year(created_at) = ?
                GROUP BY
                    YEAR(created_at), month(created_at)
                ",
                [$request->q, $request->year]
            );
        }

        return $this->success(['data' => $q]);
    }
}
