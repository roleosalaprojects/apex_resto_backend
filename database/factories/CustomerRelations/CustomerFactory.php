<?php

namespace Database\Factories\CustomerRelations;

use App\Models\CustomerRelations\Customer;
use App\Models\Employees\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CustomerRelations\Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'code' => 'CUST-'.strtoupper(Str::random(8)),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '09'.fake()->numerify('#########'),
            'address' => fake()->address(),
            'city' => fake()->city(),
            'zip' => fake()->postcode(),
            'province' => fake()->state(),
            'country' => fake()->country(),
            'password' => 'password',
            'status' => true,
            'user_id' => 0,
            'email_verified_at' => now(),
            'terms_accepted_at' => now(),
            'points' => fake()->randomFloat(2, 0, 1000),
            'accumulated_points' => fake()->randomFloat(2, 0, 5000),
            'is_wholesale' => false,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => false,
        ]);
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function withoutPassword(): static
    {
        return $this->state(fn (array $attributes) => [
            'password' => null,
        ]);
    }

    public function wholesale(): static
    {
        return $this->state(function (array $attributes) {
            $role = Role::factory()->admin()->create();
            $user = User::factory()->create(['role_id' => $role->id]);

            return [
                'is_wholesale' => true,
                'wholesale_approved_at' => now(),
                'wholesale_approved_by' => $user->id,
            ];
        });
    }
}
