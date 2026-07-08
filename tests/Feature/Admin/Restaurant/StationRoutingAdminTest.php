<?php

namespace Tests\Feature\Admin\Restaurant;

use App\Models\Employees\Role;
use App\Models\Products\Category;
use App\Models\Restaurant\KitchenStation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StationRoutingAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private KitchenStation $station;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::factory()->admin()->create();
        $this->admin = User::factory()->create(['role_id' => $role->id, 'user_id' => 1]);
        $this->station = KitchenStation::factory()->create([
            'user_id' => 1,
            'name' => 'Hot Kitchen',
        ]);
    }

    public function test_category_store_persists_kitchen_station(): void
    {
        $this->actingAs($this->admin)
            ->post(route('categories.store'), [
                'name' => 'MAINS',
                'kitchen_station_id' => $this->station->id,
            ])
            ->assertOk();

        $this->assertEquals(
            $this->station->id,
            Category::where('name', 'MAINS')->firstOrFail()->kitchen_station_id,
        );
    }

    public function test_category_update_can_change_and_clear_the_station(): void
    {
        $category = Category::factory()->create([
            'user_id' => 1,
            'status' => true,
            'kitchen_station_id' => $this->station->id,
        ]);

        $this->actingAs($this->admin)
            ->put(route('categories.update', $category), [
                'name' => $category->name,
                'kitchen_station_id' => null,
            ])
            ->assertOk();

        $this->assertNull($category->fresh()->kitchen_station_id);
    }

    public function test_category_get_exposes_the_station_for_the_edit_modal(): void
    {
        $category = Category::factory()->create([
            'user_id' => 1,
            'status' => true,
            'kitchen_station_id' => $this->station->id,
        ]);

        $this->actingAs($this->admin)
            ->getJson(route('category.get', $category))
            ->assertOk()
            ->assertJsonPath('kitchen_station_id', $this->station->id);
    }

    public function test_rejects_a_station_that_does_not_exist(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('categories.store'), [
                'name' => 'DRINKS',
                'kitchen_station_id' => 9999,
            ]);

        $response->assertSessionHasErrors('kitchen_station_id');
    }
}
