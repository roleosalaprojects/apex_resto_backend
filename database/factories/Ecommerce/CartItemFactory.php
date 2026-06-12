<?php

namespace Database\Factories\Ecommerce;

use App\Models\Ecommerce\Cart;
use App\Models\Ecommerce\CartItem;
use App\Models\Products\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Ecommerce\CartItem>
 */
class CartItemFactory extends Factory
{
    protected $model = CartItem::class;

    public function definition(): array
    {
        return [
            'cart_id' => Cart::factory(),
            'item_id' => Item::factory(),
            'qty' => fake()->numberBetween(1, 10),
            'price' => fake()->randomFloat(2, 10, 500),
        ];
    }
}
