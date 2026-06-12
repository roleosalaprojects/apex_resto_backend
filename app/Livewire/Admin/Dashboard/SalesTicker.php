<?php

namespace App\Livewire\Admin\Dashboard;

use App\Models\Pos\Sale;
use Illuminate\Support\Collection;
use Livewire\Component;

class SalesTicker extends Component
{
    public Collection $recentSales;

    public int $lastSaleId = 0;

    public function mount(): void
    {
        $this->loadRecentSales();
    }

    public function loadRecentSales(): void
    {
        $this->recentSales = Sale::query()
            ->with(['sold_by', 'store'])
            ->where('type', 0) // Only sales, not refunds
            ->whereDate('created_at', today())
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        if ($this->recentSales->isNotEmpty()) {
            $this->lastSaleId = $this->recentSales->first()->id;
        }
    }

    public function checkForNewSales(): void
    {
        $newSales = Sale::query()
            ->with(['sold_by', 'store'])
            ->where('type', 0)
            ->where('id', '>', $this->lastSaleId)
            ->whereDate('created_at', today())
            ->orderByDesc('created_at')
            ->get();

        if ($newSales->isNotEmpty()) {
            $this->lastSaleId = $newSales->first()->id;
            $this->recentSales = $newSales->merge($this->recentSales)->take(10);

            // Dispatch browser event for animation
            $this->dispatch('new-sale', saleId: $newSales->first()->id);
        }
    }

    public function render()
    {
        return view('livewire.admin.dashboard.sales-ticker');
    }
}
