<?php

use App\Models\Ecommerce\EcommerceOrder;
use App\Models\Ecommerce\EcommerceOrderStatusChange;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Synthesize a status history for every pre-existing EcommerceOrder so
 * the new Status History timeline isn't empty on day one. We use the
 * timestamps we already had on the order/sale to reconstruct the events
 * we can prove happened:
 *
 *   created_at        → null  → pending
 *   verified_at       → 0     → 1   (verified)
 *   cancelled_at      → 0     → 2   (cancelled)
 *   sale.created_at   → prev  → 3   (paid)
 *
 * Statuses past PAID (preparing, picked_up) don't have stored
 * timestamps on the order, so we deliberately do NOT fabricate them —
 * better an absent log row than a fictional one. From here on,
 * logStatusChange() captures the real timestamp at the moment of the
 * transition.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Stream orders in chunks so a tenant with thousands of orders
        // doesn't load them all into memory at once.
        EcommerceOrder::with('sale')->chunkById(200, function ($orders) {
            foreach ($orders as $order) {
                $this->backfill($order);
            }
        });
    }

    public function down(): void
    {
        DB::table('ecommerce_order_status_changes')->delete();
    }

    private function backfill(EcommerceOrder $order): void
    {
        // Skip orders that already have history (idempotent re-run).
        if (EcommerceOrderStatusChange::where('ecommerce_order_id', $order->id)->exists()) {
            return;
        }

        $rows = [];
        $rows[] = [
            'ecommerce_order_id' => $order->id,
            'from_status' => null,
            'to_status' => EcommerceOrder::STATUS_PENDING,
            'changed_by' => null,
            'note' => 'Backfilled from order created_at',
            'created_at' => $order->created_at,
        ];

        if ($order->verified_at) {
            $rows[] = [
                'ecommerce_order_id' => $order->id,
                'from_status' => EcommerceOrder::STATUS_PENDING,
                'to_status' => EcommerceOrder::STATUS_VERIFIED,
                'changed_by' => $order->verified_by,
                'note' => 'Backfilled from verified_at',
                'created_at' => $order->verified_at,
            ];
        }

        if ($order->cancelled_at) {
            $rows[] = [
                'ecommerce_order_id' => $order->id,
                'from_status' => EcommerceOrder::STATUS_PENDING,
                'to_status' => EcommerceOrder::STATUS_CANCELLED,
                'changed_by' => $order->cancelled_by,
                'note' => 'Backfilled from cancelled_at',
                'created_at' => $order->cancelled_at,
            ];
        }

        if ($order->sale) {
            // Whichever of verified or pending was the last logged
            // state — use it as the prior status of "paid".
            $priorPaid = $order->verified_at
                ? EcommerceOrder::STATUS_VERIFIED
                : EcommerceOrder::STATUS_PENDING;

            $rows[] = [
                'ecommerce_order_id' => $order->id,
                'from_status' => $priorPaid,
                'to_status' => EcommerceOrder::STATUS_PAID,
                'changed_by' => $order->sale->sales_by,
                'note' => 'Backfilled from sale.created_at',
                'created_at' => $order->sale->created_at,
            ];
        }

        DB::table('ecommerce_order_status_changes')->insert($rows);
    }
};
