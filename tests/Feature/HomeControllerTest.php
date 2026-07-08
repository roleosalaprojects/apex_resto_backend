<?php

namespace Tests\Feature;

use App\Models\Employees\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeControllerTest extends TestCase
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

    public function test_root_redirects_to_the_shop_landing_page(): void
    {
        $response = $this->get('/');

        $response->assertStatus(301);
        $response->assertRedirect('/shop');

        $this->get('/shop')->assertStatus(200);
    }

    public function test_home_page_requires_authentication(): void
    {
        $response = $this->get('/admin/home');

        $response->assertRedirect('/admin/login');
    }

    public function test_authenticated_user_can_access_home(): void
    {
        $response = $this->actingAs($this->user)->get('/admin/home');

        $response->assertOk();
    }

    public function test_can_get_dashboard_default_data(): void
    {
        $response = $this->actingAs($this->user)
            ->get('/admin/dashboard/default');

        $response->assertStatus(200);
    }
}
