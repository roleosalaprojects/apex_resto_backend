<?php

namespace Tests\Feature\User;

use App\Models\Employees\Employee;
use App\Models\Employees\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileControllerTest extends TestCase
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
        Employee::create([
            'phone' => '09171234567',
            'address' => 'Test Address',
            'status' => true,
            'user_id' => $this->user->id,
        ]);
    }

    public function test_can_view_profile(): void
    {
        $response = $this->actingAs($this->user)->get('/admin/profile');

        $response->assertOk();
    }

    public function test_can_update_profile(): void
    {
        $response = $this->actingAs($this->user)
            ->post('/admin/profile', [
                'name' => 'Updated Name',
                'email' => $this->user->email,
            ]);

        $response->assertRedirect();
    }

    public function test_can_update_password(): void
    {
        $response = $this->actingAs($this->user)
            ->post('/admin/profile/new_password', [
                'password' => 'newpassword123',
                'password_confirmation' => 'newpassword123',
            ]);

        $response->assertRedirect();

        $this->user->refresh();
        $this->assertTrue(Hash::check('newpassword123', $this->user->password));
    }

    public function test_unauthenticated_user_cannot_access_profile(): void
    {
        $response = $this->get('/admin/profile');

        $response->assertRedirect('/admin/login');
    }
}
