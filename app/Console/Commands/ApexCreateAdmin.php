<?php

namespace App\Console\Commands;

use App\Models\Employees\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class ApexCreateAdmin extends Command
{
    protected $signature = 'apex:create-admin
        {--name= : Display name for the admin user}
        {--email= : Login email}
        {--password= : Password (min 8 chars). If omitted you will be prompted.}
        {--force : Reset the password if a user with this email already exists}';

    protected $description = 'Create the first /admin tenant-owner account. Replaces the old public /admin/register flow. Subsequent staff are created via the Employees module.';

    public function handle(): int
    {
        $name = (string) ($this->option('name') ?? $this->ask('Name'));
        $email = (string) ($this->option('email') ?? $this->ask('Email'));
        $password = (string) ($this->option('password') ?? $this->secret('Password (min 8 chars)'));

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password],
            [
                'name' => 'required|string|max:255',
                'email' => 'required|email|max:255',
                'password' => 'required|string|min:8',
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $existing = User::query()->where('email', $email)->first();

        if ($existing !== null && ! $this->option('force')) {
            $this->error("A user with email {$email} already exists (id={$existing->id}). Pass --force to reset the password (will not change role or tenant).");

            return self::FAILURE;
        }

        DB::transaction(function () use ($name, $email, $password, $existing): void {
            if ($existing !== null) {
                $existing->forceFill([
                    'name' => $name,
                    'password' => Hash::make($password),
                ])->save();
                $this->info("Reset password for user #{$existing->id} ({$email}). Role and tenant unchanged.");

                return;
            }

            $adminRole = Role::query()->where('name', 'Admin')->first();

            if ($adminRole === null) {
                $adminRole = Role::create(array_merge(
                    ['name' => 'Admin', 'status' => true, 'user_id' => 1],
                    Role::fullAccessFlags(),
                ));
                $this->line("Created 'Admin' role (id={$adminRole->id}) with full access flags.");
            }

            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'role_id' => $adminRole->id,
                'status' => 1,
                'is_customer' => 0,
            ]);

            // Tenant owner: user_id points at self so all per-tenant
            // scoping (auth()->user()->user_id) resolves to this user.
            $user->forceFill(['user_id' => $user->id])->save();

            $this->info("Created tenant-owner admin #{$user->id} ({$email}). user_id={$user->id} (self).");
        });

        return self::SUCCESS;
    }
}
