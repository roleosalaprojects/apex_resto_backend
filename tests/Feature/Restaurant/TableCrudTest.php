<?php

namespace Tests\Feature\Restaurant;

use App\Models\Employees\Role;
use App\Models\Restaurant\RestaurantTable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TableCrudTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::factory()->admin()->create();
        $this->user = User::factory()->create([
            'role_id' => $role->id,
            'user_id' => 1,
            'status' => true,
        ]);
    }

    public function test_index_is_accessible(): void
    {
        $this->actingAs($this->user)
            ->get(route('restaurant-tables.index'))
            ->assertOk();
    }

    public function test_can_create_table(): void
    {
        $this->actingAs($this->user)
            ->post(route('restaurant-tables.store'), [
                'name' => 'Table 1',
                'number' => '1',
                'area' => 'Patio',
                'seats' => 4,
            ])
            ->assertRedirect(route('restaurant-tables.index'));

        $this->assertDatabaseHas('restaurant_tables', [
            'name' => 'Table 1',
            'seats' => 4,
            'user_id' => 1,
        ]);
    }

    public function test_can_update_table(): void
    {
        $table = RestaurantTable::factory()->create(['user_id' => 1, 'seats' => 2]);

        $this->actingAs($this->user)
            ->put(route('restaurant-tables.update', $table), [
                'name' => $table->name,
                'seats' => 8,
                'status' => RestaurantTable::STATUS_INACTIVE,
            ])
            ->assertRedirect(route('restaurant-tables.index'));

        $this->assertEquals(8, $table->fresh()->seats);
        $this->assertEquals(RestaurantTable::STATUS_INACTIVE, $table->fresh()->status);
    }

    public function test_can_delete_table(): void
    {
        $table = RestaurantTable::factory()->create(['user_id' => 1]);

        $this->actingAs($this->user)
            ->delete(route('restaurant-tables.destroy', $table))
            ->assertRedirect(route('restaurant-tables.index'));

        $this->assertSoftDeleted('restaurant_tables', ['id' => $table->id]);
    }

    public function test_datatable_is_tenant_scoped(): void
    {
        RestaurantTable::factory()->create(['user_id' => 1, 'name' => 'Mine']);
        RestaurantTable::factory()->create(['user_id' => 999, 'name' => 'Theirs']);

        $response = $this->actingAs($this->user)
            ->get(route('restaurant-tables.table'));

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Mine', $data[0]['name']);
    }
}
