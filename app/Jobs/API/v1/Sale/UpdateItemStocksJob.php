<?php

namespace App\Jobs\API\v1\Sale;

use App\Models\Pos\Sale;
use App\Models\Pos\SaleLine;
use App\Models\Products\Item;
use App\Models\Products\ItemStore;
use App\Services\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateItemStocksJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private Sale $sale;

    /**
     * Create a new job instance.
     *
     * @param  bool  $restore  Force stock restoration regardless of sale type
     *                         (used when voiding a sale rather than refunding).
     */
    public function __construct(Sale $sale, private bool $restore = false)
    {
        $this->sale = $sale;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $lines = SaleLine::where('sales_id', $this->sale->id)->get();
        $storeId = $this->sale->store_id;
        // Find all products in item_stores. This is where the stocks are located.
        foreach ($lines as $line) {
            $item = Item::with('components')->find($line->item_id);

            // Composite (made-to-order) items carry no stock of their own:
            // explode the recipe and move each component's stock instead.
            if ($item && $item->is_composite && $item->components->isNotEmpty()) {
                foreach ($item->components as $component) {
                    $componentQty = (float) $component->qty * $line->qty * $line->unit_qty;
                    $this->adjustStock((int) $component->component_item_id, $storeId, $componentQty);
                }

                continue;
            }

            $this->adjustStock((int) $line->item_id, $storeId, $line->qty * $line->unit_qty);
        }
    }

    /**
     * Deduct (sale) or restore (refund) $qty from the item's stock at the
     * sale's store, firing the low-stock FCM alert on threshold crossing.
     */
    private function adjustStock(int $itemId, ?int $storeId, float $qty): void
    {
        $itemStore = ItemStore::where('item_id', $itemId)
            ->where('store_id', $storeId)
            ->first();

        if (! $itemStore) {
            \Log::warning('No item_store row for item '.$itemId.' at store '.$storeId.'; skipping stock update. son: '.$this->sale->son);

            return;
        }

        $oldStock = $itemStore->stock;
        \Log::debug('item_store_id:'.$itemStore->id.' old stock:'.$oldStock);
        /*
         * Update the current stock. Since this is items taken out of the establishment we need to deduct.
         * We also need to consider the Refund.
         * if type = false (considered as sold)
         * if type = true (considered as return)
         * A void forces restoration regardless of the original type.
         * */
        $isDeduction = ! $this->restore && $this->sale->type == false;
        if ($isDeduction) {
            // Sale
            $newStock = $itemStore->stock - $qty;
        } else {
            // Return / void restoration
            $newStock = $itemStore->stock + $qty;
        }
        $itemStore->update(['stock' => $newStock]);
        \Log::info('Stocks successfully updated! from '.$oldStock.' to '.$newStock.' son: '.$this->sale->son.PHP_EOL);

        // Notify if stock dropped to low/critical/out-of-stock
        if ($isDeduction && $newStock <= 10 && $oldStock > 10) {
            try {
                $itemName = Item::find($itemId)->name ?? "Item #{$itemId}";
                $level = $newStock <= 0 ? 'OUT OF STOCK' : ($newStock <= 5 ? 'Critical' : 'Low');
                (new FcmService)->sendToAll(
                    "{$level} Stock Alert",
                    "{$itemName} — {$newStock} remaining",
                    ['type' => 'low_stock', 'id' => (string) $itemId]
                );
            } catch (\Exception $e) {
                \Log::warning('FCM notification failed for low stock: '.$e->getMessage());
            }
        }
    }
}
