<?php

namespace Tests\Feature\Restaurant;

use App\Models\Employees\Role;
use App\Models\Pos\Order;
use App\Models\Pos\OrderLine;
use App\Models\Pos\Sale;
use App\Models\Products\Category;
use App\Models\Products\Item;
use App\Models\Products\ItemStore;
use App\Models\Restaurant\KitchenStation;
use App\Models\Restaurant\RestaurantTable;
use App\Models\Settings\Pos;
use App\Models\Settings\Store;
use App\Models\User;
use App\Services\CompositeItemService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class WaiterOrderFlowTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Store $store;

    protected Pos $pos;

    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::factory()->admin()->create();
        $this->user = User::factory()->create(['role_id' => $role->id, 'user_id' => 1]);
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
        $this->category = Category::factory()->create(['status' => true]);
    }

    public function test_open_dine_in_order_occupies_table_and_routes_lines(): void
    {
        Passport::actingAs($this->user);

        $station = KitchenStation::factory()->create(['user_id' => 1]);
        $this->category->update(['kitchen_station_id' => $station->id]);
        $table = RestaurantTable::factory()->create(['user_id' => 1, 'store_id' => $this->store->id]);
        $item = Item::factory()->create(['category_id' => $this->category->id, 'price' => 100]);

        $response = $this->postJson('/api/v1/restaurant-orders', [
            'order_type' => Order::TYPE_DINE_IN,
            'table_id' => $table->id,
            'pax' => 4,
            'pos_id' => $this->pos->id,
            'lines' => [
                ['item_id' => $item->id, 'qty' => 2, 'notes' => 'No onions'],
            ],
        ]);

        $response->assertStatus(201);
        $order = Order::first();
        $this->assertEquals(Order::TYPE_DINE_IN, $order->order_type);
        $this->assertEquals(4, $order->pax);
        $this->assertEquals(RestaurantTable::STATUS_OCCUPIED, $table->fresh()->status);

        $line = $order->lines->first();
        $this->assertEquals($station->id, $line->kitchen_station_id);
        $this->assertEquals('No onions', $line->notes);
        $this->assertEquals(1, $line->round);
    }

    public function test_add_round_increments_round_number(): void
    {
        Passport::actingAs($this->user);

        $table = RestaurantTable::factory()->create(['user_id' => 1]);
        $item = Item::factory()->create(['category_id' => $this->category->id, 'price' => 50]);

        $create = $this->postJson('/api/v1/restaurant-orders', [
            'order_type' => Order::TYPE_DINE_IN,
            'table_id' => $table->id,
            'pos_id' => $this->pos->id,
            'lines' => [['item_id' => $item->id, 'qty' => 1]],
        ])->assertStatus(201);

        $orderId = $create->json('data.id');

        $this->postJson("/api/v1/restaurant-orders/{$orderId}/rounds", [
            'lines' => [['item_id' => $item->id, 'qty' => 3]],
        ])->assertStatus(200);

        $rounds = OrderLine::where('order_id', $orderId)->pluck('round')->sort()->values()->all();
        $this->assertEquals([1, 2], $rounds);
    }

    public function test_transfer_table_frees_old_and_occupies_new(): void
    {
        Passport::actingAs($this->user);

        $tableA = RestaurantTable::factory()->create(['user_id' => 1]);
        $tableB = RestaurantTable::factory()->create(['user_id' => 1]);
        $item = Item::factory()->create(['category_id' => $this->category->id, 'price' => 50]);

        $orderId = $this->postJson('/api/v1/restaurant-orders', [
            'order_type' => Order::TYPE_DINE_IN,
            'table_id' => $tableA->id,
            'pos_id' => $this->pos->id,
            'lines' => [['item_id' => $item->id, 'qty' => 1]],
        ])->json('data.id');

        $this->postJson("/api/v1/restaurant-orders/{$orderId}/transfer-table", [
            'table_id' => $tableB->id,
        ])->assertStatus(200);

        $this->assertEquals(RestaurantTable::STATUS_AVAILABLE, $tableA->fresh()->status);
        $this->assertEquals(RestaurantTable::STATUS_OCCUPIED, $tableB->fresh()->status);
    }

    public function test_settle_creates_sale_with_correct_total_and_explodes_composite_stock(): void
    {
        Passport::actingAs($this->user);

        $coffee = Item::factory()->create(['cost' => 0.50, 'uom_label' => 'g', 'category_id' => $this->category->id]);
        ItemStore::factory()->create(['item_id' => $coffee->id, 'store_id' => $this->store->id, 'stock' => 1000]);

        $latte = Item::factory()->create(['price' => 130, 'category_id' => $this->category->id]);
        app(CompositeItemService::class)->syncComponents($latte, [
            ['component_item_id' => $coffee->id, 'qty' => 20],
        ], $this->user->id);

        $table = RestaurantTable::factory()->create(['user_id' => 1]);

        $orderId = $this->postJson('/api/v1/restaurant-orders', [
            'order_type' => Order::TYPE_DINE_IN,
            'table_id' => $table->id,
            'pax' => 2,
            'pos_id' => $this->pos->id,
            'lines' => [['item_id' => $latte->id, 'qty' => 2]],
        ])->json('data.id');

        $response = $this->postJson("/api/v1/restaurant-orders/{$orderId}/settle", [
            'payment_type' => Sale::PAYMENT_CASH,
            'cash' => 300,
        ]);

        $response->assertStatus(200)->assertJsonPath('data.total', 260);

        $sale = Sale::first();
        $this->assertEquals(260, $sale->total);
        $this->assertEquals(Order::TYPE_DINE_IN, $sale->order_type);
        $this->assertEquals($table->id, $sale->table_id);

        $order = Order::find($orderId);
        $this->assertEquals($sale->id, $order->sales_id);
        $this->assertEquals(Order::STATUS_COMPLETED, $order->status);
        $this->assertEquals(RestaurantTable::STATUS_AVAILABLE, $table->fresh()->status);

        // Composite explosion: 20g coffee x 2 lattes = 40 deducted.
        $this->assertEquals(1000 - 40, (float) ItemStore::where('item_id', $coffee->id)->value('stock'));
    }

    public function test_voided_line_is_excluded_from_settlement_total(): void
    {
        Passport::actingAs($this->user);

        $table = RestaurantTable::factory()->create(['user_id' => 1]);
        $item = Item::factory()->create(['price' => 100, 'category_id' => $this->category->id]);
        ItemStore::factory()->create(['item_id' => $item->id, 'store_id' => $this->store->id, 'stock' => 100]);

        $create = $this->postJson('/api/v1/restaurant-orders', [
            'order_type' => Order::TYPE_DINE_IN,
            'table_id' => $table->id,
            'pos_id' => $this->pos->id,
            'lines' => [
                ['item_id' => $item->id, 'qty' => 1],
                ['item_id' => $item->id, 'qty' => 1],
            ],
        ])->assertStatus(201);

        $orderId = $create->json('data.id');
        $lineId = $create->json('data.lines.0.id');

        $this->postJson("/api/v1/restaurant-orders/{$orderId}/lines/{$lineId}/void", [
            'reason' => 'Customer changed mind',
        ])->assertStatus(200);

        $this->postJson("/api/v1/restaurant-orders/{$orderId}/settle", [
            'payment_type' => Sale::PAYMENT_CASH,
            'cash' => 100,
        ])->assertStatus(200)->assertJsonPath('data.total', 100);
    }

    public function test_tenancy_isolation_on_orders_index(): void
    {
        Order::create([
            'reference' => 'OTHER',
            'qty' => 1,
            'amount' => 10,
            'user_id' => 999,
            'status' => Order::STATUS_PREPARING,
            'order_type' => Order::TYPE_DINE_IN,
        ]);

        Passport::actingAs($this->user);
        $response = $this->getJson('/api/v1/restaurant-orders');

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data'));
    }
}
