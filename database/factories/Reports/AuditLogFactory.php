<?php

namespace Database\Factories\Reports;

use App\Models\Reports\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AuditLog>
 */
class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'auditable_type' => 'App\Models\User',
            'auditable_id' => 1,
            'event' => fake()->randomElement(['created', 'updated', 'deleted']),
            'old_values' => null,
            'new_values' => ['name' => fake()->name()],
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'url' => fake()->url(),
        ];
    }

    public function created(): static
    {
        return $this->state(fn (array $attributes) => [
            'event' => 'created',
            'old_values' => null,
        ]);
    }

    public function updated(): static
    {
        return $this->state(fn (array $attributes) => [
            'event' => 'updated',
            'old_values' => ['name' => fake()->name()],
            'new_values' => ['name' => fake()->name()],
        ]);
    }

    public function deleted(): static
    {
        return $this->state(fn (array $attributes) => [
            'event' => 'deleted',
            'new_values' => null,
        ]);
    }
}
