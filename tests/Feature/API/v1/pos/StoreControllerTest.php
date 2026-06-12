<?php

namespace Tests\Feature\API\v1\pos;

use App\Models\Employees\Role;
use App\Models\Settings\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class StoreControllerTest extends TestCase
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

    public function test_can_list_stores(): void
    {
        Store::factory()->count(3)->create(['status' => true]);

        Passport::actingAs($this->user);

        $response = $this->getJson('/api/v1/stores');

        $response->assertStatus(200);
    }

    public function test_can_show_store(): void
    {
        $store = Store::factory()->create(['status' => true]);

        Passport::actingAs($this->user);

        $response = $this->getJson("/api/v1/stores/{$store->id}");

        $response->assertStatus(200);
    }

    public function test_unauthenticated_user_cannot_access_stores(): void
    {
        $response = $this->getJson('/api/v1/stores');

        $response->assertStatus(401);
    }
}
