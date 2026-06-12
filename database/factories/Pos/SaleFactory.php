<?php

namespace Database\Factories\Pos;

use App\Models\Pos\Sale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Pos\Sale>
 */
class SaleFactory extends Factory
{
    protected $model = Sale::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $total = fake()->randomFloat(2, 50, 5000);
        $cash = $total + fake()->randomFloat(2, 0, 100);
        $vatable = $total / 1.12;
        $vat = $total - $vatable;

        return [
            'son' => fake()->unique()->numerify('SON-######'),
            'counter' => fake()->numerify('###'),
            'total' => $total,
            'cash' => $cash,
            'change' => $cash - $total,
            'vatable' => round($vatable, 2),
            'vat' => round($vat, 2),
            'non_vat' => 0,
            'zero_rated' => 0,
            'type' => 0,
            'sales_by' => 1,
            'pos_id' => 1,
            'store_id' => 1,
            'user_id' => 1,
            'cancelled' => false,
            'profit' => fake()->randomFloat(2, 10, 500),
        ];
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'cancelled' => true,
        ]);
    }

    public function forCustomer(int $customerId): static
    {
        return $this->state(fn (array $attributes) => [
            'customer_id' => $customerId,
        ]);
    }
}
