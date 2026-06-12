<?php

namespace App\Livewire\Ecommerce;

use App\Models\Products\Category;
use App\Models\Products\Item;
use Livewire\Attributes\Url;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

class ProductPage extends Component
{
    use WithPagination;

    #[Url]
    #[Validate('nullable|string')]
    public string $query = '';

    #[Url]
    public ?int $category = null;

    public ?string $categoryName = null;

    private $products = [];

    public function mount(): void
    {
        $this->loadCategoryName();
    }

    public function updatedCategory(): void
    {
        $this->resetPage();
        $this->loadCategoryName();
    }

    public function filterCategory(?int $categoryId): void
    {
        $this->category = $categoryId;
        $this->resetPage();
        $this->loadCategoryName();
    }

    public function clearCategory(): void
    {
        $this->category = null;
        $this->categoryName = null;
        $this->resetPage();
    }

    private function loadCategoryName(): void
    {
        if ($this->category) {
            $this->categoryName = Category::where('id', $this->category)
                ->where('status', true)
                ->value('name');

            if (! $this->categoryName) {
                $this->category = null;
            }
        } else {
            $this->categoryName = null;
        }
    }

    private function searchProducts(): void
    {
        // Listing shows every active item regardless of stock level.
        // The product detail page surfaces the actual stock state via the
        // pill + "Out of Stock" CTA so customers know what's available.
        $query = Item::query()
            ->where('status', true)
            ->orderBy('name', 'ASC')
            ->where(function ($q) {
                $q->where('name', 'like', '%'.$this->query.'%');
                $q->orWhere('barcode', 'like', '%'.$this->query.'%');
            })
            ->with(['wholesalePriceTiers', 'category']);

        if ($this->category) {
            $query->where('category_id', $this->category);
        }

        $this->products = $query->paginate(24);
    }

    public function render(): \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\Illuminate\View\View
    {
        $this->searchProducts();

        return view('livewire.ecommerce.product-page', [
            'products' => $this->products,
        ]);
    }

    public function search(): void
    {
        $this->resetPage();
        $this->searchProducts();
    }
}
