<?php

namespace Database\Factories\Reports;

use App\Models\Reports\ReportRecipient;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ReportRecipient>
 */
class ReportRecipientFactory extends Factory
{
    protected $model = ReportRecipient::class;

    public function definition(): array
    {
        return [
            'user_id' => 1,
            'email' => fake()->safeEmail(),
            'report_type' => fake()->randomElement(['daily', 'weekly', 'both']),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function daily(): static
    {
        return $this->state(fn (array $attributes) => [
            'report_type' => 'daily',
        ]);
    }

    public function weekly(): static
    {
        return $this->state(fn (array $attributes) => [
            'report_type' => 'weekly',
        ]);
    }
}
