<?php

namespace Database\Factories\Products;

use App\Models\Products\Category;
use App\Models\Products\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

class ItemFactory extends Factory
{
    protected $model = Item::class;

    public function definition(): array
    {
        $cost = $this->faker->randomFloat(2, 10, 500);
        $markup = $this->faker->numberBetween(10, 50);
        $price = $cost * (1 + $markup / 100);

        return [
            'barcode' => $this->faker->unique()->ean13(),
            'name' => strtoupper($this->faker->words(3, true)),
            'category_id' => Category::factory(),
            'vatable' => $this->faker->boolean(80),
            'tax_id' => null,
            'markup' => $markup,
            'cost' => $cost,
            'prev_cost' => $cost,
            'price' => $price,
            'prev_price' => $price,
            'status' => true,
            'user_id' => 1,
            'pwd' => $this->faker->randomFloat(2, 0, 20),
            'senior' => $this->faker->randomFloat(2, 0, 20),
            'solo_parent' => $this->faker->randomFloat(2, 0, 20),
            'naac' => $this->faker->randomFloat(2, 0, 20),
            'supplier_id' => null,
            'discountable' => $this->faker->boolean(70),
            'type' => $this->faker->numberBetween(0, 1),
            'creditable_to_points' => $this->faker->boolean(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => false,
        ]);
    }

    public function perPiece(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 0,
        ]);
    }

    public function perWeight(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 1,
        ]);
    }
}
