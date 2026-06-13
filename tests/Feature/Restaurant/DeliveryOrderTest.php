<?php

namespace Tests\Feature\Restaurant;

use App\Models\Employees\Role;
use App\Models\Pos\Order;
use App\Models\Pos\Sale;
use App\Models\Products\Category;
use App\Models\Products\Item;
use App\Models\Products\ItemStore;
use App\Models\Settings\Pos;
use App\Models\Settings\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class DeliveryOrderTest extends TestCase
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
            'name' => 'POS', 'store_id' => $this->store->id, 'status' => true,
            'mac' => '00:00:00:00:00:00', 'number' => 1, 'user_id' => $this->user->id, 'reset_counter' => 1,
        ]);
        $this->category = Category::factory()->create(['status' => true]);
    }

    public function test_delivery_order_captures_address_and_has_no_table(): void
    {
        Passport::actingAs($this->user);
        $item = Item::factory()->create(['category_id' => $this->category->id, 'price' => 200]);
        ItemStore::factory()->create(['item_id' => $item->id, 'store_id' => $this->store->id, 'stock' => 50]);

        $response = $this->postJson('/api/v1/restaurant-orders', [
            'order_type' => Order::TYPE_DELIVERY,
            'pos_id' => $this->pos->id,
            'guest_name' => 'Maria Santos',
            'delivery_address' => '123 Mabini St',
            'delivery_contact' => '09181234567',
            'lines' => [['item_id' => $item->id, 'qty' => 1]],
        ]);

        $response->assertStatus(201);
        $order = Order::first();
        $this->assertEquals(Order::TYPE_DELIVERY, $order->order_type);
        $this->assertNull($order->table_id);
        $this->assertEquals('123 Mabini St', $order->delivery_address);
    }

    public function test_take_out_order_settles_to_take_out_sale(): void
    {
        Passport::actingAs($this->user);
        $item = Item::factory()->create(['category_id' => $this->category->id, 'price' => 150]);
        ItemStore::factory()->create(['item_id' => $item->id, 'store_id' => $this->store->id, 'stock' => 50]);

        $orderId = $this->postJson('/api/v1/restaurant-orders', [
            'order_type' => Order::TYPE_TAKE_OUT,
            'pos_id' => $this->pos->id,
            'lines' => [['item_id' => $item->id, 'qty' => 2]],
        ])->json('data.id');

        $this->postJson("/api/v1/restaurant-orders/{$orderId}/settle", [
            'payment_type' => Sale::PAYMENT_CASH,
            'cash' => 300,
        ])->assertStatus(200)->assertJsonPath('data.total', 300);

        $this->assertEquals(Order::TYPE_TAKE_OUT, Sale::first()->order_type);
    }
}
