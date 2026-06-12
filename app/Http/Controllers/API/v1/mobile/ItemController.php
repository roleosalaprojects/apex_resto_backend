<?php

namespace App\Http\Controllers\API\v1\mobile;

use App\Http\Controllers\Controller;
use App\Http\Resources\ItemResource;
use App\Http\Traits\ApiResponse;
use App\Models\Products\Item;
use App\Models\Products\ItemStore;
use App\Models\Products\ItemUnit;
use App\Models\Products\PriceHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItemController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
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

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string',
            'barcode' => 'nullable|string|unique:items,barcode',
            'cost' => 'required|numeric',
            'markup' => 'required|numeric',
            'price' => 'required|numeric',
        ], [
            'name.required' => 'Name is required.',
            'cost.required' => 'Cost is required.',
            'cost.numeric' => 'Cost must be a number.',
            'markup.required' => 'Markup is required.',
            'markup.numeric' => 'Markup must be a number.',
            'price.required' => 'Price is required.',
            'price.numeric' => 'Price must be a number.',
        ]);

        $item = Item::create([
            'barcode' => $request->barcode ?? '',
            'name' => strtoupper($request->name),
            'vatable' => 0,
            'tax_id' => 1,
            'markup' => $request->markup,
            'cost' => $request->cost,
            'price' => $request->price,
            'senior' => 3,
            'pwd' => 3,
            'status' => true,
            'user_id' => auth()->user()->user_id,
            'discountable' => 0,
        ]);

        // Log initial price history
        if ($request->price > 0 || $request->cost > 0) {
            PriceHistory::create([
                'item_id' => $item->id,
                'old_price' => null,
                'new_price' => $request->price,
                'old_cost' => null,
                'new_cost' => $request->cost,
                'old_markup' => null,
                'new_markup' => $request->markup,
                'change_reason' => 'manual',
                'description' => 'Initial price set',
                'user_id' => auth()->user()->id,
            ]);
        }

        if ($request->item_units) {
            foreach ($request->item_units as $unitData) {
                ItemUnit::create([
                    'qty' => $unitData['qty'],
                    'price' => number_format((float) $unitData['price'], 2, '.', ''),
                    'barcode' => $unitData['barcode'],
                    'item_id' => $item->id,
                    'unit_id' => $unitData['unit_id'],
                    'status' => true,
                ]);
            }
        }

        if ($request->item_stores) {
            foreach ($request->item_stores as $storeData) {
                ItemStore::create([
                    'stock' => $storeData['stock'],
                    'status' => true,
                    'store_id' => $storeData['store_id'],
                    'item_id' => $item->id,
                ]);
            }
        }

        return $this->created(
            new ItemResource($item),
            $item->name.' created successfully.'
        );
    }

    public function show(Item $item): JsonResponse
    {
        $item->load(['category', 'supplier', 'itemUnits', 'itemStores']);

        return $this->success(new ItemResource($item));
    }

    public function update(Request $request, Item $item, $id): JsonResponse
    {
        $request->validate([
            'name' => 'required|string',
            'barcode' => 'nullable|string|unique:items,barcode,'.$id,
            'cost' => 'required|numeric',
            'markup' => 'required|numeric',
            'price' => 'required|numeric',
            'category_id' => 'nullable|exists:categories,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
        ], [
            'name.required' => 'Name is required.',
            'cost.required' => 'Cost is required.',
            'cost.numeric' => 'Cost must be a number.',
            'markup.required' => 'Markup is required.',
            'markup.numeric' => 'Markup must be a number.',
            'price.required' => 'Price is required.',
            'price.numeric' => 'Price must be a number.',
        ]);

        // Get existing item to check for price changes
        $existingItem = Item::find($id);

        // Log price history if price or cost changed
        $priceChanged = $existingItem->price != $request->price;
        $costChanged = $existingItem->cost != $request->cost;
        if ($priceChanged || $costChanged) {
            PriceHistory::create([
                'item_id' => $id,
                'old_price' => $existingItem->price,
                'new_price' => $request->price,
                'old_cost' => $existingItem->cost,
                'new_cost' => $request->cost,
                'old_markup' => $existingItem->markup,
                'new_markup' => $request->markup,
                'change_reason' => 'manual',
                'description' => $priceChanged && $costChanged
                    ? 'Price and cost updated'
                    : ($priceChanged ? 'Price updated' : 'Cost updated'),
                'user_id' => auth()->user()->id,
            ]);
        }

        Item::where('id', $id)->update(
            $request->only([
                'name',
                'barcode',
                'cost',
                'markup',
                'price',
                'category_id',
                'supplier_id',
            ])
        );

        ItemUnit::where('item_id', $id)->delete();
        if ($request->item_units) {
            foreach ($request->item_units as $unitData) {
                ItemUnit::create([
                    'qty' => $unitData['qty'],
                    'price' => number_format((float) $unitData['price'], 2, '.', ''),
                    'barcode' => $unitData['barcode'],
                    'item_id' => $id,
                    'unit_id' => $unitData['unit_id'],
                    'status' => true,
                ]);
            }
        }

        ItemStore::where('item_id', $id)->delete();
        if ($request->item_stores) {
            foreach ($request->item_stores as $storeData) {
                ItemStore::create([
                    'stock' => $storeData['stock'],
                    'status' => true,
                    'store_id' => $storeData['store_id'],
                    'item_id' => $id,
                ]);
            }
        }

        return $this->success(null, 'Item updated successfully.');
    }

    public function destroy(Item $item): JsonResponse
    {
        $item->update(['status' => false]);

        return $this->success(null, 'Item deleted successfully.');
    }

    public function updateImage(Request $request, $id): JsonResponse
    {
        $request->validate([
            'image' => 'required|mimes:jpg,png,jpeg,gif,svg|max:5120',
        ]);

        $item = Item::find($id);
        if (! $item) {
            return $this->error('Item not found', 404);
        }

        $image = $request->file('image');
        $name_gen = hexdec(uniqid());
        $img_ext = strtolower($image->getClientOriginalExtension());
        $img_name = $name_gen.'.'.$img_ext;
        $up_location = 'img/products/';
        $last_image = $up_location.$img_name;

        // Delete old image if exists
        if ($item->image && file_exists(public_path($item->image))) {
            unlink(public_path($item->image));
        }

        $image->move(public_path($up_location), $img_name);
        $item->update(['image' => $last_image]);

        return $this->success(['image' => $last_image], 'Image updated successfully.');
    }

    public function priceHistory(Request $request, $id): JsonResponse
    {
        $item = Item::find($id);

        if (! $item) {
            return $this->error('Item not found', 404);
        }

        $query = PriceHistory::where('item_id', $id)
            ->with(['user:id,name'])
            ->orderBy('created_at', 'desc');

        // Apply date filters if provided
        if ($request->start_date) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->end_date) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        // Paginate results
        $perPage = $request->per_page ?? 20;
        $history = $query->paginate($perPage);

        return $this->success([
            'item' => [
                'id' => $item->id,
                'name' => $item->name,
                'current_price' => $item->price,
                'current_cost' => $item->cost,
                'current_markup' => $item->markup,
            ],
            'history' => $history->items(),
            'pagination' => [
                'current_page' => $history->currentPage(),
                'last_page' => $history->lastPage(),
                'per_page' => $history->perPage(),
                'total' => $history->total(),
            ],
        ]);
    }
}
