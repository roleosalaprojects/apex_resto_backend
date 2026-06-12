<?php

namespace App\Jobs\API\v1\Sale;

use App\Models\Pos\Sale;
use App\Models\Pos\SaleLine;
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
     */
    public function __construct(Sale $sale)
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
            // Find the first item in sale_line
            $itemStore = ItemStore::where('item_id', $line->item_id)
                ->where('store_id', $storeId)
                ->first();
            $oldStock = $itemStore->stock;
            \Log::debug('item_store_id:'.$itemStore->id.' old stock:'.$oldStock);
            /*
             * Update the current stock. Since this is items taken out of the establishment we need to deduct.
             * We also need to consider the Refund.
             * if type = false (considered as sold)
             * if type = true (considered as return)
             * */
            $newStock = $itemStore->stock;
            if ($this->sale->type == false) {
                // Sale
                $newStock = $itemStore->stock - ($line->qty * $line->unit_qty);
            } else {
                // Return
                $newStock = $itemStore->stock + ($line->qty * $line->unit_qty);
            }
            $itemStore->update(['stock' => $newStock]);
            \Log::info('Stocks successfully updated! from '.$oldStock.' to '.$newStock.' son: '.$this->sale->son.PHP_EOL);

            // Notify if stock dropped to low/critical/out-of-stock
            if ($this->sale->type == false && $newStock <= 10 && $oldStock > 10) {
                try {
                    $itemName = $line->item->name ?? "Item #{$line->item_id}";
                    $level = $newStock <= 0 ? 'OUT OF STOCK' : ($newStock <= 5 ? 'Critical' : 'Low');
                    (new FcmService)->sendToAll(
                        "{$level} Stock Alert",
                        "{$itemName} — {$newStock} remaining",
                        ['type' => 'low_stock', 'id' => (string) $line->item_id]
                    );
                } catch (\Exception $e) {
                    \Log::warning('FCM notification failed for low stock: '.$e->getMessage());
                }
            }
        }
    }
}
