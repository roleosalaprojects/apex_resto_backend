<?php

namespace Database\Factories\Ecommerce;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Ecommerce\ShopAnnouncement>
 */
class ShopAnnouncementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'media_path' => 'shop_announcements/test-image.jpg',
            'media_type' => 'image',
            'link_url' => fake()->url(),
            'link_text' => 'Shop Now',
            'position' => fake()->randomElement(['hero', 'banner', 'popup']),
            'display_order' => fake()->numberBetween(1, 100),
            'is_active' => true,
            'starts_at' => null,
            'ends_at' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function hero(): static
    {
        return $this->state(fn (array $attributes) => [
            'position' => 'hero',
        ]);
    }

    public function banner(): static
    {
        return $this->state(fn (array $attributes) => [
            'position' => 'banner',
        ]);
    }

    public function video(): static
    {
        return $this->state(fn (array $attributes) => [
            'media_type' => 'video',
            'media_path' => 'shop_announcements/test-video.mp4',
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addWeek(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_at' => now()->subMonth(),
            'ends_at' => now()->subWeek(),
        ]);
    }

    public function future(): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_at' => now()->addWeek(),
            'ends_at' => now()->addMonth(),
        ]);
    }
}
