<?php

namespace Tests\Feature\Restaurant;

use App\Models\Pos\Sale;
use App\Models\Products\Item;
use App\Models\Restaurant\KitchenStation;
use App\Models\Restaurant\Reservation;
use App\Models\Restaurant\RestaurantTable;
use App\Models\Settings\Pos;
use Database\Seeders\RestaurantDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RestaurantDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_a_coherent_demo_tenant(): void
    {
        $this->seed(RestaurantDemoSeeder::class);

        $this->assertDatabaseHas('users', ['email' => 'demo-owner@apexresto.test']);
        $this->assertEquals(2, Pos::count());
        $this->assertEquals(3, KitchenStation::count());
        $this->assertEquals(12, RestaurantTable::count());
        $this->assertEquals(5, Reservation::count());

        $latte = Item::where('name', 'ICED LATTE')->first();
        $this->assertNotNull($latte);
        $this->assertTrue($latte->is_composite);
        $this->assertEquals(3, $latte->components()->count());
        // 50g ice*0.02 + 20g coffee*1.20 + 200ml oat*0.18 = 1 + 24 + 36 = 61
        $this->assertEquals(61.00, (float) $latte->cost);

        $sisig = Item::where('name', 'SISIG')->first();
        $this->assertTrue($sisig->is_composite);
        $this->assertEquals(5, $sisig->components()->count());
    }

    public function test_seeded_composite_can_be_sold_and_deducts_ingredients(): void
    {
        $this->seed(RestaurantDemoSeeder::class);

        $latte = Item::where('name', 'ICED LATTE')->first();
        $coffee = Item::where('name', 'COFFEE BEANS')->first();
        $store = Pos::first()->store_id;

        $before = (float) \App\Models\Products\ItemStore::where('item_id', $coffee->id)->where('store_id', $store)->value('stock');

        // Settle a take-out order through the restaurant flow.
        $role = \App\Models\Employees\Role::factory()->admin()->create();
        $user = \App\Models\User::factory()->create(['role_id' => $role->id, 'user_id' => $latte->user_id]);
        \Laravel\Passport\Passport::actingAs($user);

        $orderId = $this->postJson('/api/v1/restaurant-orders', [
            'order_type' => \App\Models\Pos\Order::TYPE_TAKE_OUT,
            'pos_id' => Pos::first()->id,
            'lines' => [['item_id' => $latte->id, 'qty' => 2]],
        ])->json('data.id');

        $this->postJson("/api/v1/restaurant-orders/{$orderId}/settle", [
            'payment_type' => Sale::PAYMENT_CASH,
            'cash' => 300,
        ])->assertStatus(200);

        // 20g coffee x 2 lattes deducted.
        $after = (float) \App\Models\Products\ItemStore::where('item_id', $coffee->id)->where('store_id', $store)->value('stock');
        $this->assertEquals($before - 40, $after);
    }
}
