<?php

namespace Tests\Feature\API\v1\mobile;

use App\Models\Employees\Role;
use App\Models\InventoryManagement\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class SupplierControllerTest extends TestCase
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

    public function test_can_list_suppliers(): void
    {
        Supplier::factory()->count(3)->create(['status' => true, 'user_id' => $this->user->user_id]);

        Passport::actingAs($this->user);

        $response = $this->getJson('/api/v1/mobile/suppliers');

        $response->assertStatus(200);
    }

    public function test_can_create_supplier(): void
    {
        Passport::actingAs($this->user);

        $response = $this->postJson('/api/v1/mobile/suppliers', [
            'name' => 'New Supplier',
            'contact' => 'John Doe',
            'number' => '09123456789',
            'email' => 'supplier@example.com',
            'address' => '123 Main St',
            'city' => 'Manila',
            'zip' => '1000',
            'province' => 'Metro Manila',
        ]);

        $response->assertStatus(201);
    }

    public function test_can_show_supplier(): void
    {
        $supplier = Supplier::factory()->create(['status' => true, 'user_id' => $this->user->user_id]);

        Passport::actingAs($this->user);

        $response = $this->getJson("/api/v1/mobile/suppliers/{$supplier->id}");

        $response->assertStatus(200);
    }

    public function test_can_update_supplier(): void
    {
        $supplier = Supplier::factory()->create(['status' => true, 'user_id' => $this->user->user_id]);

        Passport::actingAs($this->user);

        $response = $this->putJson("/api/v1/mobile/suppliers/{$supplier->id}", [
            'name' => 'Updated Supplier',
            'address' => '456 Updated St',
            'city' => 'Quezon City',
            'zip' => '1100',
            'province' => 'Metro Manila',
        ]);

        $response->assertStatus(200);
    }

    public function test_can_delete_supplier(): void
    {
        $supplier = Supplier::factory()->create(['status' => true, 'user_id' => $this->user->user_id]);

        Passport::actingAs($this->user);

        $response = $this->deleteJson("/api/v1/mobile/suppliers/{$supplier->id}");

        $response->assertStatus(200);
    }

    public function test_unauthenticated_user_cannot_access_suppliers(): void
    {
        $response = $this->getJson('/api/v1/mobile/suppliers');

        $response->assertStatus(401);
    }
}
