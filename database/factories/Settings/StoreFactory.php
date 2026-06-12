<?php

namespace Database\Factories\Settings;

use App\Models\Settings\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

class StoreFactory extends Factory
{
    protected $model = Store::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'user_id' => 1,
            'status' => true,
            'header' => $this->faker->sentence(),
            'footer' => $this->faker->sentence(),
            'tin' => $this->faker->numerify('###-###-###-###'),
            'vat_reg' => $this->faker->boolean(),
            'phone' => $this->faker->phoneNumber(),
            'email' => $this->faker->companyEmail(),
            'counter' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => false,
        ]);
    }

    public function withLocation(): static
    {
        return $this->state(fn (array $attributes) => [
            'latitude' => $this->faker->randomFloat(7, 5.0, 19.5),
            'longitude' => $this->faker->randomFloat(7, 117.0, 127.0),
        ]);
    }
}
