<?php

namespace App\Http\Controllers\API\v1\mobile;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\InventoryManagement\Adjustment;
use App\Models\InventoryManagement\AdjustmentLine;
use App\Models\InventoryManagement\Count;
use App\Models\InventoryManagement\CountLine;
use App\Models\InventoryManagement\Transfer;
use App\Models\InventoryManagement\TransferLine;
use App\Models\Products\Item;
use App\Models\Products\ItemStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{
    use ApiResponse;

    private const LOW_STOCK_THRESHOLD = 10;

    private const ADJUSTMENT_TYPES = [
        'damage' => 'Damage',
        'theft' => 'Theft',
        'correction' => 'Count Correction',
        'expired' => 'Expired',
        'return_to_supplier' => 'Return to Supplier',
        'other' => 'Other',
    ];

    private const TRANSFER_STATUSES = [
        0 => 'pending',
        1 => 'approved',
        2 => 'in_transit',
        3 => 'completed',
        4 => 'rejected',
    ];

    // ==================== STOCK ADJUSTMENTS ====================

    /**
     * Get stock adjustments list.
     */
    public function adjustments(Request $request): JsonResponse
    {
        $limit = $request->input('limit', 50);
        $offset = $request->input('offset', 0);

        $query = Adjustment::query()
            ->with(['store:id,name', 'creator:id,name'])
            ->where('user_id', auth()->user()->user_id);

        if ($request->filled('store_id')) {
            $query->where('store_id', $request->input('store_id'));
        }

        if ($request->filled('type')) {
            $query->where('reason', $request->input('type'));
        }

        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->input('start_date'));
        }

        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->input('end_date'));
        }

        // Filter by status (2=Pending, 1=Approved, 0=Deleted)
        // Support comma-separated values like "1,2" for multiple statuses
        if ($request->filled('status')) {
            $statuses = explode(',', $request->input('status'));
            $query->whereIn('status', $statuses);
        }

        $total = $query->count();

        $adjustments = $query
            ->with(['lines.item:id,name,barcode'])
            ->orderByDesc('created_at')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->map(function ($adjustment) {
                return [
                    'id' => $adjustment->id,
                    'adjustment_number' => 'ADJ-'.str_pad($adjustment->so, 6, '0', STR_PAD_LEFT),
                    'store' => $adjustment->store ? [
                        'id' => $adjustment->store->id,
                        'name' => $adjustment->store->name,
                    ] : null,
                    'type' => $adjustment->reason,
                    'total_items' => $adjustment->lines->count(),
                    'status' => $adjustment->status,
                    'note' => $adjustment->note,
                    'created_at' => $adjustment->created_at->toIso8601String(),
                    'created_by' => $adjustment->creator ? [
                        'id' => $adjustment->creator->id,
                        'name' => $adjustment->creator->name,
                    ] : null,
                ];
            });

        return $this->success([
            'adjustments' => $adjustments,
            'total' => $total,
        ]);
    }

    /**
     * Create stock adjustment.
     */
    public function createAdjustment(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'store_id' => 'required|exists:stores,id',
            'type' => ['required', Rule::in(array_keys(self::ADJUSTMENT_TYPES))],
            'status' => 'required|bool',
            'note' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:items,id',
            'items.*.quantity' => 'required|numeric',
            'items.*.unit_id' => 'nullable|exists:units,id',
        ]);

        $user = auth()->user();

        $lastSo = Adjustment::where('user_id', $user->user_id)->max('so') ?? 0;

        $adjustment = Adjustment::create([
            'so' => $lastSo + 1,
            'store_id' => $validated['store_id'],
            'reason' => $validated['type'],
            'note' => $validated['note'] ?? null,
            'total' => count($validated['items']),
            'received' => 0,
            'status' => $validated['status'],
            'created_by' => $user->id,
            'user_id' => $user->user_id,
        ]);

        foreach ($validated['items'] as $itemData) {
            $item = Item::find($itemData['product_id']);
            AdjustmentLine::create([
                'adjustment_id' => $adjustment->id,
                'item_id' => $itemData['product_id'],
                'qty' => $itemData['quantity'],
                'received' => 0,
                'unit_id' => $itemData['unit_id'] ?? $item->unit_id ?? null,
                'unit_qty' => 1,
            ]);
        }

        return $this->success([
            'id' => $adjustment->id,
            'adjustment_number' => 'ADJ-'.str_pad($adjustment->so, 6, '0', STR_PAD_LEFT),
        ], 'Stock adjustment created successfully');
    }

    /**
     * Get adjustment types/reasons.
     */
    public function adjustmentReasons(): JsonResponse
    {
        $reasons = collect(self::ADJUSTMENT_TYPES)->map(function ($label, $value) {
            return ['value' => $value, 'label' => $label];
        })->values();

        return $this->success($reasons);
    }

    /**
     * Get adjustment details.
     */
    public function showAdjustment(int $id): JsonResponse
    {
        $adjustment = Adjustment::with(['store:id,name', 'creator:id,name', 'lines.item:id,name,barcode', 'lines.unitRelation:id,name'])
            ->where('user_id', auth()->user()->user_id)
            ->findOrFail($id);

        return $this->success([
            'id' => $adjustment->id,
            'adjustment_number' => 'ADJ-'.str_pad($adjustment->so, 6, '0', STR_PAD_LEFT),
            'store' => $adjustment->store ? [
                'id' => $adjustment->store->id,
                'name' => $adjustment->store->name,
            ] : null,
            'type' => $adjustment->reason,
            'status' => $adjustment->status,
            'note' => $adjustment->note,
            'created_at' => $adjustment->created_at->toIso8601String(),
            'created_by' => $adjustment->creator ? [
                'id' => $adjustment->creator->id,
                'name' => $adjustment->creator->name,
            ] : null,
            'items' => $adjustment->lines->map(function ($line) {
                // Determine unit: use relation if exists, otherwise default to PCS/KGS based on product type
                $unitData = null;
                if ($line->unitRelation) {
                    $unitData = [
                        'id' => $line->unitRelation->id,
                        'name' => $line->unitRelation->name,
                    ];
                } elseif ($line->unit_id === null) {
                    // No unit_id means default PCS/KGS based on product type
                    $defaultUnit = ($line->item && $line->item->type == 1) ? 'KGS' : 'PCS';
                    $unitData = ['id' => null, 'name' => $defaultUnit];
                }

                return [
                    'id' => $line->id,
                    'product' => $line->item ? [
                        'id' => $line->item->id,
                        'name' => $line->item->name,
                        'barcode' => $line->item->barcode,
                    ] : null,
                    'quantity' => (float) $line->qty,
                    'unit' => $unitData,
                ];
            }),
        ]);
    }

    // ==================== STOCK TRANSFERS ====================

    /**
     * Get stock transfers list.
     */
    public function transfers(Request $request): JsonResponse
    {
        $limit = $request->input('limit', 50);
        $offset = $request->input('offset', 0);

        $query = Transfer::query()
            ->with(['source:id,name', 'destination:id,name', 'creator:id,name', 'lines'])
            ->where('user_id', auth()->user()->user_id);

        if ($request->filled('from_store_id')) {
            $query->where('source_store', $request->input('from_store_id'));
        }

        if ($request->filled('to_store_id')) {
            $query->where('destination_store', $request->input('to_store_id'));
        }

        if ($request->filled('status')) {
            $statusValue = array_search($request->input('status'), self::TRANSFER_STATUSES);
            if ($statusValue !== false) {
                $query->where('status', $statusValue);
            }
        }

        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->input('start_date'));
        }

        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->input('end_date'));
        }

        $total = $query->count();

        $transfers = $query
            ->orderByDesc('created_at')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->map(function ($transfer) {
                return [
                    'id' => $transfer->id,
                    'transfer_number' => 'TRF-'.str_pad($transfer->to, 6, '0', STR_PAD_LEFT),
                    'from_store' => $transfer->source ? [
                        'id' => $transfer->source->id,
                        'name' => $transfer->source->name,
                    ] : null,
                    'to_store' => $transfer->destination ? [
                        'id' => $transfer->destination->id,
                        'name' => $transfer->destination->name,
                    ] : null,
                    'status' => self::TRANSFER_STATUSES[$transfer->status] ?? 'unknown',
                    'item_count' => $transfer->lines->count(),
                    'note' => $transfer->note,
                    'created_at' => $transfer->created_at->toIso8601String(),
                    'created_by' => $transfer->creator ? [
                        'id' => $transfer->creator->id,
                        'name' => $transfer->creator->name,
                    ] : null,
                    'received_at' => $transfer->received_at,
                ];
            });

        return $this->success([
            'transfers' => $transfers,
            'total' => $total,
        ]);
    }

    /**
     * Create stock transfer.
     */
    public function createTransfer(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from_store_id' => 'required|exists:stores,id',
            'to_store_id' => 'required|exists:stores,id|different:from_store_id',
            'note' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:items,id',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_id' => 'nullable|exists:units,id',
        ]);

        $user = auth()->user();

        $lastTo = Transfer::where('user_id', $user->user_id)->max('to') ?? 0;

        $transfer = Transfer::create([
            'to' => $lastTo + 1,
            'source_store' => $validated['from_store_id'],
            'destination_store' => $validated['to_store_id'],
            'note' => $validated['note'] ?? null,
            'total' => count($validated['items']),
            'received' => 0,
            'status' => 0,
            'created_by' => $user->id,
            'user_id' => $user->user_id,
        ]);

        foreach ($validated['items'] as $itemData) {
            $item = Item::find($itemData['product_id']);
            TransferLine::create([
                'transfer_id' => $transfer->id,
                'item_id' => $itemData['product_id'],
                'qty' => $itemData['quantity'],
                'received' => 0,
                'unit_id' => $itemData['unit_id'] ?? $item->unit_id ?? null,
                'unit_qty' => 1,
            ]);
        }

        return $this->success([
            'id' => $transfer->id,
            'transfer_number' => 'TRF-'.str_pad($transfer->to, 6, '0', STR_PAD_LEFT),
        ], 'Stock transfer created successfully');
    }

    /**
     * Get transfer details.
     */
    public function showTransfer(int $id): JsonResponse
    {
        $transfer = Transfer::with(['source:id,name', 'destination:id,name', 'creator:id,name', 'receiver:id,name', 'lines.item:id,name,barcode', 'lines.unitRelation:id,name'])
            ->where('user_id', auth()->user()->user_id)
            ->findOrFail($id);

        return $this->success([
            'id' => $transfer->id,
            'transfer_number' => 'TRF-'.str_pad($transfer->to, 6, '0', STR_PAD_LEFT),
            'from_store' => $transfer->source ? [
                'id' => $transfer->source->id,
                'name' => $transfer->source->name,
            ] : null,
            'to_store' => $transfer->destination ? [
                'id' => $transfer->destination->id,
                'name' => $transfer->destination->name,
            ] : null,
            'status' => self::TRANSFER_STATUSES[$transfer->status] ?? 'unknown',
            'items' => $transfer->lines->map(function ($line) {
                // Determine unit: use relation if exists, otherwise default to PCS/KGS based on product type
                $unitData = null;
                if ($line->unitRelation) {
                    $unitData = [
                        'id' => $line->unitRelation->id,
                        'name' => $line->unitRelation->name,
                    ];
                } elseif ($line->unit_id === null) {
                    // No unit_id means default PCS/KGS based on product type
                    $defaultUnit = ($line->item && $line->item->type == 1) ? 'KGS' : 'PCS';
                    $unitData = ['id' => null, 'name' => $defaultUnit];
                }

                return [
                    'id' => $line->id,
                    'product' => $line->item ? [
                        'id' => $line->item->id,
                        'name' => $line->item->name,
                        'barcode' => $line->item->barcode,
                    ] : null,
                    'quantity' => (float) $line->qty,
                    'received' => (float) $line->received,
                    'unit' => $unitData,
                ];
            }),
            'note' => $transfer->note,
            'created_at' => $transfer->created_at->toIso8601String(),
            'created_by' => $transfer->creator ? [
                'id' => $transfer->creator->id,
                'name' => $transfer->creator->name,
            ] : null,
            'received_at' => $transfer->received_at,
            'received_by' => $transfer->receiver ? [
                'id' => $transfer->receiver->id,
                'name' => $transfer->receiver->name,
            ] : null,
        ]);
    }

    /**
     * Update transfer status.
     */
    public function updateTransfer(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['approved', 'in_transit', 'completed', 'rejected'])],
            'note' => 'nullable|string|max:1000',
        ]);

        $transfer = Transfer::where('user_id', auth()->user()->user_id)
            ->findOrFail($id);

        $currentStatus = self::TRANSFER_STATUSES[$transfer->status] ?? 'pending';
        $newStatus = $validated['status'];

        $validTransitions = [
            'pending' => ['approved', 'rejected'],
            'approved' => ['in_transit', 'rejected'],
            'in_transit' => ['completed'],
        ];

        if (! isset($validTransitions[$currentStatus]) || ! in_array($newStatus, $validTransitions[$currentStatus])) {
            return $this->error("Cannot transition from '{$currentStatus}' to '{$newStatus}'", 422);
        }

        $statusValue = array_search($newStatus, self::TRANSFER_STATUSES);
        $transfer->status = $statusValue;
        $transfer->updated_by = auth()->user()->id;

        if ($newStatus === 'completed') {
            $transfer->received_at = now();
            $transfer->received_by = auth()->user()->id;
        }

        if (! empty($validated['note'])) {
            $transfer->note = $validated['note'];
        }

        $transfer->save();

        return $this->success(null, 'Transfer status updated successfully');
    }

    // ==================== INVENTORY COUNTS ====================

    /**
     * Get inventory counts list.
     */
    public function counts(Request $request): JsonResponse
    {
        $limit = $request->input('limit', 50);
        $offset = $request->input('offset', 0);

        $query = Count::query()
            ->with(['store:id,name', 'creator:id,name', 'lines'])
            ->where('user_id', auth()->user()->user_id);

        if ($request->filled('store_id')) {
            $query->where('store_id', $request->input('store_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->input('start_date'));
        }

        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->input('end_date'));
        }

        $total = $query->count();

        $counts = $query
            ->orderByDesc('created_at')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->map(function ($count) {
                $statusMap = [0 => 'draft', 1 => 'in_progress', 2 => 'completed', 3 => 'cancelled'];

                return [
                    'id' => $count->id,
                    'count_number' => 'CNT-'.str_pad($count->ic, 6, '0', STR_PAD_LEFT),
                    'store' => $count->store ? [
                        'id' => $count->store->id,
                        'name' => $count->store->name,
                    ] : null,
                    'status' => $statusMap[$count->status] ?? 'unknown',
                    'item_count' => $count->total,
                    'counted_items' => $count->lines->count(),
                    'created_at' => $count->created_at->toIso8601String(),
                    'created_by' => $count->creator ? [
                        'id' => $count->creator->id,
                        'name' => $count->creator->name,
                    ] : null,
                ];
            });

        return $this->success([
            'counts' => $counts,
            'total' => $total,
        ]);
    }

    /**
     * Create inventory count.
     */
    public function createCount(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'store_id' => 'required|exists:stores,id',
            'category_id' => 'nullable|exists:categories,id',
            'note' => 'nullable|string|max:1000',
        ]);

        $user = auth()->user();

        $itemQuery = Item::where('user_id', $user->user_id)
            ->where('status', true);

        if ($validated['category_id'] ?? null) {
            $itemQuery->where('category_id', $validated['category_id']);
        }

        $itemCount = $itemQuery->count();

        $lastIc = Count::where('user_id', $user->user_id)->max('ic') ?? 0;

        $count = Count::create([
            'ic' => $lastIc + 1,
            'store_id' => $validated['store_id'],
            'total' => $itemCount,
            'status' => 0,
            'created_by' => $user->id,
            'user_id' => $user->user_id,
        ]);

        return $this->success([
            'id' => $count->id,
            'count_number' => 'CNT-'.str_pad($count->ic, 6, '0', STR_PAD_LEFT),
        ], 'Inventory count created successfully');
    }

    /**
     * Get inventory count details.
     */
    public function showCount(int $id): JsonResponse
    {
        $count = Count::with(['store:id,name', 'creator:id,name', 'lines.item:id,name,barcode,type', 'lines.unit:id,name'])
            ->where('user_id', auth()->user()->user_id)
            ->findOrFail($id);

        $statusMap = [0 => 'draft', 1 => 'in_progress', 2 => 'completed', 3 => 'cancelled'];

        $totalVariance = 0;

        $items = $count->lines->map(function ($line) use ($count, &$totalVariance) {
            $itemStock = ItemStore::where('item_id', $line->item_id)
                ->where('store_id', $count->store_id)
                ->first();

            $systemQty = (float) ($itemStock?->stock ?? 0);
            $countedQty = $line->counted_qty !== null ? (float) $line->counted_qty : null;
            $variance = $countedQty !== null ? $countedQty - $systemQty : null;

            if ($variance !== null && $variance !== 0.0) {
                $totalVariance++;
            }

            // Determine default unit name based on item type
            $unitName = 'pcs';
            if ($line->unit) {
                $unitName = $line->unit->name;
            } elseif ($line->item && $line->item->type == 1) {
                $unitName = 'kgs';
            }

            return [
                'id' => $line->id,
                'product' => $line->item ? [
                    'id' => $line->item->id,
                    'name' => $line->item->name,
                    'barcode' => $line->item->barcode,
                    'type' => $line->item->type,
                ] : null,
                'system_quantity' => $systemQty,
                'counted_quantity' => $countedQty,
                'variance' => $variance,
                'unit' => [
                    'id' => $line->unit?->id,
                    'name' => $unitName,
                ],
            ];
        });

        return $this->success([
            'id' => $count->id,
            'count_number' => 'CNT-'.str_pad($count->ic, 6, '0', STR_PAD_LEFT),
            'store' => $count->store ? [
                'id' => $count->store->id,
                'name' => $count->store->name,
            ] : null,
            'status' => $statusMap[$count->status] ?? 'unknown',
            'total_items' => $count->lines->count(),
            'counted_items' => $count->lines->whereNotNull('counted_qty')->count(),
            'variance_count' => $totalVariance,
            'items' => $items,
            'created_at' => $count->created_at->toIso8601String(),
            'created_by' => $count->creator ? [
                'id' => $count->creator->id,
                'name' => $count->creator->name,
            ] : null,
        ]);
    }

    /**
     * Add or update item in inventory count.
     */
    public function updateCountItem(Request $request, int $countId): JsonResponse
    {
        $validated = $request->validate([
            'item_id' => 'required|exists:items,id',
            'unit_id' => 'nullable|exists:units,id',
            'counted_qty' => 'nullable|numeric|min:0',
        ]);

        $count = Count::where('user_id', auth()->user()->user_id)
            ->findOrFail($countId);

        if ($count->status >= 2) {
            return $this->error('Cannot modify a completed or cancelled count', 422);
        }

        $existingLine = CountLine::where('count_id', $count->id)
            ->where('item_id', $validated['item_id'])
            ->first();

        if ($existingLine) {
            // Update existing line with counted_qty
            if (isset($validated['counted_qty'])) {
                $existingLine->counted_qty = $validated['counted_qty'];
                $existingLine->save();

                return $this->success(null, 'Item count updated successfully');
            }

            return $this->error('Item already exists in this count', 422);
        }

        CountLine::create([
            'count_id' => $count->id,
            'item_id' => $validated['item_id'],
            'unit_id' => $validated['unit_id'] ?? null,
            'counted_qty' => $validated['counted_qty'] ?? null,
        ]);

        if ($count->status === 0) {
            $count->status = 1;
            $count->save();
        }

        return $this->success(null, 'Item added to count successfully');
    }

    /**
     * Remove item from inventory count.
     */
    public function removeCountItem(Request $request, int $countId, int $lineId): JsonResponse
    {
        $count = Count::where('user_id', auth()->user()->user_id)
            ->findOrFail($countId);

        if ($count->status >= 2) {
            return $this->error('Cannot modify a completed or cancelled count', 422);
        }

        $line = CountLine::where('count_id', $count->id)
            ->where('id', $lineId)
            ->first();

        if (! $line) {
            return $this->error('Item not found in this count', 404);
        }

        $line->delete();

        return $this->success(null, 'Item removed from count successfully');
    }

    /**
     * Finalize inventory count and update stock.
     *
     * When finalized, the counted_qty for each item overrides the system stock
     * in the item_stores table for the count's store.
     */
    public function finalizeCount(Request $request, int $id): JsonResponse
    {
        $count = Count::with('lines')
            ->where('user_id', auth()->user()->user_id)
            ->findOrFail($id);

        if ($count->status >= 2) {
            return $this->error('Count is already finalized', 422);
        }

        // Verify all items have been counted
        $uncountedItems = $count->lines->whereNull('counted_qty')->count();
        if ($uncountedItems > 0) {
            return $this->error("Cannot finalize: {$uncountedItems} item(s) have not been counted yet", 422);
        }

        // Update stock for each line item
        $updatedCount = 0;
        $varianceCount = 0;

        foreach ($count->lines as $line) {
            $itemStore = ItemStore::where('item_id', $line->item_id)
                ->where('store_id', $count->store_id)
                ->first();

            if ($itemStore) {
                $oldStock = (float) $itemStore->stock;
                $newStock = (float) $line->counted_qty;

                if ($oldStock !== $newStock) {
                    $varianceCount++;
                }

                $itemStore->stock = $newStock;
                $itemStore->save();
                $updatedCount++;
            } else {
                // Create item_store record if it doesn't exist
                ItemStore::create([
                    'item_id' => $line->item_id,
                    'store_id' => $count->store_id,
                    'stock' => $line->counted_qty,
                ]);
                $updatedCount++;
                $varianceCount++;
            }
        }

        $count->status = 2; // Completed
        $count->save();

        return $this->success([
            'items_counted' => $count->lines->count(),
            'items_updated' => $updatedCount,
            'variances' => $varianceCount,
        ], 'Inventory count finalized and stock updated successfully');
    }

    // ==================== LOW STOCK ALERTS ====================

    /**
     * Get low stock products.
     */
    public function lowStock(Request $request): JsonResponse
    {
        $limit = $request->input('limit', 50);

        $query = ItemStore::query()
            ->join('items', 'item_stores.item_id', '=', 'items.id')
            ->leftJoin('categories', 'items.category_id', '=', 'categories.id')
            ->where('items.status', true)
            ->where('items.user_id', auth()->user()->user_id);

        if ($request->filled('store_id')) {
            $query->where('item_stores.store_id', $request->input('store_id'));
        }

        if ($request->filled('category_id')) {
            $query->where('items.category_id', $request->input('category_id'));
        }

        $outOfStockCount = (clone $query)->where('item_stores.stock', '=', 0)->count();
        $criticalCount = (clone $query)->where('item_stores.stock', '>', 0)
            ->where('item_stores.stock', '<=', self::LOW_STOCK_THRESHOLD * 0.5)->count();
        $lowStockCount = (clone $query)->where('item_stores.stock', '>', 0)
            ->where('item_stores.stock', '<=', self::LOW_STOCK_THRESHOLD)->count();

        $items = $query
            ->where('item_stores.stock', '<=', self::LOW_STOCK_THRESHOLD)
            ->select([
                'items.id',
                'items.name',
                'items.barcode',
                'items.cost as unit_cost',
                'item_stores.stock as current_stock',
                'item_stores.store_id',
                'categories.name as category',
            ])
            ->orderBy('item_stores.stock', 'asc')
            ->limit($limit)
            ->get()
            ->map(function ($item) {
                $reorderPoint = self::LOW_STOCK_THRESHOLD;
                $suggestedOrder = ($reorderPoint * 2) - $item->current_stock;

                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'barcode' => $item->barcode,
                    'current_stock' => (float) $item->current_stock,
                    'reorder_point' => $reorderPoint,
                    'suggested_order_quantity' => max($suggestedOrder, 0),
                    'store_id' => $item->store_id,
                    'category' => $item->category,
                    'unit_cost' => round((float) $item->unit_cost, 2),
                ];
            });

        return $this->success([
            'items' => $items,
            'summary' => [
                'out_of_stock_count' => $outOfStockCount,
                'critical_count' => $criticalCount,
                'low_stock_count' => $lowStockCount,
            ],
        ]);
    }

    /**
     * Get count sheet for physical counting.
     */
    public function countSheet(Request $request): JsonResponse
    {
        $request->validate([
            'store_id' => 'required|exists:stores,id',
            'category_id' => 'nullable|exists:categories,id',
        ]);

        $query = ItemStore::query()
            ->join('items', 'item_stores.item_id', '=', 'items.id')
            ->leftJoin('categories', 'items.category_id', '=', 'categories.id')
            ->where('item_stores.store_id', $request->input('store_id'))
            ->where('items.status', true)
            ->where('items.user_id', auth()->user()->user_id);

        if ($request->filled('category_id')) {
            $query->where('items.category_id', $request->input('category_id'));
        }

        $items = $query
            ->select([
                'items.id as product_id',
                'items.name',
                'items.barcode',
                'categories.name as category',
                'item_stores.stock as system_quantity',
            ])
            ->orderBy('items.name')
            ->get()
            ->map(function ($item) {
                return [
                    'product_id' => $item->product_id,
                    'name' => $item->name,
                    'barcode' => $item->barcode,
                    'category' => $item->category,
                    'system_quantity' => (float) $item->system_quantity,
                ];
            });

        return $this->success(['items' => $items]);
    }
}
