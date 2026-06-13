<?php

namespace App\Http\Controllers\API\v1\pos;

use App\Http\Controllers\Controller;
use App\Http\Resources\ItemResource;
use App\Http\Traits\ApiResponse;
use App\Models\Products\Item;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItemController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $items = Item::with([
            'itemStores' => function ($q) {
                $q->where('status', true);
                $q->select('id', 'store_id', 'item_id', 'stock');
                $q->with(['store' => function ($q) {
                    $q->select('id', 'name');
                }]);
            },
            'wholesalePriceTiers',
            'components.componentItem:id,name,cost,uom_label',
        ])
            ->select('id', 'name', 'price', 'markup', 'cost', 'category_id', 'supplier_id', 'image', 'is_composite', 'uom_label')
            ->where(function ($q) use ($request) {
                $q->where('name', 'like', "%$request->term%")
                    ->orWhere('barcode', 'like', "%$request->term%");
            })
            ->where('status', true)
            ->orderBy('name', 'asc')
            ->take(100)
            ->get();

        return $this->success(ItemResource::collection($items));
    }

    public function store(Request $request): JsonResponse
    {
        return $this->success(null);
    }

    public function show(Item $item): JsonResponse
    {
        $item->load([
            'category',
            'tax',
            'supplier',
            'itemUnits' => function ($q) {
                $q->with('unit');
            },
            'itemStores' => function ($q) {
                $q->with('store');
            },
            'wholesalePriceTiers',
            'components.componentItem:id,name,cost,uom_label',
        ]);

        return $this->success(new ItemResource($item));
    }

    public function update(Request $request, Item $item): JsonResponse
    {
        return $this->success(null);
    }

    public function destroy(Item $item): JsonResponse
    {
        return $this->success(null);
    }

    public function getItems(Request $request): JsonResponse
    {
        $items = Item::whereIn('id', array_keys($request->all()))
            ->where('status', true)
            ->get();

        return $this->success(ItemResource::collection($items));
    }

    public function searchItemsFromKey(Request $request): JsonResponse
    {
        $products = Item::where('items.status', true)
            ->with([
                'category' => function ($query) {
                    $query->select('id', 'name');
                },
                'supplier' => function ($query) {
                    $query->select('id', 'name');
                },
                'itemUnits' => function ($query) {
                    $query->with([
                        'unit' => function ($query) {
                            $query->select('id', 'name');
                        },
                    ]);
                },
                'itemStores' => function ($query) {
                    $query->with([
                        'store' => function ($query) {
                            $query->select('id', 'name');
                        },
                    ]);
                },
                'wholesalePriceTiers',
            ])
            ->where(function ($query) use ($request) {
                $query->where('name', 'LIKE', '%'.$request->term.'%')
                    ->orWhere('items.barcode', 'LIKE', '%'.$request->term.'%')
                    ->orWhereRelation('itemUnits', 'barcode', 'like', '%'.$request->term.'%');
            })
            ->orderBy('name')
            ->take(100)
            ->get();

        return $this->success([
            'products' => ItemResource::collection($products),
        ]);
    }

    public function searchItem(Request $request): JsonResponse
    {
        $products = Item::where('items.status', true)
            ->with([
                'category' => function ($query) {
                    $query->select('id', 'name');
                },
                'supplier' => function ($query) {
                    $query->select('id', 'name');
                },
                'itemUnits' => function ($query) {
                    $query->with([
                        'unit' => function ($query) {
                            $query->select('id', 'name');
                        },
                    ]);
                },
                'itemStores' => function ($query) {
                    $query->with([
                        'store' => function ($query) {
                            $query->select('id', 'name');
                        },
                    ]);
                },
                'wholesalePriceTiers',
            ])
            ->where(function ($query) use ($request) {
                $query->where('name', 'LIKE', '%'.$request->term.'%')
                    ->orWhere('items.barcode', 'LIKE', '%'.$request->term.'%')
                    ->orWhereRelation('itemUnits', 'barcode', 'like', '%'.$request->term.'%');
            })
            ->orderBy('name')
            ->take(100)
            ->get();

        return $this->success([
            'products' => ItemResource::collection($products),
        ]);
    }
}
