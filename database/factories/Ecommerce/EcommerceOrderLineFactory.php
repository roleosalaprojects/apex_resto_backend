<?php

namespace Database\Factories\Ecommerce;

use App\Models\Ecommerce\EcommerceOrder;
use App\Models\Ecommerce\EcommerceOrderLine;
use App\Models\Products\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Ecommerce\EcommerceOrderLine>
 */
class EcommerceOrderLineFactory extends Factory
{
    protected $model = EcommerceOrderLine::class;

    public function definition(): array
    {
        $qty = fake()->numberBetween(1, 10);
        $price = fake()->randomFloat(2, 10, 500);

        return [
            'ecommerce_order_id' => EcommerceOrder::factory(),
            'item_id' => Item::factory(),
            'item_name' => strtoupper(fake()->words(3, true)),
            'qty' => $qty,
            'price' => $price,
            'sub_total' => round($qty * $price, 2),
        ];
    }
}
