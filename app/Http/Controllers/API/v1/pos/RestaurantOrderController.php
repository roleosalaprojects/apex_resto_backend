<?php

namespace App\Http\Controllers\API\v1\pos;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Pos\Order;
use App\Models\Pos\OrderLine;
use App\Models\Pos\Sale;
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
            'lines.*.seat' => ['nullable', 'integer', 'min:1'],
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
            'lines.*.seat' => ['nullable', 'integer', 'min:1'],
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

    public function assignSeat(Request $request, Order $order, OrderLine $line): JsonResponse
    {
        abort_unless($line->order_id === $order->id, 404);

        $validated = $request->validate([
            'seat' => ['present', 'nullable', 'integer', 'min:1'],
        ]);

        try {
            $line = $this->orders->assignSeat($line, $validated['seat']);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success($line);
    }

    /**
     * Settlement payload rules shared by the settle endpoints. Single-tender
     * sends payment_type (+ cash/reference/bank fields); multi-tender sends
     * payments[] (two or more tenders — credit and cheque excluded) instead.
     * Declaring pax with sc_count/pwd_count computes the RMC 38-2012 SC/PWD
     * group discount server-side for this receipt.
     *
     * @return array<string, mixed>
     */
    private function settlementRules(): array
    {
        return [
            'payment_type' => ['required_without:payments', 'integer'],
            'cash' => ['nullable', 'numeric'],
            'customer_id' => ['nullable', 'integer'],
            'reference_number' => ['nullable', 'string'],
            'bank_amount' => ['nullable', 'numeric'],
            'bank_id' => ['nullable', 'integer'],
            'payments' => ['sometimes', 'array', 'min:2'],
            'payments.*.payment_type' => ['required', 'integer', Rule::in(Sale::MULTI_TENDER_TYPES)],
            'payments.*.amount' => ['required', 'numeric', 'min:0.01'],
            'payments.*.reference_number' => ['nullable', 'string'],
            'payments.*.bank_id' => ['nullable', 'integer', 'exists:banks,id'],
            'pax' => ['nullable', 'integer', 'min:1'],
            'sc_count' => ['nullable', 'integer', 'min:0'],
            'pwd_count' => ['nullable', 'integer', 'min:0'],
            'special_discount_name' => ['nullable', 'string'],
            'special_discount_id' => ['nullable', 'string'],
            'special_discount_tin' => ['nullable', 'string'],
        ];
    }

    public function settleSeat(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate(array_merge([
            'seats' => ['required', 'array', 'min:1'],
            'seats.*' => ['integer'],
        ], $this->settlementRules()));

        try {
            $sale = $this->orders->settleSeats(
                $order,
                $validated['seats'],
                $validated,
                Auth::guard('api')->user()->user_id,
            );
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        $order->refresh();

        return $this->success([
            'sale_id' => $sale->id,
            'son' => $sale->son,
            'total' => $sale->total,
            'fully_settled' => $order->sales_id !== null,
            'order_status' => $order->status,
        ]);
    }

    public function settle(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate($this->settlementRules());

        try {
            $sale = $this->orders->settle($order, $validated, Auth::guard('api')->user()->user_id);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success([
            'sale_id' => $sale->id,
            'son' => $sale->son,
            'total' => $sale->total,
        ]);
    }

    public function splitSettle(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate(array_merge([
            'line_ids' => ['required', 'array', 'min:1'],
            'line_ids.*' => ['integer'],
        ], $this->settlementRules()));

        try {
            $sale = $this->orders->splitSettle(
                $order,
                $validated['line_ids'],
                $validated,
                Auth::guard('api')->user()->user_id,
            );
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        $order->refresh();

        return $this->success([
            'sale_id' => $sale->id,
            'son' => $sale->son,
            'total' => $sale->total,
            'fully_settled' => $order->sales_id !== null,
            'order_status' => $order->status,
        ]);
    }

    public function cancel(Order $order): JsonResponse
    {
        $order = $this->orders->cancel($order, Auth::guard('api')->id());

        return $this->success($order);
    }
}
