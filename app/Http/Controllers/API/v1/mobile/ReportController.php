<?php

namespace App\Http\Controllers\API\v1\mobile;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\v1\mobile\Report\GetSaleRequest;
use App\Http\Traits\ApiResponse;
use App\Models\Pos\Sale;
use App\Models\Pos\SaleLine;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    use ApiResponse;

    public function salesSummary(GetSaleRequest $request): JsonResponse
    {
        if (! \Auth::user()->role->sls) {
            return $this->forbidden('You do not have permission to view sales.');
        }
        ini_set('memory_limit', -1);
        ini_set('max_execution_time', -1);
        $validated = $request->validated();
        $startDate = Carbon::parse($validated['startDate'])->startOfDay();
        $endDate = Carbon::parse($validated['endDate'])->endOfDay();

        $startDayString = $startDate->toDateTimeString();
        $endDayString = $endDate->toDateTimeString();

        // Years
        $dateDiffYears = floor($startDate->diffInYears($endDate));
        // Months
        $dateDiffMonths = floor($startDate->diffInMonths($endDate));
        // Days
        $dateDiffDays = floor($startDate->diffInDays($endDate));
        // Chart Data Response
        $query = Sale::whereBetween('created_at', [$startDayString, $endDayString]);
        if ($dateDiffYears == 0) {
            if ($dateDiffMonths == 0) {
                if ($dateDiffDays > 0) {
                    $query->select(
                        DB::raw('sum(if(type = 0, total, 0)) as sales'),
                        DB::raw('sum(if(type = 1, total, 0)) as refunds'),
                        DB::raw('sum(if(type = 0, profit, -profit)) as revenue'),
                        DB::raw('count(id) as receipts'),
                        DB::raw('DATE_FORMAT(created_at, "%b %d, %y") as `time`'))
                        ->groupBy(DB::raw('day(created_at)'));
                } elseif ($dateDiffDays == 0) {
                    $query->select(
                        DB::raw('sum(if(type = 0, total, 0)) as sales'),
                        DB::raw('sum(if(type = 1, total, 0)) as refunds'),
                        DB::raw('sum(if(type = 0, profit, -profit)) as revenue'),
                        DB::raw('count(id) as receipts'),
                        DB::raw('DATE_FORMAT(created_at, "%h:00 %p") as `time`'),
                    )
                        ->groupBy(DB::raw('hour(created_at)'));
                }
            } else {
                $query->select(
                    DB::raw('sum(if(type = 0, total, 0)) as sales'),
                    DB::raw('sum(if(type = 1, total, 0)) as refunds'),
                    DB::raw('sum(if(type = 0, profit, -profit)) as revenue'),
                    DB::raw('count(id) as receipts'),
                    DB::raw('DATE_FORMAT(created_at, "%b %Y") as `time`'),
                )
                    ->groupBy(DB::raw('month(created_at)'));
            }
        } else {
            $query->select(
                DB::raw('sum(if(type = 0, total, 0)) as sales'),
                DB::raw('sum(if(type = 1, total, 0)) as refunds'),
                DB::raw('sum(if(type = 0, profit, -profit)) as revenue'),
                DB::raw('count(id) as receipts'),
                DB::raw('DATE_FORMAT(created_at, "%Y") as `time`'),
            )
                ->groupBy(DB::raw('year(created_at)'));
        }

        if ($request->store_select) {
            $query->where('sales.store_id', $request->store_select);
        }

        $gross = Sale::select(
            DB::raw('sum(if(type = 0, total, 0)) as sales'),
            DB::raw('sum(if(type = 0, profit, -profit)) as revenue'),
            DB::raw('sum(if(type = 1, total, 0)) as refunds'),
        )
            ->whereBetween('created_at', [$startDate, $endDate]);

        $receipts = Sale::whereBetween('created_at', [$startDayString, $endDayString])->count('id');

        return $this->success([
            'sales' => $gross->first(),
            'chart' => $query->orderBy('created_at', 'ASC')->get(),
            'receipts' => $receipts,
        ]);
    }

    public function itemsData(Request $request): JsonResponse
    {
        ini_set('memory_limit', -1);
        ini_set('max_execution_time', -1);
        $startDate = Carbon::parse($request->startDate)->startOfDay();
        $endDate = Carbon::parse($request->endDate)->endOfDay();

        $startDayString = $startDate->toDateTimeString();
        $endDayString = $endDate->toDateTimeString();

        $items = SaleLine::whereBetween('s.created_at', [$startDayString, $endDayString])
            ->leftJoin('sales as s', 'sale_lines.sales_id', 's.id')
            ->leftJoin('items as i', 'i.id', 'sale_lines.item_id')
            ->select(
                DB::raw('sum(if(s.type = 0, (qty * unit_qty), -(qty * unit_qty))) as items_sold'),
                DB::raw('sum(if(s.type = 0, sub_total, -sub_total)) as net_sales'),
                DB::raw('sum(if(s.type = 0, sub_total - (qty * sale_lines.cost * unit_qty), -(sub_total - (qty * sale_lines.cost * unit_qty)))) as revenue'),
                DB::raw('i.name as item'),
                DB::raw('i.id as item_id'),
            )
            ->groupBy('item_id')
            ->orderBy('net_sales', 'DESC');

        $limit = $request->input('limit');
        if ($limit && $limit > 0) {
            $items = $items->take((int) $limit);
        }

        $items = $items->get();

        return $this->success(['items' => $items]);
    }

    /**
     * Get performance data for a specific product
     */
    public function productPerformance(Request $request, int $itemId): JsonResponse
    {
        ini_set('memory_limit', -1);
        ini_set('max_execution_time', -1);

        $startDate = Carbon::parse($request->startDate)->startOfDay();
        $endDate = Carbon::parse($request->endDate)->endOfDay();

        $startDayString = $startDate->toDateTimeString();
        $endDayString = $endDate->toDateTimeString();

        // Calculate date difference to determine grouping
        $dateDiffDays = floor($startDate->diffInDays($endDate));
        $dateDiffMonths = floor($startDate->diffInMonths($endDate));

        // Build query for chart data with dynamic grouping
        $query = SaleLine::whereBetween('s.created_at', [$startDayString, $endDayString])
            ->leftJoin('sales as s', 'sale_lines.sales_id', 's.id')
            ->where('sale_lines.item_id', $itemId);

        if ($dateDiffMonths > 0) {
            // Group by month for longer periods
            $query->select(
                DB::raw('sum(if(s.type = 0, (qty * unit_qty), -(qty * unit_qty))) as units_sold'),
                DB::raw('sum(if(s.type = 0, sub_total, -sub_total)) as net_sales'),
                DB::raw('sum(if(s.type = 0, sub_total - (qty * sale_lines.cost * unit_qty), -(sub_total - (qty * sale_lines.cost * unit_qty)))) as revenue'),
                DB::raw('DATE_FORMAT(s.created_at, "%b %Y") as `time`'),
                DB::raw('DATE(s.created_at) as sort_date')
            )->groupBy(DB::raw('YEAR(s.created_at), MONTH(s.created_at)'));
        } elseif ($dateDiffDays > 0) {
            // Group by day for medium periods
            $query->select(
                DB::raw('sum(if(s.type = 0, (qty * unit_qty), -(qty * unit_qty))) as units_sold'),
                DB::raw('sum(if(s.type = 0, sub_total, -sub_total)) as net_sales'),
                DB::raw('sum(if(s.type = 0, sub_total - (qty * sale_lines.cost * unit_qty), -(sub_total - (qty * sale_lines.cost * unit_qty)))) as revenue'),
                DB::raw('DATE_FORMAT(s.created_at, "%b %d") as `time`'),
                DB::raw('DATE(s.created_at) as sort_date')
            )->groupBy(DB::raw('DATE(s.created_at)'));
        } else {
            // Group by hour for single day
            $query->select(
                DB::raw('sum(if(s.type = 0, (qty * unit_qty), -(qty * unit_qty))) as units_sold'),
                DB::raw('sum(if(s.type = 0, sub_total, -sub_total)) as net_sales'),
                DB::raw('sum(if(s.type = 0, sub_total - (qty * sale_lines.cost * unit_qty), -(sub_total - (qty * sale_lines.cost * unit_qty)))) as revenue'),
                DB::raw('DATE_FORMAT(s.created_at, "%h:00 %p") as `time`'),
                DB::raw('s.created_at as sort_date')
            )->groupBy(DB::raw('HOUR(s.created_at)'));
        }

        $chartData = $query->orderBy('sort_date', 'ASC')->get();

        // Get summary totals
        $summary = SaleLine::whereBetween('s.created_at', [$startDayString, $endDayString])
            ->leftJoin('sales as s', 'sale_lines.sales_id', 's.id')
            ->where('sale_lines.item_id', $itemId)
            ->select(
                DB::raw('sum(if(s.type = 0, (qty * unit_qty), -(qty * unit_qty))) as total_units_sold'),
                DB::raw('sum(if(s.type = 0, sub_total, -sub_total)) as total_sales'),
                DB::raw('sum(if(s.type = 0, sub_total - (qty * sale_lines.cost * unit_qty), -(sub_total - (qty * sale_lines.cost * unit_qty)))) as total_revenue'),
                DB::raw('count(DISTINCT s.id) as transaction_count')
            )
            ->first();

        return $this->success([
            'chart' => $chartData,
            'summary' => $summary,
        ]);
    }
}
