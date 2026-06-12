<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_login_page_is_accessible(): void
    {
        $response = $this->get('/superadmin/login');

        $response->assertStatus(200);
    }

    public function test_admin_can_login_with_valid_credentials(): void
    {
        $admin = Admin::factory()->create([
            'email' => 'superadmin@example.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->post('/superadmin/login', [
            'email' => 'superadmin@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect('/superadmin');
        $this->assertAuthenticatedAs($admin, 'superadmin');
    }

    public function test_admin_cannot_login_with_invalid_credentials(): void
    {
        Admin::factory()->create([
            'email' => 'superadmin@example.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->post('/superadmin/login', [
            'email' => 'superadmin@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertSessionHasErrors();
        $this->assertGuest('superadmin');
    }

    public function test_admin_can_logout(): void
    {
        $admin = Admin::factory()->create();

        $this->actingAs($admin, 'superadmin');

        $response = $this->get('/superadmin/logout');

        $response->assertRedirect();
        $this->assertGuest('superadmin');
    }

    public function test_unauthenticated_admin_cannot_access_dashboard(): void
    {
        $response = $this->get('/superadmin');

        $response->assertRedirect('/admin/login');
    }
}
