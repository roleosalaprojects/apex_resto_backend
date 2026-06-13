<?php

namespace App\Observers;

use App\Jobs\RecalculateCompositeCostsJob;
use App\Models\Products\Item;

class ItemCostObserver
{
    /**
     * When an ingredient's cost moves, every composite above it in the
     * recipe graph must re-derive its own cost. Queued so bulk price
     * updates don't fan out synchronously inside the request.
     */
    public function updated(Item $item): void
    {
        if (! $item->wasChanged('cost')) {
            return;
        }

        if (! $item->usedIn()->exists()) {
            return;
        }

        RecalculateCompositeCostsJob::dispatch($item->id, auth()->id());
    }
}
