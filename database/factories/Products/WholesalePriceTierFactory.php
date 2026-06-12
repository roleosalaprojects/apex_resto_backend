<?php

namespace Database\Factories\Products;

use App\Models\Products\Item;
use App\Models\Products\WholesalePriceTier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Products\WholesalePriceTier>
 */
class WholesalePriceTierFactory extends Factory
{
    protected $model = WholesalePriceTier::class;

    public function definition(): array
    {
        return [
            'item_id' => Item::factory(),
            'min_qty' => $this->faker->numberBetween(1, 100),
            'discount' => $this->faker->randomFloat(2, 1, 50),
        ];
    }
}
