<?php

namespace Database\Factories\Accounting;

use App\Models\Accounting\Expense;
use App\Models\Settings\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Accounting\Expense>
 */
class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference_number' => 'EXP-'.$this->faker->unique()->numerify('########'),
            'store_id' => Store::factory(),
            'payee' => $this->faker->company(),
            'amount' => $this->faker->randomFloat(2, 100, 5000),
            'expense_date' => $this->faker->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'status' => Expense::STATUS_ACTIVE,
        ];
    }

    public function voided(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => Expense::STATUS_VOIDED,
            'voided_at' => now(),
            'void_reason' => 'Voided in test',
        ]);
    }
}
