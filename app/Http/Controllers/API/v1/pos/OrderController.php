<?php

namespace App\Http\Controllers\API\v1\pos;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Pos\Order;
use App\Models\Pos\OrderLine;
use App\Models\Products\Item;
use App\Models\Products\ItemStore;
use App\Models\Settings\Store;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Mavinoo\Batch\BatchFacade as Batch;

class OrderController extends Controller
{
    use ApiResponse;

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $startDate = Carbon::parse($request->startDate)->startOfDay()->toDateTimeString();
        $endDate = Carbon::parse($request->endDate)->endOfDay()->toDateTimeString();
        $orders = Order::where('user_id', auth()->user()->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with(['pos' => function ($pos) {
                $pos->select('id', 'name');
            }])
            ->orderBy('id', 'DESC')
            ->get();

        return $this->success($orders);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return JsonResponse
     */
    public function store(Request $request)
    {
        $requestValues = array_values($request->all());
        $items = Item::whereIn('id', array_keys($request->all()))
            ->with([
                'tax',
            ])
            ->where('status', true)
//            ->select('id', 'cost', 'price', 'markup')
            ->get();
        //        return $items;
        $order = Order::create([
            'reference' => uniqid(),
            'qty' => 0, // Tentative set it to 0. Update amount and qty later after inserting OrderLine
            'amount' => 0, // Tentative set it to 0. Update amount and qty later after inserting OrderLine
            'pos_id' => null, // Leave POS as blank since this is an order from a mobile device
            // Update pos_id to assigned terminal from user Input
            'user_id' => auth()->user()->id, // Created by
            'status' => 0, // 0: pending, 1: referenced
        ]);
        $totalAmount = 0;
        $totalQty = 0;
        // Line is for the number of items that needs to be inserted in the OrderLine model.
        $line = [];
        for ($i = 0; $i < count($items); $i++) {
            $item = $items[$i];
            $qty = $requestValues[$i];
            $price = $item->price == 0 ? (($item->markup / 100) * $item->cost) + $item->cost : $item->price;
            $unitName = $item->type ? 'PCS' : 'KGS';
            $subTotal = $qty * $price;
            // Update Global Scope Variables for Order Model
            $totalAmount += $subTotal;
            $totalQty += $qty;
            $line[] = [
                'qty' => $qty,
                'price' => $price,
                'unit_name' => $unitName,
                'item_name' => $item->name,
                'discount' => 0,
                'sub_total' => $subTotal, // qty * price
                'unit_qty' => 1, // Set number to 1 since this is from a mobile Application. Unit counting is always "1"
                'cost' => $item->cost,
                'vat_type' => $item->vatable, // 0 = Non-VAT, 1 = VATable, 2 = Zero-Rated
                'item_id' => $item->id,
                'unit_id' => null,
                'discount_by' => null,
                'discount_id' => null,
                'tax_id' => $item->tax_id,
                'rate' => $item->tax->rate,
                'discount_type' => null,
                'pwd_rate' => $item->pwd,
                'sc_rate' => $item->senior,
                'discountable' => $item->discountable,
                'type' => $item->type,
                'order_id' => $order->id,
            ];
        }
        $orderLine = OrderLine::insert($line);
        $order->update([
            'qty' => $totalQty,
            'amount' => $totalAmount,
        ]);
        // Deduct Order Items to Stock for reservation of Items/Products
        // Get First Store since this is a multi Store System
        $store = Store::where('status', true)->first();
        $itemStores = ItemStore::whereIn('item_id', array_keys($request->all()))
            ->where('store_id', $store->id)
            ->get();
        $updatedStocks = [];
        //        for($i = 0; $i < count($requestValues); $i++){
        //            $updatedStocks = [
        //                'id' =>$itemStores[$i]->id,
        //                'stock' => $itemStores[$i]->stock - $requestValues[$i]
        //            ];
        //        }
        foreach ($itemStores as $index => $itemStore) {
            $updatedStocks[] = [
                'id' => $itemStore->id,
                'stock' => $itemStore->stock - $requestValues[$index],
            ];
        }

        Batch::update(new ItemStore, $updatedStocks, 'id');

        // Return Response Order
        return $this->success([
            'order' => Order::where('id', $order->id)->with('lines')->first(),
        ]);
    }

    public function show(Order $order): JsonResponse
    {
        $order = Order::where('id', $order->id)->with(['pos', 'lines'])->first();

        return $this->success($order);
    }

    public function update(Request $request, Order $order): JsonResponse
    {
        return $this->success(null);
    }

    public function destroy(Order $order): JsonResponse
    {
        return $this->success(null);
    }

    public function showProducts(Request $request): JsonResponse
    {
        $products = Item::where(function ($q) use ($request) {
            $q->where('name', 'like', "%$request->keyword%");
            $q->orWhere('barcode', 'like', "%$request->keyword%");
        })
            ->where('status', true)
            ->where('show_in_pos', true)
            ->take(100)->get();

        return $this->success($products);
    }
}
