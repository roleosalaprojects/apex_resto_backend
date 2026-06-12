<?php

namespace App\Services;

use App\Models\Pos\Sale;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PeakHoursAnalysisService
{
    /**
     * Return the column expression for local-time grouping.
     *
     * Timestamps are already stored in the app timezone (Asia/Manila),
     * so no CONVERT_TZ is needed.
     */
    protected function localExpr(string $column = 'created_at'): string
    {
        return $column;
    }

    /**
     * Get heatmap data: average sales & receipts per hour (0-23) and day-of-week (1-7).
     *
     * Values are averaged across distinct dates so that days appearing more
     * often in the range don't inflate the numbers.
     *
     * @return array{heatmap: array, peak_hours: array, slow_hours: array, busiest_day: array}
     */
    public function getHeatmapData(int $userId, int $days = 30, ?int $storeId = null): array
    {
        $tz = config('app.timezone', 'Asia/Manila');
        $local = $this->localExpr();
        $distinctDates = "COUNT(DISTINCT DATE({$local}))";

        $query = Sale::where('user_id', $userId)
            ->where('cancelled', 0)
            ->where('created_at', '>=', Carbon::today($tz)->subDays($days))
            ->select(
                DB::raw("DAYOFWEEK({$local}) as day_of_week"),
                DB::raw("HOUR({$local}) as hour"),
                DB::raw("ROUND(SUM(total) / {$distinctDates}, 2) as avg_sales"),
                DB::raw("ROUND(COUNT(*) / {$distinctDates}, 1) as avg_transactions")
            )
            ->groupBy(DB::raw("DAYOFWEEK({$local})"), DB::raw("HOUR({$local})"))
            ->orderBy('day_of_week')
            ->orderBy('hour');

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        $data = $query->get();

        $heatmap = $data->map(function ($row) {
            return [
                'day' => (int) $row->day_of_week,
                'hour' => (int) $row->hour,
                'avg_sales' => round((float) $row->avg_sales, 2),
                'avg_transactions' => round((float) $row->avg_transactions, 1),
            ];
        })->toArray();

        $peakHours = $this->identifyPeakHours($data);
        $slowHours = $this->identifySlowHours($data);
        $busiestDay = $this->identifyBusiestDay($data);

        return [
            'heatmap' => $heatmap,
            'peak_hours' => $peakHours,
            'slow_hours' => $slowHours,
            'busiest_day' => $busiestDay,
        ];
    }

    /**
     * Get hourly breakdown for a specific date.
     *
     * @return array{hours: array}
     */
    public function getHourlyBreakdown(int $userId, string $date, ?int $storeId = null): array
    {
        $tz = config('app.timezone', 'Asia/Manila');
        $parsedDate = Carbon::parse($date, $tz);
        $local = $this->localExpr();

        $query = Sale::where('user_id', $userId)
            ->where('cancelled', 0)
            ->whereDate('created_at', $parsedDate)
            ->select(
                DB::raw("HOUR({$local}) as hour"),
                DB::raw('SUM(total) as sales'),
                DB::raw('COUNT(*) as transactions'),
                DB::raw('AVG(total) as avg_ticket')
            )
            ->groupBy(DB::raw("HOUR({$local})"))
            ->orderBy('hour');

        if ($storeId) {
            $query->where('store_id', $storeId);
        }

        $data = $query->get();

        $hours = $data->map(function ($row) {
            return [
                'hour' => (int) $row->hour,
                'sales' => round((float) $row->sales, 2),
                'transactions' => (int) $row->transactions,
                'avg_ticket' => round((float) $row->avg_ticket, 2),
            ];
        })->toArray();

        return ['hours' => $hours];
    }

    /**
     * Identify top 5 peak hours by average sales.
     */
    protected function identifyPeakHours(Collection $data): array
    {
        return $data->sortByDesc('avg_sales')
            ->take(5)
            ->map(function ($row) {
                return [
                    'day' => (int) $row->day_of_week,
                    'day_name' => $this->dayName((int) $row->day_of_week),
                    'hour' => (int) $row->hour,
                    'avg_sales' => round((float) $row->avg_sales, 2),
                    'avg_transactions' => round((float) $row->avg_transactions, 1),
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Identify bottom 5 slow hours by average sales.
     */
    protected function identifySlowHours(Collection $data): array
    {
        return $data->sortBy('avg_sales')
            ->take(5)
            ->map(function ($row) {
                return [
                    'day' => (int) $row->day_of_week,
                    'day_name' => $this->dayName((int) $row->day_of_week),
                    'hour' => (int) $row->hour,
                    'avg_sales' => round((float) $row->avg_sales, 2),
                    'avg_transactions' => round((float) $row->avg_transactions, 1),
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Identify the busiest day of the week by summing hourly averages.
     */
    protected function identifyBusiestDay(Collection $data): array
    {
        $byDay = $data->groupBy('day_of_week')->map(function ($group, $day) {
            return [
                'day' => (int) $day,
                'day_name' => $this->dayName((int) $day),
                'avg_daily_sales' => round($group->sum('avg_sales'), 2),
                'avg_daily_transactions' => round($group->sum('avg_transactions'), 1),
            ];
        });

        $busiest = $byDay->sortByDesc('avg_daily_sales')->first();

        return $busiest ?? [];
    }

    /**
     * Convert MySQL DAYOFWEEK (1=Sunday...7=Saturday) to day name.
     */
    protected function dayName(int $dayOfWeek): string
    {
        $days = [
            1 => 'Sunday',
            2 => 'Monday',
            3 => 'Tuesday',
            4 => 'Wednesday',
            5 => 'Thursday',
            6 => 'Friday',
            7 => 'Saturday',
        ];

        return $days[$dayOfWeek] ?? 'Unknown';
    }
}
