<?php

namespace App\Services\Restaurant;

use App\Models\Pos\Order;
use App\Models\Pos\OrderLine;
use App\Models\Pos\Sale;
use App\Models\Products\Item;
use App\Models\Restaurant\RestaurantTable;
use App\Models\Settings\Pos;
use App\Services\Data\SaleCreationData;
use App\Services\SaleCreationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Waiter ordering lifecycle: open a dine-in/take-out/delivery order, add
 * rounds, transfer tables, void lines pre-settlement, and settle into a
 * Sale via the shared SaleCreationService pipeline.
 */
class RestaurantOrderService
{
    private int $maxCounter = 999999999999999;

    public function __construct(
        private readonly KitchenRoutingService $routing,
        private readonly SaleCreationService $saleCreation,
    ) {}

    /**
     * Open a new order and fire its first round of lines to the kitchen.
     *
     * @param  array<string, mixed>  $attributes  order header (order_type, table_id, pax, waiter_id, ...)
     * @param  array<int, array<string, mixed>>  $lines  [{item_id, qty, notes?, unit_id?, unit_qty?}]
     */
    public function openOrder(array $attributes, array $lines, int $userId, ?int $posId = null): Order
    {
        return DB::transaction(function () use ($attributes, $lines, $userId, $posId) {
            $orderType = (int) ($attributes['order_type'] ?? Order::TYPE_DINE_IN);

            $order = Order::create([
                'reference' => $attributes['reference'] ?? strtoupper(Str::random(8)),
                'qty' => 0,
                'amount' => 0,
                'pos_id' => $posId,
                'user_id' => $userId,
                'status' => Order::STATUS_PREPARING,
                'order_type' => $orderType,
                'table_id' => $attributes['table_id'] ?? null,
                'pax' => $attributes['pax'] ?? null,
                'sc_count' => $attributes['sc_count'] ?? null,
                'pwd_count' => $attributes['pwd_count'] ?? null,
                'waiter_id' => $attributes['waiter_id'] ?? null,
                'guest_name' => $attributes['guest_name'] ?? null,
                'store_id' => $attributes['store_id'] ?? null,
                'delivery_address' => $attributes['delivery_address'] ?? null,
                'delivery_contact' => $attributes['delivery_contact'] ?? null,
                'delivery_status' => $attributes['delivery_status'] ?? null,
                'notes' => $attributes['notes'] ?? null,
            ]);

            $this->fireLines($order, $lines, 1);

            if ($orderType === Order::TYPE_DINE_IN && $order->table_id) {
                RestaurantTable::where('id', $order->table_id)
                    ->update(['status' => RestaurantTable::STATUS_OCCUPIED]);
            }

            return $order->fresh('lines');
        });
    }

    /**
     * Add another round of lines to an open order.
     *
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function addRound(Order $order, array $lines): Order
    {
        $this->assertOpen($order);

        return DB::transaction(function () use ($order, $lines) {
            $nextRound = (int) ($order->lines()->max('round') ?? 0) + 1;
            $this->fireLines($order, $lines, $nextRound);

            return $order->fresh('lines');
        });
    }

    /**
     * Move an open order to a different table, freeing the old one.
     */
    public function transferTable(Order $order, int $newTableId): Order
    {
        $this->assertOpen($order);

        return DB::transaction(function () use ($order, $newTableId) {
            $oldTableId = $order->table_id;

            $order->update(['table_id' => $newTableId]);

            if ($oldTableId && $oldTableId !== $newTableId) {
                RestaurantTable::where('id', $oldTableId)
                    ->update(['status' => RestaurantTable::STATUS_AVAILABLE]);
            }
            RestaurantTable::where('id', $newTableId)
                ->update(['status' => RestaurantTable::STATUS_OCCUPIED]);

            return $order->fresh('lines');
        });
    }

    /**
     * Void a single line before the order is settled.
     */
    public function voidLine(OrderLine $line, ?int $userId = null, ?string $reason = null): OrderLine
    {
        if ($line->order && $line->order->sales_id) {
            throw new RuntimeException('Cannot void a line on an already-settled order.');
        }

        $line->update([
            'line_status' => OrderLine::LINE_VOIDED,
            'voided_by' => $userId,
            'void_reason' => $reason,
        ]);

        return $line->refresh();
    }

    /**
     * Settle the order: convert non-voided lines into a Sale, link the
     * order to it, mark complete, and free the table.
     *
     * @param  array<string, mixed>  $payment
     */
    public function settle(Order $order, array $payment, int $userId): Sale
    {
        $this->assertOpen($order);

        $pos = Pos::with('store')->findOrFail($order->pos_id);

        return DB::transaction(function () use ($order, $payment, $pos) {
            [$counter, $sonType] = $this->computeSaleCounter($pos);

            $data = SaleCreationData::fromRestaurantOrder($order, $pos, $counter, $sonType, $payment);
            $sale = $this->saleCreation->create($data);

            $order->update([
                'sales_id' => $sale->id,
                'status' => Order::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);

            if ($order->table_id) {
                RestaurantTable::where('id', $order->table_id)
                    ->update(['status' => RestaurantTable::STATUS_AVAILABLE]);
            }

            return $sale;
        });
    }

    /**
     * Cancel an open order, voiding its lines and freeing the table.
     */
    public function cancel(Order $order, ?int $userId = null): Order
    {
        $this->assertOpen($order);

        return DB::transaction(function () use ($order, $userId) {
            $order->lines()
                ->where('line_status', '!=', OrderLine::LINE_VOIDED)
                ->update(['line_status' => OrderLine::LINE_VOIDED, 'voided_by' => $userId]);

            $order->update([
                'status' => Order::STATUS_CANCELLED,
                'cancelled_by' => $userId,
                'cancelled_at' => now(),
            ]);

            if ($order->table_id) {
                RestaurantTable::where('id', $order->table_id)
                    ->update(['status' => RestaurantTable::STATUS_AVAILABLE]);
            }

            return $order->fresh('lines');
        });
    }

    /**
     * Create OrderLines for a round, resolving each item's kitchen station
     * and stamping fired_at so the KDS queue orders correctly.
     *
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function fireLines(Order $order, array $lines, int $round): void
    {
        $now = now();

        foreach ($lines as $line) {
            $item = Item::with('category')->findOrFail($line['item_id']);
            $qty = (float) ($line['qty'] ?? 1);
            $price = (float) ($line['price'] ?? $item->price);
            $discount = (float) ($line['discount'] ?? 0);

            OrderLine::create([
                'qty' => $qty,
                'price' => $price,
                'unit_name' => $line['unit_name'] ?? ($item->uom_label ?? 'PC'),
                'item_name' => $item->name,
                'discount' => $discount,
                'sub_total' => $qty * ($price - $discount),
                'unit_qty' => $line['unit_qty'] ?? 1,
                'cost' => $item->cost,
                'item_id' => $item->id,
                'unit_id' => $line['unit_id'] ?? null,
                'order_id' => $order->id,
                'notes' => $line['notes'] ?? null,
                'round' => $round,
                'kitchen_station_id' => $this->routing->resolveStation($item),
                'line_status' => OrderLine::LINE_QUEUED,
                'fired_at' => $now,
            ]);
        }

        $totals = $order->lines()
            ->where('line_status', '!=', OrderLine::LINE_VOIDED)
            ->selectRaw('COALESCE(SUM(qty),0) as qty, COALESCE(SUM(sub_total),0) as amount')
            ->first();

        $order->update([
            'qty' => $totals->qty,
            'amount' => $totals->amount,
        ]);
    }

    private function assertOpen(Order $order): void
    {
        if ($order->sales_id) {
            throw new RuntimeException('Order is already settled.');
        }
        if ((int) $order->status === Order::STATUS_CANCELLED) {
            throw new RuntimeException('Order is cancelled.');
        }
    }

    /**
     * Next official SI counter for a POS terminal. Mirrors
     * SaleController::computeCounter for type=false sales; Phase 3 will
     * centralise this in DocumentNumberService.
     *
     * @return array{0: int, 1: int|string}
     */
    private function computeSaleCounter(Pos $pos): array
    {
        $resetCounter = $pos->reset_counter;
        $latestSale = Sale::where('pos_id', $pos->id)->where('type', false)->latest()->first();

        if (! $latestSale) {
            $counter = 100000;
        } elseif ($latestSale->counter == $this->maxCounter) {
            $pos->update(['reset_counter' => $pos->reset_counter + 1]);
            $resetCounter += 1;
            $counter = 100000;
        } else {
            $counter = $latestSale->counter + 1;
        }

        return [$counter, $resetCounter];
    }
}
