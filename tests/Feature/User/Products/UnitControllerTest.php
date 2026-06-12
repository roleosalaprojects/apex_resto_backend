<?php

namespace Tests\Feature\User\Products;

use App\Models\Employees\Role;
use App\Models\Products\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnitControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Role $role;

    protected function setUp(): void
    {
        parent::setUp();

        $this->role = Role::factory()->admin()->create();
        $this->user = User::factory()->create([
            'role_id' => $this->role->id,
            'user_id' => 1,
            'status' => true,
        ]);
    }

    public function test_can_view_units_index(): void
    {
        $response = $this->actingAs($this->user)->get('/admin/units');

        $response->assertOk();
    }

    public function test_can_store_unit(): void
    {
        $response = $this->actingAs($this->user)
            ->post('/admin/units', [
                'name' => 'Box',
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('units', [
            'name' => 'Box',
            'status' => true,
        ]);
    }

    public function test_can_view_unit(): void
    {
        $unit = Unit::factory()->create([
            'status' => true,
            'user_id' => $this->user->user_id,
        ]);

        $response = $this->actingAs($this->user)->get("/admin/units/{$unit->id}");

        $response->assertOk();
    }

    public function test_can_update_unit(): void
    {
        $unit = Unit::factory()->create([
            'status' => true,
            'user_id' => $this->user->user_id,
        ]);

        $response = $this->actingAs($this->user)
            ->put("/admin/units/{$unit->id}", [
                'name' => 'Updated Unit',
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('units', [
            'id' => $unit->id,
            'name' => 'Updated Unit',
        ]);
    }

    public function test_can_delete_unit(): void
    {
        $unit = Unit::factory()->create([
            'status' => true,
            'user_id' => $this->user->user_id,
        ]);

        $response = $this->actingAs($this->user)
            ->delete("/admin/units/{$unit->id}");

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('units', [
            'id' => $unit->id,
            'status' => false,
        ]);
    }

    public function test_can_get_units_table_data(): void
    {
        Unit::factory()->count(5)->create([
            'status' => true,
            'user_id' => $this->user->user_id,
        ]);

        $response = $this->actingAs($this->user)
            ->get('/admin/units/table');

        $response->assertStatus(200);
    }

    public function test_can_select_units(): void
    {
        Unit::factory()->count(3)->create([
            'status' => true,
            'user_id' => $this->user->user_id,
        ]);

        $response = $this->actingAs($this->user)
            ->get('/admin/units/select?term=');

        $response->assertStatus(200);
    }

    public function test_unauthenticated_user_cannot_access_units(): void
    {
        $response = $this->get('/admin/units');

        $response->assertRedirect('/admin/login');
    }
}
