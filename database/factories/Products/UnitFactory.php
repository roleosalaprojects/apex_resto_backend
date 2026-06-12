<?php

namespace Database\Factories\Products;

use App\Models\Products\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

class UnitFactory extends Factory
{
    protected $model = Unit::class;

    public function definition(): array
    {
        $units = ['Box', 'Pack', 'Case', 'Dozen', 'Bundle', 'Carton'];

        return [
            'name' => $this->faker->randomElement($units).' '.$this->faker->randomNumber(2),
            'status' => true,
            'user_id' => 1,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => false,
        ]);
    }
}
