<?php

namespace App\Livewire\Ecommerce;

use App\Models\Ecommerce\EcommerceOrder;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class CustomerOrders extends Component
{
    use WithPagination;

    /** Free-text match against reference and note. */
    #[Url(except: '')]
    public string $search = '';

    /** Status slug: 'all', 'pending', 'verified', 'paid', 'preparing', 'picked_up', 'cancelled'. */
    #[Url(except: 'all')]
    public string $status = 'all';

    /** Sort key: 'newest', 'oldest', 'total_high', 'total_low'. */
    #[Url(except: 'newest')]
    public string $sort = 'newest';

    /**
     * Reset to page 1 whenever a filter changes — otherwise the
     * paginator can land us on a page that has nothing.
     */
    public function updating(string $name, $value): void
    {
        if (in_array($name, ['search', 'status', 'sort'], true)) {
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->status = 'all';
        $this->sort = 'newest';
        $this->resetPage();
    }

    /**
     * Pre-computed status options so the view can iterate over them
     * without rebuilding the array.
     *
     * @return array<int, array{slug: string, label: string, value: int|null}>
     */
    public function getStatusOptionsProperty(): array
    {
        return [
            ['slug' => 'all', 'label' => 'All', 'value' => null],
            ['slug' => 'pending', 'label' => 'Pending', 'value' => EcommerceOrder::STATUS_PENDING],
            ['slug' => 'verified', 'label' => 'Verified', 'value' => EcommerceOrder::STATUS_VERIFIED],
            ['slug' => 'paid', 'label' => 'Paid', 'value' => EcommerceOrder::STATUS_PAID],
            ['slug' => 'preparing', 'label' => 'Preparing', 'value' => EcommerceOrder::STATUS_PREPARING],
            ['slug' => 'picked_up', 'label' => 'Picked Up', 'value' => EcommerceOrder::STATUS_PICKED_UP],
            ['slug' => 'cancelled', 'label' => 'Cancelled', 'value' => EcommerceOrder::STATUS_CANCELLED],
        ];
    }

    public function hasActiveFilters(): bool
    {
        return $this->search !== '' || $this->status !== 'all' || $this->sort !== 'newest';
    }

    public function render(): \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\Illuminate\View\View
    {
        $customer = auth('customer')->user();

        $query = EcommerceOrder::where('customer_id', $customer->id)
            ->with('lines');

        $term = trim($this->search);
        if ($term !== '') {
            $like = '%'.$term.'%';
            $query->where(function ($q) use ($like) {
                $q->where('reference', 'like', $like)
                    ->orWhere('note', 'like', $like);
            });
        }

        $statusValue = collect($this->statusOptions)
            ->firstWhere('slug', $this->status)['value'] ?? null;
        if ($statusValue !== null) {
            $query->where('status', $statusValue);
        }

        match ($this->sort) {
            'oldest' => $query->orderBy('created_at'),
            'total_high' => $query->orderByDesc('total'),
            'total_low' => $query->orderBy('total'),
            default => $query->orderByDesc('created_at'),
        };

        // Total against the unfiltered customer scope so the empty
        // state can distinguish "no orders ever" from "no matches".
        $totalCount = EcommerceOrder::where('customer_id', $customer->id)->count();

        return view('livewire.ecommerce.customer-orders', [
            'orders' => $query->paginate(10),
            'totalCount' => $totalCount,
        ]);
    }
}
