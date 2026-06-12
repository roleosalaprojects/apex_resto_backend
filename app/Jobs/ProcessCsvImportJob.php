<?php

namespace App\Jobs;

use App\Models\BulkOperationLog;
use App\Models\InventoryManagement\Supplier;
use App\Models\Products\Category;
use App\Models\Products\Item;
use App\Models\Products\ItemStore;
use App\Models\Products\PriceHistory;
use App\Models\Settings\Store;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProcessCsvImportJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public BulkOperationLog $log,
        public string $filePath,
        public bool $updateExisting,
        public int $userId
    ) {}

    public function handle(): void
    {
        $this->log->markAsProcessing();

        try {
            $this->processFile();
            $this->log->markAsCompleted();
        } catch (\Exception $e) {
            $this->log->addError(['message' => $e->getMessage()]);
            $this->log->markAsFailed();
        } finally {
            Storage::delete($this->filePath);
        }
    }

    private function processFile(): void
    {
        $fullPath = Storage::path($this->filePath);
        $handle = fopen($fullPath, 'r');

        if ($handle === false) {
            throw new \RuntimeException('Could not open file');
        }

        $headers = fgetcsv($handle);
        if ($headers === false) {
            fclose($handle);
            throw new \RuntimeException('Could not read CSV headers');
        }

        $headers = array_map('strtolower', array_map('trim', $headers));
        $stores = Store::where('status', true)->pluck('id', 'name')->toArray();

        $rowNumber = 1;
        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;
            try {
                $data = array_combine($headers, $row);
                if ($data === false) {
                    throw new \RuntimeException('Invalid row format');
                }
                $this->processRow($data, $stores);
                $this->log->incrementSuccess();
            } catch (\Exception $e) {
                $this->log->addError([
                    'row' => $rowNumber,
                    'message' => $e->getMessage(),
                ]);
                $this->log->incrementFailed();
            }
            $this->log->incrementProcessed();
        }

        fclose($handle);
    }

    /**
     * @param  array<string, string>  $data
     * @param  array<string, int>  $stores
     */
    private function processRow(array $data, array $stores): void
    {
        DB::transaction(function () use ($data, $stores) {
            $barcode = trim($data['barcode'] ?? '');
            $name = strtoupper(trim($data['name'] ?? ''));

            if (empty($name)) {
                throw new \RuntimeException('Name is required');
            }

            $existingItem = null;
            if (! empty($barcode)) {
                $existingItem = Item::query()
                    ->where('barcode', $barcode)
                    ->where('status', true)
                    ->first();
            }

            if ($existingItem && ! $this->updateExisting) {
                throw new \RuntimeException("Item with barcode '{$barcode}' already exists");
            }

            $categoryId = $this->resolveCategoryId($data['category'] ?? '');
            $supplierId = $this->resolveSupplierId($data['supplier'] ?? '');

            $itemData = [
                'barcode' => $barcode,
                'name' => $name,
                'category_id' => $categoryId,
                'supplier_id' => $supplierId,
                'cost' => (float) ($data['cost'] ?? 0),
                'markup' => (float) ($data['markup'] ?? 0),
                'price' => (float) ($data['price'] ?? 0),
                'vatable' => $this->parseBoolean($data['vatable'] ?? '1'),
                'type' => $this->parseType($data['type'] ?? 'pc'),
                'status' => $this->parseBoolean($data['status'] ?? '1'),
                'user_id' => $this->userId,
            ];

            if ($existingItem) {
                $this->updateItem($existingItem, $itemData);
                $item = $existingItem;
            } else {
                $item = Item::create($itemData);
                PriceHistory::create([
                    'item_id' => $item->id,
                    'old_price' => null,
                    'new_price' => $item->price,
                    'old_cost' => null,
                    'new_cost' => $item->cost,
                    'old_markup' => null,
                    'new_markup' => $item->markup,
                    'change_reason' => 'import',
                    'description' => 'CSV import - new item',
                    'user_id' => $this->userId,
                ]);
            }

            $this->processStocks($item, $data, $stores);
        });
    }

    /**
     * @param  array<string, string>  $itemData
     */
    private function updateItem(Item $item, array $itemData): void
    {
        $priceChanged = $item->price != $itemData['price'];
        $costChanged = $item->cost != $itemData['cost'];
        $markupChanged = $item->markup != $itemData['markup'];

        if ($priceChanged || $costChanged || $markupChanged) {
            PriceHistory::create([
                'item_id' => $item->id,
                'old_price' => $item->price,
                'new_price' => $itemData['price'],
                'old_cost' => $item->cost,
                'new_cost' => $itemData['cost'],
                'old_markup' => $item->markup,
                'new_markup' => $itemData['markup'],
                'change_reason' => 'import',
                'description' => 'CSV import - updated item',
                'user_id' => $this->userId,
            ]);
        }

        if ($priceChanged) {
            $itemData['prev_price'] = $item->price;
        }
        if ($costChanged) {
            $itemData['prev_cost'] = $item->cost;
        }

        $item->update($itemData);
    }

    private function resolveCategoryId(?string $categoryName): ?int
    {
        if (empty($categoryName)) {
            return null;
        }

        $category = Category::query()
            ->whereRaw('LOWER(name) = ?', [strtolower(trim($categoryName))])
            ->where('status', true)
            ->first();

        if (! $category) {
            $category = Category::create([
                'name' => ucwords(strtolower(trim($categoryName))),
                'status' => true,
                'user_id' => $this->userId,
            ]);
        }

        return $category->id;
    }

    private function resolveSupplierId(?string $supplierName): ?int
    {
        if (empty($supplierName)) {
            return null;
        }

        $supplier = Supplier::query()
            ->whereRaw('LOWER(name) = ?', [strtolower(trim($supplierName))])
            ->where('status', true)
            ->first();

        if (! $supplier) {
            $supplier = Supplier::create([
                'name' => ucwords(strtolower(trim($supplierName))),
                'status' => true,
                'user_id' => $this->userId,
            ]);
        }

        return $supplier->id;
    }

    /**
     * @param  array<string, string>  $data
     * @param  array<string, int>  $stores
     */
    private function processStocks(Item $item, array $data, array $stores): void
    {
        foreach ($data as $key => $value) {
            if (str_starts_with($key, 'stock_')) {
                $storeName = substr($key, 6);
                $storeName = str_replace('_', ' ', $storeName);

                $storeId = null;
                foreach ($stores as $name => $id) {
                    if (strcasecmp($name, $storeName) === 0) {
                        $storeId = $id;
                        break;
                    }
                }

                if ($storeId) {
                    ItemStore::updateOrCreate(
                        ['item_id' => $item->id, 'store_id' => $storeId],
                        ['stock' => (float) $value, 'status' => true]
                    );
                }
            }
        }
    }

    private function parseBoolean(string $value): bool
    {
        $value = strtolower(trim($value));

        return in_array($value, ['1', 'true', 'yes', 'y'], true);
    }

    private function parseType(string $value): int
    {
        $value = strtolower(trim($value));

        return in_array($value, ['kg', 'weight', '1'], true) ? 1 : 0;
    }
}
