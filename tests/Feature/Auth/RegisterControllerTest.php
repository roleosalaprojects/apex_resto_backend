<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Public registration was removed in the Tranche-1 security fix. The
 * tests in this file used to exercise the open registration form; they
 * now exercise the opposite invariant — that those endpoints are gone.
 *
 * First-time admin/superadmin provisioning is covered by:
 *   - tests/Feature/Console/ApexCreateAdminTest.php
 *   - tests/Feature/Console/ApexCreateSuperAdminTest.php
 */
class RegisterControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_register_get_is_no_longer_routed(): void
    {
        $response = $this->get('/admin/register');

        $this->assertContains($response->status(), [404, 405], 'GET /admin/register must not resolve to a route now that public registration is disabled.');
    }

    public function test_admin_register_post_is_no_longer_routed(): void
    {
        $response = $this->post('/admin/register', [
            'name' => 'Anyone',
            'email' => 'anyone@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $this->assertContains($response->status(), [404, 405], 'POST /admin/register must not resolve to a route now that public registration is disabled.');
        $this->assertDatabaseMissing('users', ['email' => 'anyone@example.com']);
    }

    public function test_api_pos_register_is_no_longer_routed(): void
    {
        $response = $this->postJson('/api/v1/register', [
            'name' => 'Anyone',
            'email' => 'anyone-pos@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $this->assertContains($response->status(), [404, 405]);
        $this->assertDatabaseMissing('users', ['email' => 'anyone-pos@example.com']);
    }

    public function test_image_updater_register_is_no_longer_routed(): void
    {
        $response = $this->postJson('/api/v1/image-updater/register', [
            'name' => 'Anyone',
            'email' => 'anyone-iu@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $this->assertContains($response->status(), [404, 405]);
        $this->assertDatabaseMissing('users', ['email' => 'anyone-iu@example.com']);
    }

    public function test_superadmin_register_get_is_no_longer_routed(): void
    {
        $response = $this->get('/superadmin/register');

        $this->assertContains($response->status(), [404, 405]);
    }

    public function test_superadmin_register_post_is_no_longer_routed(): void
    {
        $response = $this->post('/superadmin/register', [
            'name' => 'Anyone',
            'email' => 'anyone-sa@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $this->assertContains($response->status(), [404, 405]);
        $this->assertDatabaseMissing('admins', ['email' => 'anyone-sa@example.com']);
    }

    public function test_fix_database_maintenance_route_is_no_longer_routed(): void
    {
        $response = $this->get('/fix/database');

        $this->assertContains($response->status(), [404, 405], 'GET /fix/database (REPAIR TABLE) must not be publicly reachable.');
    }
}
