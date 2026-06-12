<?php

namespace Tests\Feature\Auth;

use App\Models\Employees\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginControllerTest extends TestCase
{
    use RefreshDatabase;

    protected Role $role;

    protected function setUp(): void
    {
        parent::setUp();

        $this->role = Role::factory()->admin()->create();
    }

    public function test_login_page_is_accessible(): void
    {
        $response = $this->get('/admin/login');

        $response->assertStatus(200);
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => bcrypt('password'),
            'role_id' => $this->role->id,
            'status' => true,
        ]);

        $response = $this->post('/admin/login', [
            'email' => 'user@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect('/admin/home');
        $this->assertAuthenticatedAs($user);
    }

    public function test_user_cannot_login_with_invalid_email(): void
    {
        User::factory()->create([
            'email' => 'user@example.com',
            'password' => bcrypt('password'),
            'role_id' => $this->role->id,
        ]);

        $response = $this->post('/admin/login', [
            'email' => 'wrong@example.com',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_user_cannot_login_with_invalid_password(): void
    {
        User::factory()->create([
            'email' => 'user@example.com',
            'password' => bcrypt('password'),
            'role_id' => $this->role->id,
        ]);

        $response = $this->post('/admin/login', [
            'email' => 'user@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create([
            'role_id' => $this->role->id,
        ]);

        $this->actingAs($user);

        $response = $this->post('/admin/logout');

        $response->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_authenticated_user_is_redirected_from_login_page(): void
    {
        $user = User::factory()->create([
            'role_id' => $this->role->id,
        ]);

        $this->actingAs($user);

        $response = $this->get('/admin/login');

        // Authenticated users are redirected to superadmin
        $response->assertRedirect('/superadmin');
    }
}
