<?php

namespace Tests\Feature\API\v1\pos;

use App\Models\Employees\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class AuthenticationControllerTest extends TestCase
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

    public function test_can_get_users_with_discount_role(): void
    {
        Role::factory()->create([
            'discounts' => true,
            'name' => 'Manager',
        ]);

        User::factory()->create([
            'role_id' => Role::where('discounts', true)->first()->id,
        ]);

        Passport::actingAs($this->user);

        $response = $this->getJson('/api/v1/authentications/roles?role=discounts');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => ['users'],
        ]);
    }

    public function test_can_get_users_with_delete_items_role(): void
    {
        Role::factory()->create([
            'delete_items' => true,
            'name' => 'Supervisor',
        ]);

        User::factory()->create([
            'role_id' => Role::where('delete_items', true)->first()->id,
        ]);

        Passport::actingAs($this->user);

        $response = $this->getJson('/api/v1/authentications/roles?role=delete_items');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => ['users'],
        ]);
    }

    public function test_can_get_users_with_refund_role(): void
    {
        Role::factory()->create([
            'rfnd' => true,
            'name' => 'Cashier Lead',
        ]);

        User::factory()->create([
            'role_id' => Role::where('rfnd', true)->first()->id,
        ]);

        Passport::actingAs($this->user);

        $response = $this->getJson('/api/v1/authentications/roles?role=rfnd');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => ['users'],
        ]);
    }

    public function test_role_parameter_is_required(): void
    {
        Passport::actingAs($this->user);

        $response = $this->getJson('/api/v1/authentications/roles');

        $response->assertStatus(422);
    }

    public function test_unauthenticated_user_cannot_access_authentication(): void
    {
        $response = $this->getJson('/api/v1/authentications/roles?role=discounts');

        $response->assertStatus(401);
    }
}
