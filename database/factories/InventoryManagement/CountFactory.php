<?php

namespace Database\Factories\InventoryManagement;

use App\Models\InventoryManagement\Count;
use App\Models\Settings\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InventoryManagement\Count>
 */
class CountFactory extends Factory
{
    protected $model = Count::class;

    public function definition(): array
    {
        return [
            'ic' => fake()->unique()->numberBetween(1000, 9999),
            'status' => 1,
            'total' => 0,
            'user_id' => 1,
            'created_by' => User::factory(),
            'store_id' => Store::factory(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 0,
        ]);
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 1,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 2,
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 3,
        ]);
    }
}
