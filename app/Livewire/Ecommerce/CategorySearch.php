<?php

namespace App\Livewire\Ecommerce;

use App\Models\Products\Category;
use Livewire\Component;

class CategorySearch extends Component
{
    public string $search = '';

    public function render(): \Illuminate\Contracts\View\View
    {
        $categories = Category::query()
            ->where('status', true)
            ->when($this->search, function ($query) {
                $query->where('name', 'like', '%'.$this->search.'%');
            })
            ->orderBy('name')
            ->get();

        return view('livewire.ecommerce.category-search', [
            'categories' => $categories,
        ]);
    }
}
