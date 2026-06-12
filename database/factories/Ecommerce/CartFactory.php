<?php

namespace Database\Factories\Ecommerce;

use App\Models\CustomerRelations\Customer;
use App\Models\Ecommerce\Cart;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Ecommerce\Cart>
 */
class CartFactory extends Factory
{
    protected $model = Cart::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
        ];
    }
}
