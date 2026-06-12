<?php

namespace Database\Factories\InventoryManagement;

use App\Models\InventoryManagement\Purchase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InventoryManagement\Purchase>
 */
class PurchaseFactory extends Factory
{
    protected $model = Purchase::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'po' => 'PO-'.fake()->numerify('######'),
            'supplier_id' => 1,
            'store_id' => 1,
            'user_id' => 1,
            'total' => fake()->randomFloat(2, 100, 10000),
            'items' => fake()->numberBetween(1, 20),
            'received' => 0,
            'status' => 0,
            'created_by' => 1,
            'note' => fake()->sentence(),
        ];
    }

    public function received(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 1,
            'received' => $attributes['items'] ?? 1,
        ]);
    }
}
