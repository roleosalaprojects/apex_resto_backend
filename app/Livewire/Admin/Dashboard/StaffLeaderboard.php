<?php

namespace App\Livewire\Admin\Dashboard;

use App\Models\Pos\Sale;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class StaffLeaderboard extends Component
{
    public Collection $leaderboard;

    public float $totalTeamSales = 0;

    public function mount(): void
    {
        $this->loadLeaderboard();
    }

    public function loadLeaderboard(): void
    {
        $this->leaderboard = Sale::query()
            ->join('users', 'sales.sales_by', '=', 'users.id')
            ->leftJoin('employees', 'users.id', '=', 'employees.user_id')
            ->where('sales.type', 0) // Only sales
            ->whereDate('sales.created_at', today())
            ->select(
                'users.id',
                'users.name',
                'employees.image',
                DB::raw('SUM(sales.total) as total_sales'),
                DB::raw('COUNT(sales.id) as transaction_count'),
                DB::raw('AVG(sales.total) as avg_transaction')
            )
            ->groupBy('users.id', 'users.name', 'employees.image')
            ->orderByDesc('total_sales')
            ->limit(5)
            ->get();

        $this->totalTeamSales = $this->leaderboard->sum('total_sales');
    }

    public function refresh(): void
    {
        $this->loadLeaderboard();
    }

    public function render()
    {
        return view('livewire.admin.dashboard.staff-leaderboard');
    }
}
