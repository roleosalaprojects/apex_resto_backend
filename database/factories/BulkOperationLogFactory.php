<?php

namespace Database\Factories;

use App\Models\BulkOperationLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BulkOperationLog>
 */
class BulkOperationLogFactory extends Factory
{
    protected $model = BulkOperationLog::class;

    public function definition(): array
    {
        $total = $this->faker->numberBetween(10, 100);
        $processed = $this->faker->numberBetween(0, $total);
        $success = $this->faker->numberBetween(0, $processed);
        $failed = $processed - $success;

        return [
            'type' => $this->faker->randomElement(['price_update', 'category_update', 'import']),
            'user_id' => User::factory(),
            'total_records' => $total,
            'processed_records' => $processed,
            'success_records' => $success,
            'failed_records' => $failed,
            'status' => 'pending',
            'errors' => null,
            'started_at' => null,
            'completed_at' => null,
        ];
    }

    public function priceUpdate(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'price_update',
        ]);
    }

    public function categoryUpdate(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'category_update',
        ]);
    }

    public function import(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'import',
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'processed_records' => 0,
            'success_records' => 0,
            'failed_records' => 0,
            'started_at' => null,
            'completed_at' => null,
        ]);
    }

    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'processing',
            'started_at' => now(),
            'completed_at' => null,
        ]);
    }

    public function completed(): static
    {
        return $this->state(function (array $attributes) {
            $total = $attributes['total_records'];

            return [
                'status' => 'completed',
                'processed_records' => $total,
                'success_records' => $total,
                'failed_records' => 0,
                'started_at' => now()->subMinutes(5),
                'completed_at' => now(),
            ];
        });
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'started_at' => now()->subMinutes(5),
            'completed_at' => now(),
            'errors' => [['message' => 'An error occurred']],
        ]);
    }
}
