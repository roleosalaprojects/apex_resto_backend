<?php

namespace App\Console\Commands;

use App\Models\Admin;
use App\Models\Pos\Receipt;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class ApexCreateSuperAdmin extends Command
{
    protected $signature = 'apex:create-superadmin
        {--name= : Display name for the superadmin}
        {--email= : Login email for the superadmin}
        {--password= : Password (min 8 chars). If omitted you will be prompted.}
        {--force : Replace the password if an admin with this email already exists}';

    protected $description = 'Create the first /superadmin panel account. Replaces the old public /superadmin/register flow.';

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

        $existing = Admin::query()->where('email', $email)->first();

        if ($existing !== null && ! $this->option('force')) {
            $this->error("An admin with email {$email} already exists (id={$existing->id}). Pass --force to reset the password.");

            return self::FAILURE;
        }

        DB::transaction(function () use ($name, $email, $password, $existing): void {
            if ($existing !== null) {
                $existing->forceFill([
                    'name' => $name,
                    'password' => Hash::make($password),
                ])->save();
                $this->info("Reset password for superadmin #{$existing->id} ({$email}).");

                return;
            }

            $admin = Admin::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
            ]);

            Receipt::create([
                'header' => '',
                'vat_reg' => false,
                'footer' => '',
                'tin' => '',
                'points' => 0.00001,
                'name' => '',
                'email' => '',
                'phone' => '',
                'ptu' => '',
                'accredition' => '',
                'display' => false,
                'user_id' => $admin->id,
            ]);

            $this->info("Created superadmin #{$admin->id} ({$email}).");
        });

        return self::SUCCESS;
    }
}
