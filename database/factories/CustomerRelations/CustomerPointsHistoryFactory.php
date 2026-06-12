<?php

namespace Database\Factories\CustomerRelations;

use App\Models\CustomerRelations\CustomerPointsHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CustomerRelations\CustomerPointsHistory>
 */
class CustomerPointsHistoryFactory extends Factory
{
    protected $model = CustomerPointsHistory::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(['earned', 'redeemed', 'expired', 'adjusted']);
        $points = match ($type) {
            'earned', 'adjusted' => fake()->randomFloat(2, 5, 100),
            'redeemed', 'expired' => -fake()->randomFloat(2, 5, 50),
        };

        return [
            'customer_id' => 1,
            'type' => $type,
            'points' => $points,
            'balance_after' => fake()->randomFloat(2, 0, 500),
            'reference_type' => $type === 'adjusted' ? 'manual' : 'sale',
            'reference_id' => $type !== 'adjusted' ? fake()->numberBetween(1, 1000) : null,
            'reference_number' => $type !== 'adjusted' ? 'SON-'.fake()->numerify('######') : null,
            'description' => match ($type) {
                'earned' => 'Points earned from purchase',
                'redeemed' => 'Points redeemed for discount',
                'expired' => 'Points expired due to inactivity',
                'adjusted' => 'Manual adjustment',
            },
            'store_id' => 1,
            'user_id' => 1,
        ];
    }

    public function earned(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'earned',
            'points' => fake()->randomFloat(2, 5, 100),
            'reference_type' => 'sale',
            'description' => 'Points earned from purchase',
        ]);
    }

    public function redeemed(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'redeemed',
            'points' => -fake()->randomFloat(2, 5, 50),
            'reference_type' => 'sale',
            'description' => 'Points redeemed for discount',
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'expired',
            'points' => -fake()->randomFloat(2, 5, 50),
            'reference_type' => 'system',
            'reference_id' => null,
            'reference_number' => null,
            'description' => 'Points expired due to inactivity',
        ]);
    }

    public function adjusted(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'adjusted',
            'points' => fake()->randomFloat(2, -50, 100),
            'reference_type' => 'manual',
            'reference_id' => null,
            'reference_number' => null,
            'description' => 'Manual adjustment',
        ]);
    }
}
