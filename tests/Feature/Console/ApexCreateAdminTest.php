<?php

namespace Tests\Feature\Console;

use App\Models\Employees\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ApexCreateAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_admin_role_when_missing_and_user_with_self_referencing_user_id(): void
    {
        $this->assertSame(0, Role::count());

        $this->artisan('apex:create-admin', [
            '--name' => 'Tenant Owner',
            '--email' => 'owner@apex.test',
            '--password' => 'supersecret123',
        ])->assertExitCode(0);

        $role = Role::where('name', 'Admin')->first();
        $this->assertNotNull($role);
        // Spot-check that the full-access flag set was applied.
        $this->assertTrue((bool) $role->sttngs);
        $this->assertTrue((bool) $role->sls);
        $this->assertTrue((bool) $role->bnkng);

        $user = User::where('email', 'owner@apex.test')->first();
        $this->assertNotNull($user);
        $this->assertSame($role->id, $user->role_id);
        $this->assertSame($user->id, $user->user_id, 'Tenant owner must have user_id === id.');
        $this->assertSame(0, (int) $user->is_customer);
        $this->assertTrue(Hash::check('supersecret123', $user->password));
    }

    public function test_reuses_existing_admin_role_on_second_invocation(): void
    {
        $existingRole = Role::create(array_merge(
            ['name' => 'Admin', 'status' => true, 'user_id' => 1],
            Role::fullAccessFlags(),
        ));

        $this->artisan('apex:create-admin', [
            '--name' => 'Second Owner',
            '--email' => 'second@apex.test',
            '--password' => 'supersecret123',
        ])->assertExitCode(0);

        $this->assertSame(1, Role::where('name', 'Admin')->count(), 'Admin role must not be duplicated.');

        $user = User::where('email', 'second@apex.test')->first();
        $this->assertNotNull($user);
        $this->assertSame($existingRole->id, $user->role_id);
    }

    public function test_refuses_to_recreate_existing_email_without_force(): void
    {
        $existing = User::create([
            'name' => 'Existing',
            'email' => 'owner@apex.test',
            'password' => Hash::make('oldpassword1'),
            'is_customer' => 0,
        ]);
        $existing->forceFill(['user_id' => $existing->id])->save();

        $this->artisan('apex:create-admin', [
            '--name' => 'Replacement',
            '--email' => 'owner@apex.test',
            '--password' => 'newpassword12',
        ])->assertExitCode(1);

        $user = $existing->fresh();
        $this->assertTrue(Hash::check('oldpassword1', $user->password));
        $this->assertSame('Existing', $user->name);
    }

    public function test_force_resets_password_on_existing_user_without_changing_role_or_tenant(): void
    {
        $role = Role::factory()->create(['name' => 'Some Other Role']);
        $existing = User::create([
            'name' => 'Existing',
            'email' => 'owner@apex.test',
            'password' => Hash::make('oldpassword1'),
            'role_id' => $role->id,
            'is_customer' => 0,
        ]);
        $existing->forceFill(['user_id' => 42])->save();

        $this->artisan('apex:create-admin', [
            '--name' => 'Updated Name',
            '--email' => 'owner@apex.test',
            '--password' => 'newpassword12',
            '--force' => true,
        ])->assertExitCode(0);

        $user = $existing->fresh();
        $this->assertTrue(Hash::check('newpassword12', $user->password));
        $this->assertSame('Updated Name', $user->name);
        $this->assertSame($role->id, $user->role_id, 'Role must be preserved on password reset.');
        $this->assertSame(42, $user->user_id, 'Tenant scope must be preserved on password reset.');
    }

    public function test_rejects_short_password(): void
    {
        $this->artisan('apex:create-admin', [
            '--name' => 'Owner',
            '--email' => 'owner@apex.test',
            '--password' => 'short',
        ])->assertExitCode(1);

        $this->assertDatabaseMissing('users', ['email' => 'owner@apex.test']);
    }

    public function test_rejects_invalid_email(): void
    {
        $this->artisan('apex:create-admin', [
            '--name' => 'Owner',
            '--email' => 'not-an-email',
            '--password' => 'supersecret123',
        ])->assertExitCode(1);

        $this->assertSame(0, User::count());
    }
}
