<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    protected Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::factory()->create();
    }

    public function test_admin_can_access_dashboard(): void
    {
        $response = $this->actingAs($this->admin, 'superadmin')
            ->get('/superadmin');

        $response->assertOk();
    }

    public function test_unauthenticated_user_cannot_access_dashboard(): void
    {
        $response = $this->get('/superadmin');

        $response->assertRedirect('/admin/login');
    }
}
