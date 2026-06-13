<?php

namespace Database\Factories\Products;

use App\Models\Products\Item;
use App\Models\Products\ItemComponent;
use Illuminate\Database\Eloquent\Factories\Factory;

class ItemComponentFactory extends Factory
{
    protected $model = ItemComponent::class;

    public function definition(): array
    {
        return [
            'item_id' => Item::factory()->state(['is_composite' => true]),
            'component_item_id' => Item::factory(),
            'qty' => $this->faker->randomFloat(4, 1, 500),
            'notes' => null,
            'user_id' => 1,
        ];
    }
}
