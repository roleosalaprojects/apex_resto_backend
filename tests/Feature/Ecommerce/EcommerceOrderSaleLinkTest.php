<?php

namespace Tests\Feature\Ecommerce;

use App\Models\Ecommerce\EcommerceOrder;
use App\Models\Pos\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The EcommerceOrder ↔ Sale link can become phantom when an ID gets
 * recycled — e.g. after a TRUNCATE bypassed the ON DELETE SET NULL
 * cascade via FOREIGN_KEY_CHECKS=0. The order's sale() relation now
 * defensively requires `sales.created_at >= order.created_at` so an
 * older sale whose ecommerce_order_id happens to match a recycled
 * order id can never render as the new order's "Paid" panel.
 */
class EcommerceOrderSaleLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_sale_relation_returns_legitimate_link(): void
    {
        $order = EcommerceOrder::factory()->create([
            'created_at' => now()->subMinute(),
        ]);
        $sale = Sale::factory()->create([
            'ecommerce_order_id' => $order->id,
            'created_at' => now(),
        ]);

        $this->assertNotNull($order->fresh()->sale);
        $this->assertSame($sale->id, $order->fresh()->sale->id);
    }

    public function test_sale_relation_filters_out_pre_order_phantom(): void
    {
        // Phantom: a sale created BEFORE the order ever existed but
        // whose ecommerce_order_id collides (e.g. ID-recycling after a
        // TRUNCATE). The defensive guard must hide it.
        $order = EcommerceOrder::factory()->create([
            'created_at' => now(),
        ]);
        Sale::factory()->create([
            'ecommerce_order_id' => $order->id,
            'created_at' => now()->subMonth(),
        ]);

        $this->assertNull($order->fresh()->sale,
            'A sale created before the order cannot legitimately belong to it — phantom link must be filtered.');
    }

    public function test_sale_relation_with_simultaneous_created_at_resolves(): void
    {
        // Edge: a sale created in the exact same instant as the order
        // (admin flow where order + sale write within the same Carbon
        // tick). Must NOT be filtered out — the >= constraint covers it.
        $now = now();
        $order = EcommerceOrder::factory()->create(['created_at' => $now]);
        $sale = Sale::factory()->create([
            'ecommerce_order_id' => $order->id,
            'created_at' => $now,
        ]);

        $this->assertSame($sale->id, $order->fresh()->sale?->id);
    }
}
