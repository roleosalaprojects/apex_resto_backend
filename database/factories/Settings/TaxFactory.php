<?php

namespace Database\Factories\Settings;

use App\Models\Settings\Tax;
use Illuminate\Database\Eloquent\Factories\Factory;

class TaxFactory extends Factory
{
    protected $model = Tax::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->word().' Tax',
            'rate' => $this->faker->randomFloat(2, 0, 25),
            'status' => true,
            'user_id' => 1,
        ];
    }

    public function vat(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'VAT',
            'rate' => 12.00,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => false,
        ]);
    }
}
