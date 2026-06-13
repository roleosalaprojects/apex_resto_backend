<?php

namespace Database\Factories\Restaurant;

use App\Models\Restaurant\RestaurantTable;
use Illuminate\Database\Eloquent\Factories\Factory;

class RestaurantTableFactory extends Factory
{
    protected $model = RestaurantTable::class;

    public function definition(): array
    {
        return [
            'name' => 'Table '.$this->faker->unique()->numberBetween(1, 999),
            'number' => (string) $this->faker->numberBetween(1, 99),
            'area' => $this->faker->randomElement(['Main Hall', 'Patio', 'Second Floor']),
            'seats' => $this->faker->randomElement([2, 4, 6, 8]),
            'status' => RestaurantTable::STATUS_AVAILABLE,
            'store_id' => null,
            'user_id' => 1,
        ];
    }

    public function occupied(): static
    {
        return $this->state(fn () => ['status' => RestaurantTable::STATUS_OCCUPIED]);
    }
}
