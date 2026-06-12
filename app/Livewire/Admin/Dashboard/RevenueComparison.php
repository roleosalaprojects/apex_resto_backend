<?php

namespace App\Livewire\Admin\Dashboard;

use App\Models\Pos\Sale;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class RevenueComparison extends Component
{
    public float $todaySales = 0;

    public float $yesterdaySales = 0;

    public float $lastWeekSameDaySales = 0;

    public int $todayTransactions = 0;

    public int $yesterdayTransactions = 0;

    public int $lastWeekTransactions = 0;

    public float $vsYesterdayPercent = 0;

    public float $vsLastWeekPercent = 0;

    public function mount(): void
    {
        $this->loadComparison();
    }

    public function loadComparison(): void
    {
        $today = today();
        $yesterday = today()->subDay();
        $lastWeekSameDay = today()->subWeek();

        // Today's sales
        $todayData = $this->getSalesData($today);
        $this->todaySales = $todayData['total'];
        $this->todayTransactions = $todayData['count'];

        // Yesterday's sales
        $yesterdayData = $this->getSalesData($yesterday);
        $this->yesterdaySales = $yesterdayData['total'];
        $this->yesterdayTransactions = $yesterdayData['count'];

        // Last week same day
        $lastWeekData = $this->getSalesData($lastWeekSameDay);
        $this->lastWeekSameDaySales = $lastWeekData['total'];
        $this->lastWeekTransactions = $lastWeekData['count'];

        // Calculate percentage changes
        $this->vsYesterdayPercent = $this->calculatePercentChange($this->todaySales, $this->yesterdaySales);
        $this->vsLastWeekPercent = $this->calculatePercentChange($this->todaySales, $this->lastWeekSameDaySales);
    }

    protected function getSalesData($date): array
    {
        $result = Sale::query()
            ->where('type', 0) // Only sales
            ->whereDate('created_at', $date)
            ->select(
                DB::raw('COALESCE(SUM(total), 0) as total'),
                DB::raw('COUNT(*) as count')
            )
            ->first();

        return [
            'total' => (float) ($result->total ?? 0),
            'count' => (int) ($result->count ?? 0),
        ];
    }

    protected function calculatePercentChange(float $current, float $previous): float
    {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    public function refresh(): void
    {
        $this->loadComparison();
    }

    public function render()
    {
        return view('livewire.admin.dashboard.revenue-comparison');
    }
}
