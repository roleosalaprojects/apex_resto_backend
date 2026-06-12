<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Employees\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $admin;

    protected Role $role;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::factory()->create();
        $this->role = Role::factory()->admin()->create();
    }

    public function test_admin_can_view_users_list(): void
    {
        $response = $this->actingAs($this->admin, 'superadmin')
            ->get('/superadmin/admin');

        $response->assertOk();
    }

    public function test_admin_can_activate_user(): void
    {
        $user = User::factory()->create([
            'role_id' => $this->role->id,
            'status' => false,
        ]);

        $response = $this->actingAs($this->admin, 'superadmin')
            ->post("/superadmin/user/{$user->id}/activate");

        $response->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'status' => true,
        ]);
    }

    public function test_unauthenticated_admin_cannot_access_users(): void
    {
        $response = $this->get('/superadmin/admin');

        $response->assertRedirect('/admin/login');
    }
}
