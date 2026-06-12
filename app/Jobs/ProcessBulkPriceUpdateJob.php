<?php

namespace App\Jobs;

use App\Models\BulkOperationLog;
use App\Models\Products\Item;
use App\Models\Products\PriceHistory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class ProcessBulkPriceUpdateJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<int>  $itemIds
     */
    public function __construct(
        public BulkOperationLog $log,
        public array $itemIds,
        public string $updateType,
        public string $field,
        public float $value,
        public string $direction,
        public int $userId
    ) {}

    public function handle(): void
    {
        $this->log->markAsProcessing();

        try {
            foreach ($this->itemIds as $itemId) {
                try {
                    $this->processItem($itemId);
                    $this->log->incrementSuccess();
                } catch (\Exception $e) {
                    $this->log->addError([
                        'item_id' => $itemId,
                        'message' => $e->getMessage(),
                    ]);
                    $this->log->incrementFailed();
                }
                $this->log->incrementProcessed();
            }

            $this->log->markAsCompleted();
        } catch (\Exception $e) {
            $this->log->addError(['message' => $e->getMessage()]);
            $this->log->markAsFailed();
        }
    }

    private function processItem(int $itemId): void
    {
        DB::transaction(function () use ($itemId) {
            $item = Item::findOrFail($itemId);
            $oldValue = $item->{$this->field};
            $newValue = $this->calculateNewValue($oldValue);

            $priceHistoryData = [
                'item_id' => $item->id,
                'change_reason' => 'bulk',
                'description' => 'Bulk price update',
                'user_id' => $this->userId,
            ];

            if ($this->field === 'price') {
                $item->prev_price = $item->price;
                $item->price = $newValue;
                $priceHistoryData['old_price'] = $oldValue;
                $priceHistoryData['new_price'] = $newValue;
            } elseif ($this->field === 'cost') {
                $item->prev_cost = $item->cost;
                $item->cost = $newValue;
                $priceHistoryData['old_cost'] = $oldValue;
                $priceHistoryData['new_cost'] = $newValue;
            } elseif ($this->field === 'markup') {
                $item->markup = $newValue;
                $priceHistoryData['old_markup'] = $oldValue;
                $priceHistoryData['new_markup'] = $newValue;
            }

            $item->save();
            PriceHistory::create($priceHistoryData);
        });
    }

    private function calculateNewValue(float $currentValue): float
    {
        if ($this->updateType === 'fixed') {
            $change = $this->value;
        } else {
            $change = $currentValue * ($this->value / 100);
        }

        if ($this->direction === 'decrease') {
            $newValue = $currentValue - $change;
        } else {
            $newValue = $currentValue + $change;
        }

        return max(0, round($newValue, 2));
    }
}
