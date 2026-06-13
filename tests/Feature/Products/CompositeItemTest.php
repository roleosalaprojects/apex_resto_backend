<?php

namespace Tests\Feature\Products;

use App\Models\Employees\Role;
use App\Models\Products\Item;
use App\Models\Products\ItemStore;
use App\Models\Settings\Pos;
use App\Models\Settings\Store;
use App\Models\User;
use App\Services\CompositeItemService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Laravel\Passport\Passport;
use Tests\TestCase;

class CompositeItemTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Store $store;

    protected Pos $pos;

    protected CompositeItemService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::factory()->admin()->create();
        $this->user = User::factory()->create([
            'role_id' => $role->id,
            'user_id' => 1,
        ]);
        $this->store = Store::factory()->create();
        $this->pos = Pos::create([
            'name' => 'Test POS',
            'store_id' => $this->store->id,
            'status' => true,
            'mac' => '00:00:00:00:00:00',
            'number' => 1,
            'user_id' => $this->user->id,
            'reset_counter' => 1,
        ]);
        $this->service = app(CompositeItemService::class);
    }

    public function test_sync_components_recalculates_cost_from_recipe(): void
    {
        $coffee = Item::factory()->create(['cost' => 0.50, 'uom_label' => 'g']);
        $milk = Item::factory()->create(['cost' => 0.10, 'uom_label' => 'ml']);
        $latte = Item::factory()->create(['cost' => 0, 'price' => 130]);

        $this->service->syncComponents($latte, [
            ['component_item_id' => $coffee->id, 'qty' => 20],
            ['component_item_id' => $milk->id, 'qty' => 200],
        ], $this->user->id);

        $latte->refresh();
        $this->assertTrue($latte->is_composite);
        $this->assertEquals(30.00, (float) $latte->cost);
        $this->assertDatabaseHas('price_histories', [
            'item_id' => $latte->id,
            'change_reason' => 'composite',
        ]);
    }

    public function test_cost_override_skips_recalculation(): void
    {
        $coffee = Item::factory()->create(['cost' => 0.50]);
        $latte = Item::factory()->create(['cost' => 99, 'cost_override' => true]);

        $this->service->syncComponents($latte, [
            ['component_item_id' => $coffee->id, 'qty' => 20],
        ], $this->user->id);

        $this->assertEquals(99.00, (float) $latte->fresh()->cost);
    }

    public function test_direct_and_transitive_cycles_are_rejected(): void
    {
        $a = Item::factory()->create();
        $b = Item::factory()->create();
        $c = Item::factory()->create();

        $this->service->syncComponents($a, [['component_item_id' => $b->id, 'qty' => 1]], $this->user->id);
        $this->service->syncComponents($b, [['component_item_id' => $c->id, 'qty' => 1]], $this->user->id);

        $this->expectException(InvalidArgumentException::class);
        $this->service->syncComponents($c, [['component_item_id' => $a->id, 'qty' => 1]], $this->user->id);
    }

    public function test_self_reference_is_rejected(): void
    {
        $item = Item::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->service->syncComponents($item, [['component_item_id' => $item->id, 'qty' => 1]], $this->user->id);
    }

    public function test_inactive_component_is_rejected(): void
    {
        $inactive = Item::factory()->inactive()->create();
        $composite = Item::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->service->syncComponents($composite, [['component_item_id' => $inactive->id, 'qty' => 1]], $this->user->id);
    }

    public function test_component_cost_change_cascades_to_parent_composites(): void
    {
        $coffee = Item::factory()->create(['cost' => 0.50]);
        $latte = Item::factory()->create(['cost' => 0]);
        $this->service->syncComponents($latte, [
            ['component_item_id' => $coffee->id, 'qty' => 20],
        ], $this->user->id);
        $this->assertEquals(10.00, (float) $latte->fresh()->cost);

        $coffee->update(['cost' => 1.00]);

        $this->assertEquals(20.00, (float) $latte->fresh()->cost);
    }

    public function test_sale_of_composite_deducts_component_stock_not_own(): void
    {
        Passport::actingAs($this->user);

        [$latte, $coffee, $milk] = $this->createCompositeWithStocks();

        $response = $this->postJson('/api/v1/sales', $this->buildSalePayload($latte, 2));
        $response->assertStatus(200);

        $this->assertEquals(1000 - (20 * 2), $this->stockOf($coffee));
        $this->assertEquals(1000 - (200 * 2), $this->stockOf($milk));
    }

    public function test_refund_of_composite_restores_component_stock(): void
    {
        Passport::actingAs($this->user);

        [$latte, $coffee, $milk] = $this->createCompositeWithStocks();

        $payload = $this->buildSalePayload($latte, 1);
        $payload['type'] = true;
        $this->postJson('/api/v1/sales', $payload)->assertStatus(200);

        $this->assertEquals(1000 + 20, $this->stockOf($coffee));
        $this->assertEquals(1000 + 200, $this->stockOf($milk));
    }

    public function test_pos_item_payload_exposes_recipe(): void
    {
        Passport::actingAs($this->user);

        [$latte] = $this->createCompositeWithStocks();

        $response = $this->getJson('/api/v1/items/'.$latte->id);

        $response->assertStatus(200)
            ->assertJsonPath('data.is_composite', true)
            ->assertJsonCount(2, 'data.components');
    }

    /**
     * @return array{0: Item, 1: Item, 2: Item} [composite, coffee, milk]
     */
    protected function createCompositeWithStocks(): array
    {
        $coffee = Item::factory()->create(['cost' => 0.50, 'uom_label' => 'g']);
        $milk = Item::factory()->create(['cost' => 0.10, 'uom_label' => 'ml']);
        $latte = Item::factory()->create(['price' => 130]);

        foreach ([$coffee, $milk] as $ingredient) {
            ItemStore::factory()->create([
                'item_id' => $ingredient->id,
                'store_id' => $this->store->id,
                'stock' => 1000,
            ]);
        }

        $this->service->syncComponents($latte, [
            ['component_item_id' => $coffee->id, 'qty' => 20],
            ['component_item_id' => $milk->id, 'qty' => 200],
        ], $this->user->id);

        return [$latte->fresh(), $coffee, $milk];
    }

    protected function stockOf(Item $item): float
    {
        return (float) ItemStore::where('item_id', $item->id)
            ->where('store_id', $this->store->id)
            ->value('stock');
    }

    /**
     * Minimal POS sale payload for one line of the given composite.
     *
     * @return array<string, mixed>
     */
    protected function buildSalePayload(Item $composite, int $qty): array
    {
        $total = $composite->price * $qty;

        return [
            'pos_id' => $this->pos->id,
            'type' => false,
            'sale_id' => null,
            'ecommerce_order_id' => null,
            'details' => [
                'total' => $total,
                'cash' => $total,
                'change' => 0,
                'customer_id' => null,
                'points' => 0,
                'points_used' => 0,
                'payment_type' => 1,
                'reference_number' => '',
                'bank_amount' => 0,
                'bank_id' => null,
                'profit' => 0,
                'vatable' => 0,
                'vat' => 0,
                'vat_exempt' => $total,
                'zero_rated' => 0,
                'sc_discount' => 0,
                'pwd_discount' => 0,
                'sp_discount' => 0,
                'naac_discount' => 0,
                'vat_special_discounts' => 0,
                'special_discount_type' => 0,
                'special_discount_name' => '',
                'special_discount_id' => '',
                'special_discount_tin' => '',
                'voucher_id' => null,
                'voucher_code' => null,
                'voucher_discount' => 0,
            ],
            'line' => [[
                'product' => $composite->toArray(),
                'qty' => $qty,
                'price' => $composite->price,
                'discount' => 0,
                'unit' => 'PCS',
                'unit_id' => -1,
                'unit_qty' => 1,
                'cost' => $composite->cost,
                'profit' => ($composite->price - $composite->cost) * $qty,
                'vatable' => 0,
                'vat' => 0,
                'vat_exempt' => $total,
                'zero_rated' => 0,
                'sc_discount' => 0,
                'pwd_discount' => 0,
                'sp_discount' => 0,
                'naac_discount' => 0,
                'vat_special_discounts' => 0,
            ]],
        ];
    }
}
