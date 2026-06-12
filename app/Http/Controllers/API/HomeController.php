<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HomeController extends Controller
{
    use ApiResponse;

    public function salesView(): JsonResponse
    {
        $x = DB::select(
            '
            SELECT
                month(created_at) as `month`,
                year(created_at) as `year`,
                sum(if(type = false, total, 0)) as sales,
                sum(if(type = true, total, 0)) as refunds,
                sum(total) as gross,
                concat(
                    DATE_FORMAT(STR_TO_DATE(month(created_at), "%m"), "%b"),
                    ", ",
                    year(created_at)
                ) as date
            FROM
                sales
            GROUP BY
                month, year
            Order by
                year, month
            ASC;
            '
        );

        return $this->success($x);
    }

    public function salesTotal(): JsonResponse
    {
        $q = DB::select(
            '
            SELECT sum(if(type = false, total, 0)) as `total` FROM sales
            '
        );

        return $this->success($q);
    }

    public function customerView(): JsonResponse
    {
        $q = DB::select(
            '
                SELECT
                    MONTH(created_at) as `month`,
                    COUNT(id) as registered,
                    concat(
                        DATE_FORMAT(STR_TO_DATE(month(created_at), "%m"), "%b"),
                        ", ",
                        year(created_at)
                    ) as date
                FROM
                    customers
                WHERE
                        status = true
                GROUP BY
                    `month`
            '
        );

        return $this->success($q);
    }

    public function customersTotal(): JsonResponse
    {
        $q = DB::select(
            '
            SELECT sum(if(status = true, 1, 0)) as `total` FROM customers
            '
        );

        return $this->success($q);
    }

    public function itemsChart(): JsonResponse
    {
        $q = DB::select(
            '
                SELECT
                    sum(qty * unit_qty) as data,
                    i.name as label
                FROM
                    sale_lines sl
                LEFT JOIN
                    items i
                ON
                    i.id = sl.item_id
                LEFT JOIN
                    sales s
                ON
                    s.id = sl.sales_id
                WHERE
                    s.type = false
                GROUP BY
                    item_id
                ORDER BY
                    data
                DESC LIMIT 10;
            '
        );
        $sum = DB::select(
            '
                SELECT
                    sum(qty * unit_qty) as total
                FROM
                    sale_lines sl
                LEFT JOIN
                    sales s
                ON
                    s.id = sl.sales_id
                WHERE
                    s.type = false
            '
        );

        return $this->success(['data' => $q, 'sum' => $sum]);
    }
}
