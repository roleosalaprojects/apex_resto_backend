<?php

namespace Database\Factories\Settings;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Settings\Advertisement>
 */
class AdvertisementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->sentence(3),
            'description' => fake()->sentence(10),
            'image' => 'media/advertisements/'.fake()->uuid().'.jpg',
            'media_type' => 'image',
            'duration' => fake()->numberBetween(5, 30),
            'status' => true,
            'display_order' => fake()->numberBetween(0, 10),
        ];
    }

    public function video(): static
    {
        return $this->state(fn (array $attributes) => [
            'media_type' => 'video',
            'image' => 'media/advertisements/'.fake()->uuid().'.mp4',
            'duration' => fake()->numberBetween(10, 300),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => false,
        ]);
    }
}
