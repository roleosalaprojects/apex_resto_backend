<?php

namespace Database\Factories\Settings;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Settings\ColorPalette>
 */
class ColorPaletteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => Str::slug($this->faker->unique()->words(2, true), '_'),
            'label' => ucfirst($this->faker->words(2, true)),
            'primary' => $this->hex(),
            'secondary' => $this->hex(),
            'accent' => $this->hex(),
            'on_primary' => '#ffffff',
            'on_secondary' => '#ffffff',
            'is_default' => false,
            'is_active' => true,
            'sort_order' => $this->faker->numberBetween(0, 100),
        ];
    }

    public function default(): static
    {
        return $this->state(fn () => ['is_default' => true]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    private function hex(): string
    {
        return sprintf('#%06x', $this->faker->numberBetween(0, 0xFFFFFF));
    }
}
