<?php

namespace App\Jobs;

use App\Models\Products\Item;
use App\Models\Products\ItemComponent;
use App\Services\CompositeItemService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Cascade a component item's cost change up to every composite that
 * (directly or transitively) uses it. The visited set guards against
 * re-processing in diamond-shaped recipe graphs.
 */
class RecalculateCompositeCostsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly int $itemId, private readonly ?int $userId = null) {}

    public function handle(CompositeItemService $compositeItemService): void
    {
        $queue = [$this->itemId];
        $visited = [];

        while ($queue !== []) {
            $current = array_shift($queue);

            $parentIds = ItemComponent::where('component_item_id', $current)
                ->pluck('item_id')
                ->all();

            foreach ($parentIds as $parentId) {
                $parentId = (int) $parentId;
                if (isset($visited[$parentId])) {
                    continue;
                }
                $visited[$parentId] = true;

                $parent = Item::find($parentId);
                if ($parent) {
                    $compositeItemService->recalculateCost($parent, $this->userId);
                }

                $queue[] = $parentId;
            }
        }
    }
}
