<?php

namespace Tests\Feature\API\v1\mobile;

use App\Models\Employees\Role;
use App\Models\Products\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
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
        ]);
    }

    public function test_can_list_units(): void
    {
        Unit::factory()->count(3)->create(['status' => true, 'user_id' => $this->user->user_id]);

        Passport::actingAs($this->user);

        $response = $this->getJson('/api/v1/mobile/units');

        $response->assertStatus(200);
    }

    public function test_can_get_units(): void
    {
        Unit::factory()->count(3)->create(['status' => true, 'user_id' => $this->user->user_id]);

        Passport::actingAs($this->user);

        $response = $this->getJson('/api/v1/mobile/units/get');

        $response->assertStatus(200);
    }

    public function test_can_create_unit(): void
    {
        Passport::actingAs($this->user);

        $response = $this->postJson('/api/v1/mobile/units', [
            'name' => 'Box',
        ]);

        $response->assertStatus(200);
    }

    public function test_can_show_unit(): void
    {
        $unit = Unit::factory()->create(['status' => true, 'user_id' => $this->user->user_id]);

        Passport::actingAs($this->user);

        $response = $this->getJson("/api/v1/mobile/units/{$unit->id}");

        $response->assertStatus(200);
    }

    public function test_can_update_unit(): void
    {
        $unit = Unit::factory()->create(['status' => true, 'user_id' => $this->user->user_id]);

        Passport::actingAs($this->user);

        $response = $this->putJson("/api/v1/mobile/units/{$unit->id}", [
            'name' => 'Updated Unit',
        ]);

        $response->assertStatus(200);
    }

    public function test_can_delete_unit(): void
    {
        $unit = Unit::factory()->create(['status' => true, 'user_id' => $this->user->user_id]);

        Passport::actingAs($this->user);

        $response = $this->deleteJson("/api/v1/mobile/units/{$unit->id}");

        $response->assertStatus(200);
    }

    public function test_unauthenticated_user_cannot_access_units(): void
    {
        $response = $this->getJson('/api/v1/mobile/units');

        $response->assertStatus(401);
    }
}
