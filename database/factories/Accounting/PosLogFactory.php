<?php

namespace Database\Factories\Accounting;

use App\Models\Accounting\PosLog;
use App\Models\Settings\Pos;
use App\Models\Settings\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Accounting\PosLog>
 */
class PosLogFactory extends Factory
{
    protected $model = PosLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cash_in' => 0,
            'rendered' => 0,
            'cash_out' => 0,
            'type' => $this->faker->numberBetween(1, 14),
            'reason' => null,
            'so_id' => null,
            'pos_id' => Pos::factory(),
            'store_id' => Store::factory(),
            'user_id' => User::factory(),
        ];
    }

    public function cashIn(float $amount = 1000): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 4,
            'cash_in' => $amount,
            'reason' => 'Starting cash',
        ]);
    }

    public function cashOut(float $amount = 500): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 12,
            'cash_out' => $amount,
            'reason' => 'Cash pickup',
        ]);
    }

    public function sale(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 5,
            'cash_in' => $this->faker->randomFloat(2, 50, 5000),
            'rendered' => $this->faker->randomFloat(2, 0, 500),
        ]);
    }

    public function login(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 1,
        ]);
    }
}
