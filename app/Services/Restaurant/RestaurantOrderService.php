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
use Illuminate\Database\Eloquent\Collection;
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
     * Assign (or clear) a line's seat before it is settled. Once a line is
     * billed its seat is locked.
     */
    public function assignSeat(OrderLine $line, ?int $seat): OrderLine
    {
        if ($line->sales_id) {
            throw new RuntimeException('Cannot reassign the seat of an already-settled line.');
        }

        $line->update(['seat' => $seat]);

        return $line->refresh();
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
     * Settle the whole order: bill every unsettled, non-voided line onto one
     * Sale. After a split bill this pays off whatever remains.
     *
     * @param  array<string, mixed>  $payment
     */
    public function settle(Order $order, array $payment, int $userId): Sale
    {
        $this->assertOpen($order);

        $pos = Pos::with('store')->findOrFail($order->pos_id);
        $lines = $this->unsettledLines($order)->get();

        if ($lines->isEmpty()) {
            throw new RuntimeException('Order has no unsettled lines to settle.');
        }

        return $this->settleLines($order, $lines, $payment, $pos);
    }

    /**
     * Split bill: settle a chosen subset of the order's lines onto its own
     * Sale (its own official SI), leaving the rest open. The order completes
     * and the table frees only once every non-voided line is settled.
     *
     * @param  array<int, int>  $lineIds
     * @param  array<string, mixed>  $payment
     */
    public function splitSettle(Order $order, array $lineIds, array $payment, int $userId): Sale
    {
        $this->assertOpen($order);

        $lineIds = array_values(array_unique(array_map('intval', $lineIds)));

        $pos = Pos::with('store')->findOrFail($order->pos_id);
        $lines = $this->unsettledLines($order)->whereIn('id', $lineIds)->get();

        if ($lines->count() !== count($lineIds)) {
            throw new RuntimeException('One or more selected lines are invalid, already settled, or voided.');
        }

        return $this->settleLines($order, $lines, $payment, $pos);
    }

    /**
     * Bill-by-seat: settle every unsettled, non-voided line belonging to the
     * given seat(s) onto one Sale. Like splitSettle but selecting by seat
     * rather than explicit line ids.
     *
     * @param  array<int, int>  $seats
     * @param  array<string, mixed>  $payment
     */
    public function settleSeats(Order $order, array $seats, array $payment, int $userId): Sale
    {
        $this->assertOpen($order);

        $seats = array_values(array_unique(array_map('intval', $seats)));

        $pos = Pos::with('store')->findOrFail($order->pos_id);
        $lines = $this->unsettledLines($order)->whereIn('seat', $seats)->get();

        if ($lines->isEmpty()) {
            throw new RuntimeException('No unsettled lines for the selected seat(s).');
        }

        return $this->settleLines($order, $lines, $payment, $pos);
    }

    /**
     * Bill the given lines onto a fresh Sale, stamp each line with that
     * sale, and — if nothing unsettled remains — complete the order and
     * free its table.
     *
     * @param  Collection<int, OrderLine>  $lines
     * @param  array<string, mixed>  $payment
     */
    private function settleLines(Order $order, Collection $lines, array $payment, Pos $pos): Sale
    {
        return DB::transaction(function () use ($order, $lines, $payment, $pos) {
            [$counter, $sonType] = $this->computeSaleCounter($pos);

            $lines->loadMissing('item:id,cost,creditable_to_points,vatable');

            $coversWholeOrder = $order->lines()->whereNotNull('sales_id')->doesntExist()
                && $lines->count() === $this->unsettledLines($order)->count();

            $data = SaleCreationData::fromRestaurantOrder($order, $pos, $counter, $sonType, $lines, $payment, $coversWholeOrder);
            $sale = $this->saleCreation->create($data);

            OrderLine::whereIn('id', $lines->pluck('id'))->update(['sales_id' => $sale->id]);

            if ($this->unsettledLines($order)->doesntExist()) {
                $order->update([
                    'sales_id' => $sale->id,
                    'status' => Order::STATUS_COMPLETED,
                    'completed_at' => now(),
                ]);

                if ($order->table_id) {
                    RestaurantTable::where('id', $order->table_id)
                        ->update(['status' => RestaurantTable::STATUS_AVAILABLE]);
                }
            }

            return $sale;
        });
    }

    /**
     * Query for the order's lines that are neither voided nor already
     * settled — i.e. what's still owed.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    private function unsettledLines(Order $order)
    {
        return $order->lines()
            ->whereNull('sales_id')
            ->where('line_status', '!=', OrderLine::LINE_VOIDED);
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
                'seat' => $line['seat'] ?? null,
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
