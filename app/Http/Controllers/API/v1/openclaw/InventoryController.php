<?php

namespace App\Http\Controllers\API\v1\openclaw;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\InventoryManagement\Supplier;
use App\Models\Products\Item;
use App\Models\Products\ItemStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    use ApiResponse;

    private const LOW_STOCK_THRESHOLD = 10;

    /**
     * Stock levels per item per store.
     */
    public function stock(Request $request): JsonResponse
    {
        $request->validate([
            'store_id' => 'nullable|integer|min:1',
            'limit' => 'nullable|integer|min:1|max:1000',
            'cursor' => 'nullable|integer|min:0',
        ]);

        $tenantUserId = (int) auth()->user()->user_id;
        $limit = (int) $request->input('limit', 200);
        $cursor = (int) $request->input('cursor', 0);

        $query = ItemStore::query()
            ->join('items', 'item_stores.item_id', '=', 'items.id')
            ->where('items.status', true)
            ->where('items.user_id', $tenantUserId)
            ->when($request->filled('store_id'), fn ($q) => $q->where('item_stores.store_id', (int) $request->input('store_id')))
            ->where('item_stores.id', '>', $cursor)
            ->select([
                'item_stores.id as row_id',
                'item_stores.item_id',
                'items.name',
                'items.barcode',
                'item_stores.store_id',
                'item_stores.stock',
                'items.cost',
                'items.price',
            ])
            ->orderBy('item_stores.id')
            ->limit($limit + 1)
            ->get();

        $hasMore = $query->count() > $limit;
        $rows = $query->take($limit);
        $nextCursor = $hasMore ? (int) $rows->last()->row_id : null;

        return $this->success([
            'items' => $rows->map(fn ($r) => [
                'item_id' => (int) $r->item_id,
                'name' => $r->name,
                'barcode' => $r->barcode,
                'store_id' => (int) $r->store_id,
                'stock' => round((float) $r->stock, 2),
                'cost' => round((float) $r->cost, 2),
                'price' => round((float) $r->price, 2),
            ])->values(),
            'next_cursor' => $nextCursor,
        ]);
    }

    /**
     * Items at or below their effective low-stock threshold, with summary counts.
     * The threshold is per-item via items.low_stock_threshold; when NULL it falls
     * back to the system default LOW_STOCK_THRESHOLD.
     */
    public function lowStock(Request $request): JsonResponse
    {
        $request->validate([
            'store_id' => 'nullable|integer|min:1',
            'limit' => 'nullable|integer|min:1|max:500',
        ]);

        $tenantUserId = (int) auth()->user()->user_id;
        $limit = (int) $request->input('limit', 100);

        $effectiveSql = 'COALESCE(items.low_stock_threshold, '.self::LOW_STOCK_THRESHOLD.')';

        $base = ItemStore::query()
            ->join('items', 'item_stores.item_id', '=', 'items.id')
            ->where('items.status', true)
            ->where('items.user_id', $tenantUserId)
            ->when($request->filled('store_id'), fn ($q) => $q->where('item_stores.store_id', (int) $request->input('store_id')));

        $outOfStock = (clone $base)->where('item_stores.stock', '<=', 0)->count();
        $low = (clone $base)
            ->where('item_stores.stock', '>', 0)
            ->whereRaw("item_stores.stock <= {$effectiveSql}")
            ->count();

        $items = (clone $base)
            ->whereRaw("item_stores.stock <= {$effectiveSql}")
            ->select([
                'items.id',
                'items.name',
                'items.barcode',
                'item_stores.store_id',
                'item_stores.stock',
                'items.cost',
                'items.low_stock_threshold',
                DB::raw("{$effectiveSql} as effective_threshold"),
            ])
            ->orderBy('item_stores.stock')
            ->limit($limit)
            ->get()
            ->map(fn ($i) => [
                'item_id' => (int) $i->id,
                'name' => $i->name,
                'barcode' => $i->barcode,
                'store_id' => (int) $i->store_id,
                'stock' => round((float) $i->stock, 2),
                'cost' => round((float) $i->cost, 2),
                'low_stock_threshold' => $i->low_stock_threshold !== null ? (int) $i->low_stock_threshold : null,
                'effective_threshold' => (int) $i->effective_threshold,
            ]);

        return $this->success([
            'default_threshold' => self::LOW_STOCK_THRESHOLD,
            'summary' => [
                'out_of_stock_count' => $outOfStock,
                'low_stock_count' => $low,
            ],
            'items' => $items,
        ]);
    }

    /**
     * PATCH /v1/openclaw/items/{item}/alert — set or clear low_stock_threshold.
     */
    public function setItemAlert(Request $request, Item $item): JsonResponse
    {
        $tenantUserId = (int) auth()->user()->user_id;
        if ((int) $item->user_id !== $tenantUserId) {
            abort(404);
        }

        $validated = $request->validate([
            'low_stock_threshold' => 'present|nullable|integer|min:0|max:1000000',
        ]);

        $item->forceFill(['low_stock_threshold' => $validated['low_stock_threshold']])->save();

        return $this->success([
            'item' => [
                'id' => $item->id,
                'name' => $item->name,
                'barcode' => $item->barcode,
                'low_stock_threshold' => $item->low_stock_threshold !== null ? (int) $item->low_stock_threshold : null,
                'effective_threshold' => $item->low_stock_threshold !== null
                    ? (int) $item->low_stock_threshold
                    : self::LOW_STOCK_THRESHOLD,
            ],
        ], $validated['low_stock_threshold'] === null
            ? "Low-stock alert cleared for item #{$item->id}."
            : "Low-stock alert set to {$validated['low_stock_threshold']} for item #{$item->id}.");
    }

    /**
     * Active suppliers with active item counts.
     */
    public function suppliers(Request $request): JsonResponse
    {
        $tenantUserId = (int) auth()->user()->user_id;

        $suppliers = Supplier::query()
            ->where('user_id', $tenantUserId)
            ->where('status', true)
            ->orderBy('name')
            ->get()
            ->map(fn (Supplier $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'contact' => $s->contact,
                'phone' => $s->number,
                'email' => $s->email,
                'payment_terms_days' => $s->payment_terms_days !== null ? (int) $s->payment_terms_days : null,
                'item_count' => Item::query()
                    ->where('user_id', $tenantUserId)
                    ->where('supplier_id', $s->id)
                    ->where('status', true)
                    ->count(),
            ]);

        return $this->success([
            'suppliers' => $suppliers,
        ]);
    }
}
