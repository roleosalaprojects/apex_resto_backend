<?php

namespace Tests\Feature\Admin\Restaurant;

use App\Models\Employees\Role;
use App\Models\Pos\Order;
use App\Models\Restaurant\RestaurantTable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FloorplanTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::factory()->admin()->create();
        $this->admin = User::factory()->create(['role_id' => $role->id, 'user_id' => 1]);
    }

    public function test_floorplan_page_renders_for_restaurant_role(): void
    {
        $this->actingAs($this->admin)
            ->get(route('restaurant-tables.floorplan'))
            ->assertOk()
            ->assertSee('Floorplan');
    }

    public function test_floorplan_data_groups_tables_with_open_orders(): void
    {
        $table = RestaurantTable::factory()->create([
            'user_id' => 1,
            'name' => 'M1',
            'area' => 'Main Hall',
            'status' => RestaurantTable::STATUS_OCCUPIED,
        ]);
        RestaurantTable::factory()->create([
            'user_id' => 1,
            'name' => 'P1',
            'area' => null,
        ]);
        // Foreign tenant's table stays invisible.
        RestaurantTable::factory()->create(['user_id' => 99, 'name' => 'ZZ']);

        Order::create([
            'reference' => 'REF123', 'qty' => 1, 'amount' => 340,
            'user_id' => 1, 'status' => Order::STATUS_PREPARING,
            'order_type' => Order::TYPE_DINE_IN, 'table_id' => $table->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson(route('restaurant-tables.floorplan-data'))
            ->assertOk();

        $tables = collect($response->json('tables'));
        $this->assertCount(2, $tables);

        $m1 = $tables->firstWhere('name', 'M1');
        $this->assertSame('Main Hall', $m1['area']);
        $this->assertSame('REF123', $m1['open_order']['reference']);
        $this->assertEquals(340, $m1['open_order']['amount']);

        // Area-less tables land in "Main"; no open order → null.
        $p1 = $tables->firstWhere('name', 'P1');
        $this->assertSame('Main', $p1['area']);
        $this->assertNull($p1['open_order']);
    }

    public function test_floorplan_denied_without_restaurant_permission(): void
    {
        $role = Role::factory()->create(['rstrnt' => false, 'user_id' => 1]);
        $user = User::factory()->create(['role_id' => $role->id, 'user_id' => 1]);

        $this->actingAs($user)
            ->get(route('restaurant-tables.floorplan'))
            ->assertRedirect('/home');
    }
}
