<?php

namespace Database\Factories\Employees;

use App\Models\Employees\Shift;
use App\Models\Employees\ShiftBreak;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Employees\ShiftBreak>
 */
class ShiftBreakFactory extends Factory
{
    protected $model = ShiftBreak::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $breakStart = fake()->dateTimeBetween('-4 hours', 'now');

        return [
            'shift_id' => Shift::factory(),
            'break_start' => $breakStart,
            'break_end' => null,
            'type' => fake()->randomElement(['lunch', 'short', 'other']),
            'reason' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(function (array $attributes) {
            $breakStart = $attributes['break_start'];

            return [
                'break_end' => fake()->dateTimeBetween($breakStart, '+1 hour'),
            ];
        });
    }

    public function lunch(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'lunch',
        ]);
    }

    public function short(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'short',
        ]);
    }
}
