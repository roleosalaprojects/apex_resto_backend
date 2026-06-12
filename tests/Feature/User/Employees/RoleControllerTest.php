<?php

namespace Tests\Feature\User\Employees;

use App\Models\Employees\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleControllerTest extends TestCase
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

    public function test_can_view_roles_index(): void
    {
        $response = $this->actingAs($this->user)->get('/admin/roles');

        $response->assertOk();
    }

    public function test_can_view_create_role_form(): void
    {
        $response = $this->actingAs($this->user)->get('/admin/roles/create');

        $response->assertOk();
    }

    public function test_can_store_role(): void
    {
        $response = $this->actingAs($this->user)
            ->post('/admin/roles', [
                'name' => 'Cashier',
                'pos' => 1,
                'bck_offc' => false,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('roles', [
            'name' => 'Cashier',
        ]);
    }

    public function test_can_view_role(): void
    {
        $role = Role::factory()->create([
            'status' => true,
            'user_id' => $this->user->user_id,
        ]);

        $response = $this->actingAs($this->user)->get("/admin/roles/{$role->id}");

        $response->assertOk();
    }

    public function test_can_view_edit_role_form(): void
    {
        $role = Role::factory()->create([
            'status' => true,
            'user_id' => $this->user->user_id,
        ]);

        $response = $this->actingAs($this->user)->get("/admin/roles/{$role->id}/edit");

        $response->assertOk();
    }

    public function test_can_update_role(): void
    {
        $role = Role::factory()->create([
            'status' => true,
            'user_id' => $this->user->user_id,
        ]);

        $response = $this->actingAs($this->user)
            ->put("/admin/roles/{$role->id}", [
                'name' => 'Updated Role',
                'pos' => 2,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'name' => 'Updated Role',
        ]);
    }

    public function test_can_delete_role(): void
    {
        $role = Role::factory()->create([
            'status' => true,
            'user_id' => $this->user->user_id,
        ]);

        $response = $this->actingAs($this->user)
            ->delete("/admin/roles/{$role->id}");

        $response->assertRedirect();

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'status' => false,
        ]);
    }

    public function test_store_saves_csh_out_permission(): void
    {
        $response = $this->actingAs($this->user)
            ->post('/admin/roles', [
                'name' => 'Cash Out Role',
                'csh_out' => 1,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('roles', [
            'name' => 'CASH OUT ROLE',
            'csh_out' => true,
        ]);
    }

    public function test_update_saves_csh_out_permission(): void
    {
        $role = Role::factory()->create([
            'status' => true,
            'user_id' => $this->user->user_id,
            'csh_out' => false,
        ]);

        $response = $this->actingAs($this->user)
            ->put("/admin/roles/{$role->id}", [
                'name' => $role->name,
                'csh_out' => 1,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('roles', [
            'id' => $role->id,
            'csh_out' => true,
        ]);
    }

    public function test_unauthenticated_user_cannot_access_roles(): void
    {
        $response = $this->get('/admin/roles');

        $response->assertRedirect('/admin/login');
    }
}
