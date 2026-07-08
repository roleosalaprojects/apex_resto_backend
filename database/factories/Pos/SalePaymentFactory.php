<?php

namespace Database\Factories\Pos;

use App\Models\Pos\Sale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Pos\SalePayment>
 */
class SalePaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sales_id' => Sale::factory(),
            'payment_type' => Sale::PAYMENT_CASH,
            'amount' => $this->faker->randomFloat(2, 10, 500),
            'reference_number' => null,
            'bank_id' => null,
        ];
    }
}
