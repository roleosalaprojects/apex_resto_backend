<?php

namespace App\Http\Controllers\API\v1\pos;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Pos\Order;
use App\Models\Pos\OrderLine;
use App\Services\Restaurant\RestaurantOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class RestaurantOrderController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly RestaurantOrderService $orders) {}

    public function index(Request $request): JsonResponse
    {
        $userId = Auth::guard('api')->user()->user_id;

        $orders = Order::query()
            ->where('user_id', $userId)
            ->whereNotNull('order_type')
            ->whereNull('sales_id')
            ->whereNotIn('status', [Order::STATUS_CANCELLED, Order::STATUS_COMPLETED])
            ->with(['lines', 'table:id,name,number', 'waiter:id,name'])
            ->orderByDesc('id')
            ->get();

        return $this->success($orders);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_type' => ['required', Rule::in([Order::TYPE_DINE_IN, Order::TYPE_TAKE_OUT, Order::TYPE_DELIVERY])],
            'table_id' => ['nullable', 'integer', 'exists:restaurant_tables,id'],
            'pax' => ['nullable', 'integer', 'min:1'],
            'sc_count' => ['nullable', 'integer', 'min:0'],
            'pwd_count' => ['nullable', 'integer', 'min:0'],
            'waiter_id' => ['nullable', 'integer'],
            'guest_name' => ['nullable', 'string'],
            'delivery_address' => ['nullable', 'string'],
            'delivery_contact' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'pos_id' => ['nullable', 'integer', 'exists:pos,id'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', 'integer', 'exists:items,id'],
            'lines.*.qty' => ['required', 'numeric', 'min:0.01'],
            'lines.*.notes' => ['nullable', 'string'],
            'lines.*.unit_id' => ['nullable', 'integer'],
            'lines.*.unit_qty' => ['nullable', 'numeric'],
        ]);

        $user = Auth::guard('api')->user();
        $attributes = array_merge($validated, [
            'store_id' => $request->input('store_id'),
        ]);

        $order = $this->orders->openOrder(
            $attributes,
            $validated['lines'],
            $user->user_id,
            $validated['pos_id'] ?? $request->input('pos_id'),
        );

        return $this->created($order->load(['lines', 'table:id,name,number']));
    }

    public function show(Order $order): JsonResponse
    {
        return $this->success($order->load(['lines.item:id,name', 'table:id,name,number', 'waiter:id,name', 'sale:id,son,total']));
    }

    public function rounds(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', 'integer', 'exists:items,id'],
            'lines.*.qty' => ['required', 'numeric', 'min:0.01'],
            'lines.*.notes' => ['nullable', 'string'],
            'lines.*.unit_id' => ['nullable', 'integer'],
            'lines.*.unit_qty' => ['nullable', 'numeric'],
        ]);

        $order = $this->orders->addRound($order, $validated['lines']);

        return $this->success($order->load('lines'));
    }

    public function transferTable(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'table_id' => ['required', 'integer', 'exists:restaurant_tables,id'],
        ]);

        $order = $this->orders->transferTable($order, $validated['table_id']);

        return $this->success($order);
    }

    public function voidLine(Request $request, Order $order, OrderLine $line): JsonResponse
    {
        abort_unless($line->order_id === $order->id, 404);

        $validated = $request->validate([
            'reason' => ['nullable', 'string'],
        ]);

        $line = $this->orders->voidLine($line, Auth::guard('api')->id(), $validated['reason'] ?? null);

        return $this->success($line);
    }

    public function settle(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'payment_type' => ['required', 'integer'],
            'cash' => ['nullable', 'numeric'],
            'customer_id' => ['nullable', 'integer'],
            'reference_number' => ['nullable', 'string'],
            'bank_amount' => ['nullable', 'numeric'],
            'bank_id' => ['nullable', 'integer'],
        ]);

        $sale = $this->orders->settle($order, $validated, Auth::guard('api')->user()->user_id);

        return $this->success([
            'sale_id' => $sale->id,
            'son' => $sale->son,
            'total' => $sale->total,
        ]);
    }

    public function cancel(Order $order): JsonResponse
    {
        $order = $this->orders->cancel($order, Auth::guard('api')->id());

        return $this->success($order);
    }
}
