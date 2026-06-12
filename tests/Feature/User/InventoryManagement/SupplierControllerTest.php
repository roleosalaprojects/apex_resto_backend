<?php

namespace Tests\Feature\User\InventoryManagement;

use App\Models\Employees\Role;
use App\Models\InventoryManagement\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            'status' => true,
        ]);
    }

    public function test_can_view_suppliers_index(): void
    {
        $response = $this->actingAs($this->user)->get('/admin/suppliers');

        $response->assertOk();
    }

    public function test_can_view_create_supplier_form(): void
    {
        $response = $this->actingAs($this->user)->get('/admin/suppliers/create');

        $response->assertOk();
    }

    public function test_can_store_supplier(): void
    {
        $response = $this->actingAs($this->user)
            ->post('/admin/suppliers', [
                'name' => 'Test Supplier',
                'contact' => 'John Doe',
                'number' => '09123456789',
                'email' => 'supplier@example.com',
                'address' => '123 Main St',
                'city' => 'Manila',
                'zip' => '1000',
                'province' => 'Metro Manila',
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('suppliers', [
            'name' => 'Test Supplier',
            'status' => true,
        ]);
    }

    public function test_can_view_supplier(): void
    {
        $supplier = Supplier::factory()->create([
            'status' => true,
            'user_id' => $this->user->user_id,
        ]);

        $response = $this->actingAs($this->user)->get("/admin/suppliers/{$supplier->id}");

        $response->assertOk();
    }

    public function test_can_view_edit_supplier_form(): void
    {
        $supplier = Supplier::factory()->create([
            'status' => true,
            'user_id' => $this->user->user_id,
        ]);

        $response = $this->actingAs($this->user)->get("/admin/suppliers/{$supplier->id}/edit");

        $response->assertOk();
    }

    public function test_can_update_supplier(): void
    {
        $supplier = Supplier::factory()->create([
            'status' => true,
            'user_id' => $this->user->user_id,
        ]);

        $response = $this->actingAs($this->user)
            ->put("/admin/suppliers/{$supplier->id}", [
                'name' => 'Updated Supplier',
                'contact' => 'Jane Doe',
                'number' => '09987654321',
                'address' => '456 New St',
                'city' => 'Quezon City',
                'zip' => '1100',
                'province' => 'Metro Manila',
            ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('suppliers', [
            'id' => $supplier->id,
            'name' => 'Updated Supplier',
        ]);
    }

    public function test_can_delete_supplier(): void
    {
        $supplier = Supplier::factory()->create([
            'status' => true,
            'user_id' => $this->user->user_id,
        ]);

        $response = $this->actingAs($this->user)
            ->delete("/admin/suppliers/{$supplier->id}");

        $response->assertRedirect();

        $this->assertDatabaseHas('suppliers', [
            'id' => $supplier->id,
            'status' => false,
        ]);
    }

    public function test_can_get_suppliers_table_data(): void
    {
        Supplier::factory()->count(5)->create([
            'status' => true,
            'user_id' => $this->user->user_id,
        ]);

        $response = $this->actingAs($this->user)
            ->get('/admin/suppliers/table');

        $response->assertStatus(200);
    }

    public function test_can_select_suppliers(): void
    {
        Supplier::factory()->count(3)->create([
            'status' => true,
            'user_id' => $this->user->user_id,
        ]);

        $response = $this->actingAs($this->user)
            ->get('/admin/suppliers/select?term=');

        $response->assertStatus(200);
    }

    public function test_unauthenticated_user_cannot_access_suppliers(): void
    {
        $response = $this->get('/admin/suppliers');

        $response->assertRedirect('/admin/login');
    }
}
