<?php

namespace Database\Factories\Ecommerce;

use App\Models\CustomerRelations\Customer;
use App\Models\Ecommerce\EcommerceOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Ecommerce\EcommerceOrder>
 */
class EcommerceOrderFactory extends Factory
{
    protected $model = EcommerceOrder::class;

    public function definition(): array
    {
        return [
            // Mirror EcommerceOrder::generateReference() — 12 hex chars
            // from random_bytes — so fixtures look like real orders.
            'reference' => 'ECO-'.strtoupper(bin2hex(random_bytes(6))),
            'customer_id' => Customer::factory(),
            'total' => fake()->randomFloat(2, 100, 5000),
            'qty' => fake()->numberBetween(1, 20),
            'status' => 0,
        ];
    }

    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 1,
            'verified_by' => 1,
            'verified_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 2,
            'cancelled_by' => 1,
            'cancelled_at' => now(),
        ]);
    }
}
