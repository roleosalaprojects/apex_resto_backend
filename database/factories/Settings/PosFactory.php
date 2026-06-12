<?php

namespace Database\Factories\Settings;

use App\Models\Settings\Pos;
use App\Models\Settings\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Settings\Pos>
 */
class PosFactory extends Factory
{
    protected $model = Pos::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'POS '.$this->faker->numberBetween(1, 100),
            'store_id' => Store::factory(),
            'status' => true,
            'mac' => $this->faker->macAddress(),
            'number' => $this->faker->numerify('###'),
            'user_id' => 1,
            'serial' => $this->faker->uuid(),
            'min' => $this->faker->numerify('MIN-######'),
            'ptu' => $this->faker->numerify('PTU-######'),
            'issued' => now()->subYear(),
            'expiry' => now()->addYears(4),
            'type' => 0,
            'reset_counter' => 0,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => false,
        ]);
    }
}
