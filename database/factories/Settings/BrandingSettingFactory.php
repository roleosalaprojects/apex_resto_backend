<?php

namespace Database\Factories\Settings;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Settings\BrandingSetting>
 */
class BrandingSettingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => $this->faker->numberBetween(1, 1_000_000),
            'palette_key' => 'apex_default',
            'logo_path' => null,
            'brand_name' => null,
        ];
    }
}
