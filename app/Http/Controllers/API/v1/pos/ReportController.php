<?php

namespace App\Http\Controllers\API\v1\pos;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Pos\Sale;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        ini_set('memory_limit', -1);
        ini_set('max_execution_time', -1);

        $startDate = Carbon::parse($request->startDate)->startOfDay();
        $endDate = Carbon::parse($request->endDate)->endOfDay();

        $startDayString = $startDate->toDateTimeString();
        $endDayString = $endDate->toDateTimeString();

        // Years
        $dateDiffYears = $startDate->diffInYears($endDate);
        // Months
        $dateDiffMonths = $startDate->diffInMonths($endDate);
        // Days
        $dateDiffDays = $startDate->diffInDays($endDate);
        // Chart Data Response
        $query = Sale::whereBetween('created_at', [$startDayString, $endDayString]);
        if ($dateDiffYears == 0) {
            if ($dateDiffMonths == 0) {
                if ($dateDiffDays > 0) {
                    $query->select(
                        DB::raw('sum(if(type = 0, total, 0)) as sales'),
                        DB::raw('sum(if(type = 1, total, 0)) as refunds'),
                        DB::raw('sum(if(type = 0, profit, -profit)) as revenue'),
                        DB::raw('DATE_FORMAT(created_at, "%b %d, %y") as `time`'))
                        ->groupBy(DB::raw('day(created_at)'));
                } elseif ($dateDiffDays == 0) {
                    $query->select(
                        DB::raw('sum(if(type = 0, total, 0)) as sales'),
                        DB::raw('sum(if(type = 1, total, 0)) as refunds'),
                        DB::raw('sum(if(type = 0, profit, -profit)) as revenue'),
                        DB::raw('DATE_FORMAT(created_at, "%h:00 %p") as `time`')
                    )
                        ->groupBy(DB::raw('hour(created_at)'));
                }
            } else {
                $query->select(
                    DB::raw('sum(if(type = 0, total, 0)) as sales'),
                    DB::raw('sum(if(type = 1, total, 0)) as refunds'),
                    DB::raw('sum(if(type = 0, profit, -profit)) as revenue'),
                    DB::raw('DATE_FORMAT(created_at, "%b %Y") as `time`')
                )
                    ->groupBy(DB::raw('month(created_at)'));
            }
        } else {
            $query->select(
                DB::raw('sum(if(type = 0, total, 0)) as sales'),
                DB::raw('sum(if(type = 1, total, 0)) as refunds'),
                DB::raw('sum(if(type = 0, profit, -profit)) as revenue'),
                DB::raw('DATE_FORMAT(created_at, "%Y") as `time`')
            )
                ->groupBy(DB::raw('year(created_at)'));
        }

        if ($request->store_select) {
            $query->where('sales.store_id', $request->store_select);
        }

        $gross = Sale::select(
            DB::raw('sum(if(type = 0, total, 0)) as sales'),
            DB::raw('sum(if(type = 0, profit, -profit)) as revenue'),
            DB::raw('sum(if(type = 1, total, 0)) as refunds')
        )
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($request->store_select) {
            $gross->where('sales.store_id', $request->store_select);
        }

        return $this->success([
            'sales' => $gross->first(),
            'chart' => $query->get(),
            'table' => DataTables($query)->make(true),
        ]);
    }
}
