<?php

namespace App\Services\Restaurant;

use App\Models\Products\Item;

class KitchenRoutingService
{
    /**
     * Resolve the kitchen station an item should be routed to.
     *
     * Item-level override wins; otherwise fall back to the item's
     * category default; null when neither is set (unrouted line).
     */
    public function resolveStation(Item $item): ?int
    {
        if ($item->kitchen_station_id) {
            return (int) $item->kitchen_station_id;
        }

        $categoryStation = $item->category?->kitchen_station_id
            ?? $item->loadMissing('category')->category?->kitchen_station_id;

        return $categoryStation ? (int) $categoryStation : null;
    }
}
