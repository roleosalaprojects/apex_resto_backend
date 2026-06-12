<?php

namespace Database\Factories\InventoryManagement;

use App\Models\InventoryManagement\Count;
use App\Models\InventoryManagement\CountLine;
use App\Models\Products\Item;
use App\Models\Products\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InventoryManagement\CountLine>
 */
class CountLineFactory extends Factory
{
    protected $model = CountLine::class;

    public function definition(): array
    {
        return [
            'item_id' => Item::factory(),
            'unit_id' => 0,
            'count_id' => Count::factory(),
            'counted_qty' => null,
        ];
    }

    public function counted(float $qty = 10.00): static
    {
        return $this->state(fn (array $attributes) => [
            'counted_qty' => $qty,
        ]);
    }

    public function withUnit(): static
    {
        return $this->state(fn (array $attributes) => [
            'unit_id' => Unit::factory(),
        ]);
    }
}
