<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\InventoryManagement\Supplier;
use App\Models\Products\Category;
use App\Models\Products\Item;
use App\Models\Products\ItemStore;
use App\Models\Products\Unit;
use App\Models\Settings\Tax;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ItemController extends Controller
{
    use ApiResponse;

    public function show(Request $request)
    {
        $start = Carbon::parse($request->start)->startOfDay()->toDateTimeString();
        $end = Carbon::parse($request->end)->endOfDay()->toDateTimeString();
        $q = DB::select(
            "
            SELECT
                qty,
                unit,
                format(qty * unit_qty, 2) as ttl_qty,
                item_id,
                format(price * qty, 2) as price,
                s.created_at as date,
                s.son as si
            FROM sale_lines sl
            LEFT JOIN
                sales s
            ON
                s.id = sl.sales_id
            WHERE
                item_id = $request->item_id
                AND
                s.created_at BETWEEN '$start' AND '$end';
            "
        );

        return datatables($q)->make(true);
    }

    public function getChart(): JsonResponse
    {
        return $this->success(null);
    }

    public function searchItem($key): JsonResponse
    {
        $key = ($key) ?? '1';
        $items = Item::where('name', 'like', "%$key%")->where('status', true)->take(100)->get();

        return $this->success($items);
    }

    public function showDesc($id): JsonResponse
    {
        $items = Item::find($id);
        $categories = Category::where('status', true)->get();
        $suppliers = Supplier::where('status', true)->get();
        $taxes = Tax::where('status', true)->get();
        $units = Unit::where('status', true)->get();
        $item_stores = ItemStore::where('item_id', $items->id);

        return $this->success([
            'item' => $items,
            'categories' => $categories,
            'suppliers' => $suppliers,
            'taxes' => $taxes,
            'units' => $units,
            'item_stores' => $item_stores,
        ]);
    }
}
