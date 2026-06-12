<?php

namespace App\Http\Controllers\API\v1\pos;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Settings\Pos;
use DB;
use Illuminate\Http\JsonResponse;

class ReadingController extends Controller
{
    use ApiResponse;

    public function getReadings(Pos $pos): JsonResponse
    {
        $x = DB::select(
            '
                SELECT
                    x.id,
                    "x" as type,
                    s.`name` as store,
                    x.counter,
                    p.number as terminal,
                    x.transactions,
                    format(x.cash, 2) as gross,
                    format(x.refunds, 2) as refunds,
                    format(x.cash - x.refunds, 2) as net,
                    u.`name` as employee,
                    x.created_at as date
                FROM
                    xreadings x
                LEFT JOIN
                    pos p
                ON
                    p.id = x.pos_id
                LEFT JOIN
                    stores s
                ON
                    s.id = x.store_id
                LEFT JOIN
                    users u
                ON
                    u.id = x.user_id
                WHERE
                x.pos_id = '.$pos->id
        );
        $z = DB::select(
            '
                SELECT
                    z.id,
                    "z" as type,
                    s.`name` as store,
                    z.counter,
                    p.number as terminal,
                    z.transactions,
                    format(z.cash, 2) as gross,
                    format(z.refund, 2) as refunds,
                    format(z.cash - z.refund, 2) as net,
                    u.`name` as employee,
                    z.created_at as date
                FROM
                    zreadings z
                LEFT JOIN
                    pos p
                ON
                    p.id = z.pos_id
                LEFT JOIN
                    stores s
                ON
                    s.id = z.store_id
                LEFT JOIN
                    users u
                ON
                    u.id = z.user_id
                WHERE
                z.pos_id = '.$pos->id
        );
        $data = array_merge($x, $z);

        return $this->success(['readings' => $data]);
    }
}
