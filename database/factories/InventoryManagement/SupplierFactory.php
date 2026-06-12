<?php

namespace Database\Factories\InventoryManagement;

use App\Models\InventoryManagement\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'tin' => $this->faker->numerify('###-###-###-###'),
            'contact' => $this->faker->name(),
            'number' => $this->faker->phoneNumber(),
            'email' => $this->faker->companyEmail(),
            'address' => $this->faker->streetAddress(),
            'city' => $this->faker->city(),
            'zip' => $this->faker->postcode(),
            'province' => $this->faker->state(),
            'note' => $this->faker->sentence(),
            'status' => true,
            'user_id' => 1,
            'country' => $this->faker->country(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => false,
        ]);
    }
}
