<?php

namespace Database\Factories;

use App\Models\ItemInsight;
use App\Models\Products\Item;
use App\Models\Settings\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

class ItemInsightFactory extends Factory
{
    protected $model = ItemInsight::class;

    public function definition(): array
    {
        $score = $this->faker->randomFloat(2, 10, 95);

        return [
            'user_id' => 1,
            'store_id' => Store::factory(),
            'insight_date' => now()->toDateString(),
            'item_id' => Item::factory(),
            'rank' => $this->faker->numberBetween(1, 100),
            'sellability_score' => $score,
            'score_breakdown' => [
                'volume' => $this->faker->randomFloat(2, 0, 30),
                'trend' => $this->faker->randomFloat(2, 0, 20),
                'margin' => $this->faker->randomFloat(2, 0, 15),
                'consistency' => $this->faker->randomFloat(2, 0, 10),
                'stock_readiness' => $this->faker->randomFloat(2, 0, 10),
                'seasonal' => $this->faker->randomFloat(2, 0, 10),
                'weather' => $this->faker->randomFloat(2, 0, 5),
            ],
            'ai_insight' => $this->faker->sentence(),
            'predicted_qty' => $this->faker->randomFloat(2, 1, 100),
            'current_stock' => $this->faker->randomFloat(2, 0, 500),
            'profit_margin' => $this->faker->randomFloat(2, 5, 60),
            'category_name' => $this->faker->word(),
            'factors' => $this->faker->randomElements(
                ['trending_up', 'high_margin', 'weekend_boost', 'payday_boost', 'holiday_boost', 'consistent_seller', 'low_stock_risk'],
                $this->faker->numberBetween(1, 3)
            ),
        ];
    }

    public function withoutAi(): static
    {
        return $this->state(fn (array $attributes) => [
            'ai_insight' => null,
        ]);
    }

    public function forDate(string $date): static
    {
        return $this->state(fn (array $attributes) => [
            'insight_date' => $date,
        ]);
    }
}
