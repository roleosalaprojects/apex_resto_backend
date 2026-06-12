<?php

namespace Database\Factories\Employees;

use App\Models\Employees\Shift;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Employees\Shift>
 */
class ShiftFactory extends Factory
{
    protected $model = Shift::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $clockIn = fake()->dateTimeBetween('-1 week', 'now');

        return [
            'user_id' => User::factory(),
            'pos_id' => null,
            'store_id' => null,
            'clock_in' => $clockIn,
            'clock_out' => null,
            'starting_cash' => fake()->randomFloat(2, 1000, 5000),
            'ending_cash' => null,
            'expected_cash' => null,
            'cash_difference' => null,
            'notes' => null,
            'status' => 'active',
        ];
    }

    public function completed(): static
    {
        return $this->state(function (array $attributes) {
            $clockIn = $attributes['clock_in'];
            $clockOut = fake()->dateTimeBetween($clockIn, '+8 hours');
            $endingCash = fake()->randomFloat(2, 5000, 20000);
            $expectedCash = $endingCash + fake()->randomFloat(2, -100, 100);

            return [
                'clock_out' => $clockOut,
                'ending_cash' => $endingCash,
                'expected_cash' => $expectedCash,
                'cash_difference' => $endingCash - $expectedCash,
                'status' => 'completed',
            ];
        });
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
            'notes' => 'Shift cancelled',
        ]);
    }
}
