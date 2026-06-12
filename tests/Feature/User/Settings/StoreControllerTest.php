<?php

namespace Tests\Feature\User\Settings;

use App\Models\Employees\Role;
use App\Models\Settings\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            'status' => true,
        ]);
    }

    public function test_can_view_stores_index(): void
    {
        $response = $this->actingAs($this->user)->get('/admin/stores');

        $response->assertOk();
    }

    public function test_can_view_create_store_form(): void
    {
        $response = $this->actingAs($this->user)->get('/admin/stores/create');

        $response->assertOk();
    }

    public function test_can_store_store(): void
    {
        $response = $this->actingAs($this->user)
            ->post('/admin/stores', [
                'name' => 'Test Store',
                'header' => 'Store Header',
                'footer' => 'Store Footer',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('stores', [
            'name' => 'Test Store',
            'status' => true,
        ]);
    }

    public function test_can_view_store(): void
    {
        $store = Store::factory()->create([
            'status' => true,
            'user_id' => $this->user->user_id,
        ]);

        $response = $this->actingAs($this->user)->get("/admin/stores/{$store->id}");

        $response->assertOk();
    }

    public function test_can_view_edit_store_form(): void
    {
        $store = Store::factory()->create([
            'status' => true,
            'user_id' => $this->user->user_id,
        ]);

        $response = $this->actingAs($this->user)->get("/admin/stores/{$store->id}/edit");

        $response->assertOk();
    }

    public function test_can_update_store(): void
    {
        $store = Store::factory()->create([
            'status' => true,
            'user_id' => $this->user->user_id,
        ]);

        $response = $this->actingAs($this->user)
            ->put("/admin/stores/{$store->id}", [
                'name' => 'Updated Store',
                'header' => 'Updated Header',
                'footer' => 'Updated Footer',
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('stores', [
            'id' => $store->id,
            'name' => 'Updated Store',
        ]);
    }

    public function test_can_delete_store(): void
    {
        $store = Store::factory()->create([
            'status' => true,
            'user_id' => $this->user->user_id,
        ]);

        $response = $this->actingAs($this->user)
            ->delete("/admin/stores/{$store->id}");

        $response->assertRedirect();

        $this->assertDatabaseHas('stores', [
            'id' => $store->id,
            'status' => false,
        ]);
    }

    public function test_can_get_stores_table_data(): void
    {
        Store::factory()->count(5)->create([
            'status' => true,
            'user_id' => $this->user->user_id,
        ]);

        $response = $this->actingAs($this->user)
            ->get('/admin/stores/table');

        $response->assertStatus(200);
    }

    public function test_can_select_stores(): void
    {
        Store::factory()->count(3)->create([
            'status' => true,
            'user_id' => $this->user->user_id,
        ]);

        $response = $this->actingAs($this->user)
            ->get('/admin/stores/select?term=');

        $response->assertStatus(200);
    }

    public function test_unauthenticated_user_cannot_access_stores(): void
    {
        $response = $this->get('/admin/stores');

        $response->assertRedirect('/admin/login');
    }
}
