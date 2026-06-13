<?php

namespace Tests\Feature\Restaurant;

use App\Models\Employees\Role;
use App\Models\Pos\Order;
use App\Models\Pos\OrderLine;
use App\Models\Products\Category;
use App\Models\Products\Item;
use App\Models\Restaurant\KitchenStation;
use App\Models\Restaurant\RestaurantTable;
use App\Models\Settings\Pos;
use App\Models\Settings\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class KdsFlowTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Pos $pos;

    protected Category $category;

    protected KitchenStation $hot;

    protected KitchenStation $cold;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::factory()->admin()->create();
        $this->user = User::factory()->create(['role_id' => $role->id, 'user_id' => 1]);
        $store = Store::factory()->create();
        $this->pos = Pos::create([
            'name' => 'POS', 'store_id' => $store->id, 'status' => true,
            'mac' => '00:00:00:00:00:00', 'number' => 1, 'user_id' => $this->user->id, 'reset_counter' => 1,
        ]);
        $this->category = Category::factory()->create(['status' => true]);
        $this->hot = KitchenStation::factory()->create(['user_id' => 1, 'name' => 'Hot Kitchen']);
        $this->cold = KitchenStation::factory()->create(['user_id' => 1, 'name' => 'Cold Bar']);
    }

    public function test_item_override_routes_to_station_over_category_default(): void
    {
        Passport::actingAs($this->user);
        $this->category->update(['kitchen_station_id' => $this->cold->id]);

        $overridden = Item::factory()->create([
            'category_id' => $this->category->id,
            'kitchen_station_id' => $this->hot->id,
            'price' => 100,
        ]);
        $defaulted = Item::factory()->create(['category_id' => $this->category->id, 'price' => 50]);

        $table = RestaurantTable::factory()->create(['user_id' => 1]);
        $this->postJson('/api/v1/restaurant-orders', [
            'order_type' => Order::TYPE_DINE_IN,
            'table_id' => $table->id,
            'pos_id' => $this->pos->id,
            'lines' => [
                ['item_id' => $overridden->id, 'qty' => 1],
                ['item_id' => $defaulted->id, 'qty' => 1],
            ],
        ])->assertStatus(201);

        $this->assertEquals($this->hot->id, OrderLine::where('item_id', $overridden->id)->value('kitchen_station_id'));
        $this->assertEquals($this->cold->id, OrderLine::where('item_id', $defaulted->id)->value('kitchen_station_id'));
    }

    public function test_station_queue_returns_only_its_active_lines(): void
    {
        Passport::actingAs($this->user);
        $this->category->update(['kitchen_station_id' => $this->hot->id]);
        $item = Item::factory()->create(['category_id' => $this->category->id, 'price' => 100]);
        $table = RestaurantTable::factory()->create(['user_id' => 1]);

        $this->postJson('/api/v1/restaurant-orders', [
            'order_type' => Order::TYPE_DINE_IN,
            'table_id' => $table->id,
            'pos_id' => $this->pos->id,
            'lines' => [['item_id' => $item->id, 'qty' => 1]],
        ])->assertStatus(201);

        $hotQueue = $this->getJson("/api/v1/kds/stations/{$this->hot->id}/queue");
        $hotQueue->assertStatus(200);
        $this->assertCount(1, $hotQueue->json('data.lines'));

        $coldQueue = $this->getJson("/api/v1/kds/stations/{$this->cold->id}/queue");
        $this->assertCount(0, $coldQueue->json('data.lines'));
    }

    public function test_bump_line_advances_status_and_drops_from_queue_when_served(): void
    {
        Passport::actingAs($this->user);
        $this->category->update(['kitchen_station_id' => $this->hot->id]);
        $item = Item::factory()->create(['category_id' => $this->category->id, 'price' => 100]);
        $table = RestaurantTable::factory()->create(['user_id' => 1]);

        $create = $this->postJson('/api/v1/restaurant-orders', [
            'order_type' => Order::TYPE_DINE_IN,
            'table_id' => $table->id,
            'pos_id' => $this->pos->id,
            'lines' => [['item_id' => $item->id, 'qty' => 1]],
        ])->assertStatus(201);

        $lineId = $create->json('data.lines.0.id');

        $this->postJson("/api/v1/kds/lines/{$lineId}/bump")->assertJsonPath('data.line_status', OrderLine::LINE_PREPARING);
        $this->postJson("/api/v1/kds/lines/{$lineId}/bump")->assertJsonPath('data.line_status', OrderLine::LINE_READY);
        $this->postJson("/api/v1/kds/lines/{$lineId}/bump")->assertJsonPath('data.line_status', OrderLine::LINE_SERVED);

        $queue = $this->getJson("/api/v1/kds/stations/{$this->hot->id}/queue");
        $this->assertCount(0, $queue->json('data.lines'));
    }

    public function test_bump_order_serves_all_active_lines(): void
    {
        Passport::actingAs($this->user);
        $this->category->update(['kitchen_station_id' => $this->hot->id]);
        $item = Item::factory()->create(['category_id' => $this->category->id, 'price' => 100]);
        $table = RestaurantTable::factory()->create(['user_id' => 1]);

        $orderId = $this->postJson('/api/v1/restaurant-orders', [
            'order_type' => Order::TYPE_DINE_IN,
            'table_id' => $table->id,
            'pos_id' => $this->pos->id,
            'lines' => [
                ['item_id' => $item->id, 'qty' => 1],
                ['item_id' => $item->id, 'qty' => 2],
            ],
        ])->json('data.id');

        $this->postJson("/api/v1/kds/orders/{$orderId}/bump")->assertStatus(200);

        $statuses = OrderLine::where('order_id', $orderId)->pluck('line_status')->unique()->all();
        $this->assertEquals([OrderLine::LINE_SERVED], $statuses);
    }
}
