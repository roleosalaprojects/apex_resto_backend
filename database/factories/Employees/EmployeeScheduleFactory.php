<?php

namespace Database\Factories\Employees;

use App\Models\Employees\EmployeeSchedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeSchedule>
 */
class EmployeeScheduleFactory extends Factory
{
    protected $model = EmployeeSchedule::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'day_of_week' => fake()->numberBetween(0, 6),
            'start_time' => '08:00',
        ];
    }

    public function forDay(int $dayOfWeek): static
    {
        return $this->state(fn (array $attributes) => [
            'day_of_week' => $dayOfWeek,
        ]);
    }

    public function startingAt(string $time): static
    {
        return $this->state(fn (array $attributes) => [
            'start_time' => $time,
        ]);
    }

    public function restDay(): static
    {
        return $this->state(fn (array $attributes) => [
            'start_time' => null,
        ]);
    }
}
