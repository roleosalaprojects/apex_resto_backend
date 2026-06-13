<?php

namespace App\Services;

use App\Models\Products\Item;
use App\Models\Products\ItemComponent;
use App\Models\Products\PriceHistory;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Recipe management for composite (made-to-order) items.
 *
 * A composite item carries no stock of its own; selling it explodes the
 * recipe and deducts each component's ItemStore (see UpdateItemStocksJob).
 * Cost is derived from the components unless cost_override is set.
 */
class CompositeItemService
{
    /**
     * Replace the composite's recipe with the given lines.
     *
     * @param  array<int, array{component_item_id: int|string, qty: float|string, notes?: string|null}>  $components
     */
    public function syncComponents(Item $item, array $components, int $userId): void
    {
        $this->assertComponentsActive($components);

        foreach ($components as $component) {
            if ($this->wouldCreateCycle($item, (int) $component['component_item_id'])) {
                throw new InvalidArgumentException(
                    'Component would create a recipe cycle (item #'.$component['component_item_id'].').'
                );
            }
        }

        DB::transaction(function () use ($item, $components, $userId) {
            ItemComponent::where('item_id', $item->id)->delete();

            foreach ($components as $component) {
                ItemComponent::create([
                    'item_id' => $item->id,
                    'component_item_id' => (int) $component['component_item_id'],
                    'qty' => $component['qty'],
                    'notes' => $component['notes'] ?? null,
                    'user_id' => $userId,
                ]);
            }

            $item->update(['is_composite' => count($components) > 0]);
        });

        $this->recalculateCost($item->fresh(), $userId);
    }

    /**
     * Re-derive cost from components and persist it, logging a
     * PriceHistory row. No-op when the owner pinned cost manually.
     */
    public function recalculateCost(Item $item, ?int $userId = null): void
    {
        if ($item->cost_override || ! $item->is_composite) {
            return;
        }

        $newCost = $item->computedComponentCost();

        if (round((float) $item->cost, 2) === $newCost) {
            return;
        }

        $oldCost = $item->cost;
        $item->update([
            'prev_cost' => $oldCost,
            'cost' => $newCost,
        ]);

        PriceHistory::create([
            'item_id' => $item->id,
            'old_price' => $item->price,
            'new_price' => $item->price,
            'old_cost' => $oldCost,
            'new_cost' => $newCost,
            'old_markup' => $item->markup,
            'new_markup' => $item->markup,
            'change_reason' => 'composite',
            'description' => 'Cost recalculated from recipe components',
            'user_id' => $userId,
        ]);
    }

    /**
     * BFS up the proposed component's own recipe tree: adding
     * $componentItemId under $item cycles iff $item is reachable
     * from $componentItemId via existing component edges.
     */
    public function wouldCreateCycle(Item $item, int $componentItemId): bool
    {
        if ($componentItemId === $item->id) {
            return true;
        }

        $queue = [$componentItemId];
        $visited = [];

        while ($queue !== []) {
            $current = array_shift($queue);
            if (isset($visited[$current])) {
                continue;
            }
            $visited[$current] = true;

            $childIds = ItemComponent::where('item_id', $current)
                ->pluck('component_item_id')
                ->all();

            foreach ($childIds as $childId) {
                if ((int) $childId === $item->id) {
                    return true;
                }
                $queue[] = (int) $childId;
            }
        }

        return false;
    }

    /**
     * @param  array<int, array{component_item_id: int|string}>  $components
     */
    public function assertComponentsActive(array $components): void
    {
        $ids = array_map(static fn (array $component) => (int) $component['component_item_id'], $components);

        if ($ids === []) {
            return;
        }

        $activeIds = Item::whereIn('id', $ids)->where('status', true)->pluck('id')->all();
        $inactive = array_diff($ids, $activeIds);

        if ($inactive !== []) {
            throw new InvalidArgumentException(
                'Inactive or missing component item(s): '.implode(', ', $inactive)
            );
        }
    }
}
