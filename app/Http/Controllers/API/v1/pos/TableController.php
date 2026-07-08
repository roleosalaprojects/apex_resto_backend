<?php

namespace App\Http\Controllers\API\v1\pos;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Pos\Order;
use App\Models\Restaurant\RestaurantTable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TableController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $userId = Auth::guard('api')->user()->user_id;

        // Open orders mapped per occupied table — through the joined-table
        // pivot (falling back to the primary table_id for legacy orders),
        // so every table of a joined party reports the same order.
        $openOrders = Order::query()
            ->where('user_id', $userId)
            ->whereNotNull('order_type')
            ->whereNull('sales_id')
            ->whereNotIn('status', [Order::STATUS_CANCELLED, Order::STATUS_COMPLETED])
            ->whereNotNull('table_id')
            ->with('tables:id')
            ->get(['id', 'table_id', 'reference', 'pax', 'amount', 'status']);

        $orderByTable = [];
        foreach ($openOrders as $order) {
            $tableIds = $order->tables->pluck('id')->all() ?: [$order->table_id];
            foreach ($tableIds as $tableId) {
                $orderByTable[$tableId] = $order;
            }
        }

        $tables = RestaurantTable::query()
            ->where('user_id', $userId)
            ->when($request->filled('store_id'), fn ($q) => $q->where('store_id', $request->integer('store_id')))
            ->orderBy('area')
            ->orderBy('name')
            ->get()
            ->map(function (RestaurantTable $table) use ($orderByTable) {
                $open = $orderByTable[$table->id] ?? null;

                return [
                    'id' => $table->id,
                    'name' => $table->name,
                    'number' => $table->number,
                    'area' => $table->area,
                    'seats' => $table->seats,
                    'status' => $table->status,
                    'open_order' => $open ? [
                        'id' => $open->id,
                        'reference' => $open->reference,
                        'pax' => $open->pax,
                        'amount' => $open->amount,
                        'is_primary_table' => (int) $open->table_id === (int) $table->id,
                    ] : null,
                ];
            });

        return $this->success($tables);
    }
}
