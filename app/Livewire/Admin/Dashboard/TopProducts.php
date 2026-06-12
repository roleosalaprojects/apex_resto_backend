<?php

namespace App\Livewire\Admin\Dashboard;

use App\Models\Pos\SaleLine;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class TopProducts extends Component
{
    public Collection $topProducts;

    public function mount(): void
    {
        $this->loadTopProducts();
    }

    public function loadTopProducts(): void
    {
        $this->topProducts = SaleLine::query()
            ->join('sales', 'sale_lines.sales_id', '=', 'sales.id')
            ->join('items', 'sale_lines.item_id', '=', 'items.id')
            ->where('sales.type', 0) // Only sales, not refunds
            ->whereDate('sales.created_at', today())
            ->select(
                'items.id',
                'items.name',
                'items.image',
                DB::raw('SUM(sale_lines.qty) as total_qty'),
                DB::raw('SUM(sale_lines.sub_total) as total_sales'),
                DB::raw('COUNT(DISTINCT sales.id) as transaction_count')
            )
            ->groupBy('items.id', 'items.name', 'items.image')
            ->orderByDesc('total_sales')
            ->limit(5)
            ->get();
    }

    public function refresh(): void
    {
        $this->loadTopProducts();
    }

    public function render()
    {
        return view('livewire.admin.dashboard.top-products');
    }
}
