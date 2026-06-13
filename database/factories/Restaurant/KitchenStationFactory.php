<?php

namespace Database\Factories\Restaurant;

use App\Models\Restaurant\KitchenStation;
use Illuminate\Database\Eloquent\Factories\Factory;

class KitchenStationFactory extends Factory
{
    protected $model = KitchenStation::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->randomElement(['Hot Kitchen', 'Cold Bar', 'Beverage', 'Grill']),
            'store_id' => null,
            'status' => true,
            'user_id' => 1,
        ];
    }
}
