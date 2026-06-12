<?php

namespace Tests\Feature\Console;

use App\Models\Admin;
use App\Models\Pos\Receipt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ApexCreateSuperAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_a_superadmin_and_default_receipt(): void
    {
        $this->artisan('apex:create-superadmin', [
            '--name' => 'Owner',
            '--email' => 'owner@example.com',
            '--password' => 'supersecret123',
        ])->assertExitCode(0);

        $admin = Admin::where('email', 'owner@example.com')->first();
        $this->assertNotNull($admin);
        $this->assertTrue(Hash::check('supersecret123', $admin->password));

        $this->assertDatabaseHas('receipts', [
            'user_id' => $admin->id,
            'display' => false,
        ]);
    }

    public function test_refuses_to_recreate_existing_email_without_force(): void
    {
        Admin::create([
            'name' => 'Existing',
            'email' => 'owner@example.com',
            'password' => Hash::make('oldpassword1'),
        ]);

        $this->artisan('apex:create-superadmin', [
            '--name' => 'Replacement',
            '--email' => 'owner@example.com',
            '--password' => 'newpassword12',
        ])->assertExitCode(1);

        $admin = Admin::where('email', 'owner@example.com')->first();
        $this->assertTrue(Hash::check('oldpassword1', $admin->password));
        $this->assertSame('Existing', $admin->name);
    }

    public function test_force_resets_password_on_existing_admin(): void
    {
        $existing = Admin::create([
            'name' => 'Existing',
            'email' => 'owner@example.com',
            'password' => Hash::make('oldpassword1'),
        ]);

        $this->artisan('apex:create-superadmin', [
            '--name' => 'Updated Name',
            '--email' => 'owner@example.com',
            '--password' => 'newpassword12',
            '--force' => true,
        ])->assertExitCode(0);

        $admin = $existing->fresh();
        $this->assertTrue(Hash::check('newpassword12', $admin->password));
        $this->assertSame('Updated Name', $admin->name);

        // Should not create a second receipt for the same admin.
        $this->assertSame(0, Receipt::where('user_id', $admin->id)->count());
    }

    public function test_rejects_short_password(): void
    {
        $this->artisan('apex:create-superadmin', [
            '--name' => 'Owner',
            '--email' => 'owner@example.com',
            '--password' => 'short',
        ])->assertExitCode(1);

        $this->assertDatabaseMissing('admins', ['email' => 'owner@example.com']);
    }

    public function test_rejects_invalid_email(): void
    {
        $this->artisan('apex:create-superadmin', [
            '--name' => 'Owner',
            '--email' => 'not-an-email',
            '--password' => 'supersecret123',
        ])->assertExitCode(1);

        $this->assertSame(0, Admin::count());
    }
}
