<?php

namespace Tests\Feature\Admin;

use App\Models\Employees\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavbarPollingConfigTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::factory()->admin()->create();
        $this->admin = User::factory()->create(['role_id' => $role->id]);
        $this->admin->update(['user_id' => $this->admin->id]);
    }

    public function test_navbar_poll_intervals_come_from_config(): void
    {
        config([
            'notifications.order_feed_poll_ms' => 45000,
            'notifications.access_request_poll_ms' => 7000,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.home'));

        $response->assertOk();
        $response->assertSee('const POLL_INTERVAL_MS = 45000;', false);
        $response->assertSee('const POLL_INTERVAL_MS = 7000;', false);
        $response->assertSee('Polling every 45s', false);
        $response->assertSee('Polling every 7s', false);
    }

    public function test_navbar_poll_intervals_default_values(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.home'));

        $response->assertOk();
        $response->assertSee('const POLL_INTERVAL_MS = 10000;', false);
        $response->assertSee('const POLL_INTERVAL_MS = 3000;', false);
    }
}
