<?php

namespace App\Services;

use App\Models\CustomerRelations\Customer;
use App\Models\Products\Item;
use App\Models\Products\WholesalePriceTier;
use Illuminate\Support\Collection;

class WholesalePricingService
{
    public function getRetailPrice(Item $item): float
    {
        return (float) $item->price;
    }

    public function getPrice(Item $item, ?Customer $customer, int $qty = 1): float
    {
        if (! $customer) {
            return $this->getRetailPrice($item);
        }

        $tier = $this->getApplicableTier($item->id, $qty);

        if (! $tier) {
            return $this->getRetailPrice($item);
        }

        return max(0, (float) $item->price - (float) $tier->discount);
    }

    public function getApplicableTier(int $itemId, int $qty): ?WholesalePriceTier
    {
        return WholesalePriceTier::where('item_id', $itemId)
            ->where('min_qty', '<=', $qty)
            ->orderBy('min_qty', 'desc')
            ->first();
    }

    public function getTiers(int $itemId): Collection
    {
        return WholesalePriceTier::where('item_id', $itemId)
            ->orderBy('min_qty', 'asc')
            ->get();
    }

    public function getMinOrderQty(int $itemId, ?Customer $customer): int
    {
        return 1;
    }
}
