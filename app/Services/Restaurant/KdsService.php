<?php

namespace App\Services\Restaurant;

use App\Models\Pos\Order;
use App\Models\Pos\OrderLine;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Kitchen Display System operations.
 *
 * v1 is polling REST: the KDS client polls queueForStation() with an
 * `updated_since` cursor and POSTs bump events. bumpLine() is the single
 * choke point a future broadcast layer (Reverb) can hook into.
 */
class KdsService
{
    /**
     * Active (non-served, non-voided) lines routed to a station, optionally
     * only those touched since $since (poll cursor).
     *
     * @return Collection<int, OrderLine>
     */
    public function queueForStation(int $stationId, ?string $since = null): Collection
    {
        $query = OrderLine::query()
            ->where('kitchen_station_id', $stationId)
            ->whereIn('line_status', [OrderLine::LINE_QUEUED, OrderLine::LINE_PREPARING, OrderLine::LINE_READY])
            ->with(['item:id,name', 'order:id,reference,table_id,order_type'])
            ->orderBy('fired_at');

        if ($since !== null) {
            $query->where('updated_at', '>', Carbon::parse($since));
        }

        return $query->get();
    }

    /**
     * Advance a line one step along queued → preparing → ready → served,
     * stamping the matching timestamp. Voided lines never advance.
     */
    public function bumpLine(OrderLine $line, ?int $userId = null): OrderLine
    {
        if ($line->line_status === OrderLine::LINE_VOIDED) {
            return $line;
        }

        $next = match ($line->line_status) {
            OrderLine::LINE_QUEUED => OrderLine::LINE_PREPARING,
            OrderLine::LINE_PREPARING => OrderLine::LINE_READY,
            OrderLine::LINE_READY => OrderLine::LINE_SERVED,
            default => OrderLine::LINE_SERVED,
        };

        $attributes = [
            'line_status' => $next,
            'bumped_by' => $userId,
        ];

        if ($next === OrderLine::LINE_READY) {
            $attributes['ready_at'] = now();
        } elseif ($next === OrderLine::LINE_SERVED) {
            $attributes['served_at'] = now();
        }

        $line->update($attributes);

        return $line->refresh();
    }

    /**
     * Bump every active line of an order to served in one action (the
     * "all done" button on the KDS ticket).
     */
    public function bumpOrder(Order $order, ?int $userId = null): void
    {
        $order->lines()
            ->whereIn('line_status', [OrderLine::LINE_QUEUED, OrderLine::LINE_PREPARING, OrderLine::LINE_READY])
            ->get()
            ->each(function (OrderLine $line) use ($userId) {
                $line->update([
                    'line_status' => OrderLine::LINE_SERVED,
                    'served_at' => now(),
                    'bumped_by' => $userId,
                ]);
            });
    }
}
