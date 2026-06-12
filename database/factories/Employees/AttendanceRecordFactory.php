<?php

namespace Database\Factories\Employees;

use App\Models\Employees\AttendanceRecord;
use App\Models\Settings\Store;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Employees\AttendanceRecord>
 */
class AttendanceRecordFactory extends Factory
{
    protected $model = AttendanceRecord::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $date = fake()->dateTimeBetween('-30 days', 'now');
        $timeIn = Carbon::parse($date)->setTime(8, 0);
        $timeOut = Carbon::parse($date)->setTime(17, 0);
        $hoursRendered = round($timeIn->diffInMinutes($timeOut) / 60, 2);

        return [
            'user_id' => User::factory(),
            'store_id' => Store::factory(),
            'date' => $date,
            'time_in' => $timeIn,
            'time_out' => $timeOut,
            'hours_rendered' => $hoursRendered,
            'status' => 'present',
            'remarks' => fake()->optional()->sentence(),
        ];
    }

    public function present(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'present',
        ]);
    }

    public function absent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'absent',
            'time_in' => null,
            'time_out' => null,
            'hours_rendered' => 0,
        ]);
    }

    public function forDate(Carbon $date): static
    {
        return $this->state(fn (array $attributes) => [
            'date' => $date->toDateString(),
            'time_in' => $date->copy()->setTime(8, 0),
            'time_out' => $date->copy()->setTime(17, 0),
        ]);
    }

    public function late(int $minutes = 30): static
    {
        return $this->state(fn (array $attributes) => [
            'is_late' => true,
            'late_minutes' => $minutes,
        ]);
    }

    public function onTime(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_late' => false,
            'late_minutes' => 0,
        ]);
    }
}
