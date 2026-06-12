<?php

namespace Database\Seeders;

use App\Models\Settings\ColorPalette;
use Illuminate\Database\Seeder;

class ColorPaletteSeeder extends Seeder
{
    public function run(): void
    {
        $palettes = [
            [
                'key' => 'apex_default',
                'label' => 'Apex Blue (default)',
                'primary' => '#1858fd',
                'secondary' => '#1652ea',
                'accent' => '#f6a623',
                'on_primary' => '#ffffff',
                'on_secondary' => '#ffffff',
                'is_default' => true,
                'sort_order' => 0,
            ],
            [
                'key' => 'ocean_breeze',
                'label' => 'Ocean Breeze',
                'primary' => '#0ea5e9',
                'secondary' => '#0284c7',
                'accent' => '#06b6d4',
                'on_primary' => '#ffffff',
                'on_secondary' => '#ffffff',
                'sort_order' => 10,
            ],
            [
                'key' => 'forest_green',
                'label' => 'Forest Green',
                'primary' => '#16a34a',
                'secondary' => '#15803d',
                'accent' => '#84cc16',
                'on_primary' => '#ffffff',
                'on_secondary' => '#ffffff',
                'sort_order' => 20,
            ],
            [
                'key' => 'sunset_coral',
                'label' => 'Sunset Coral',
                'primary' => '#f97316',
                'secondary' => '#ea580c',
                'accent' => '#fbbf24',
                'on_primary' => '#ffffff',
                'on_secondary' => '#ffffff',
                'sort_order' => 30,
            ],
            [
                'key' => 'royal_purple',
                'label' => 'Royal Purple',
                'primary' => '#7c3aed',
                'secondary' => '#6d28d9',
                'accent' => '#ec4899',
                'on_primary' => '#ffffff',
                'on_secondary' => '#ffffff',
                'sort_order' => 40,
            ],
            [
                'key' => 'monochrome_slate',
                'label' => 'Monochrome Slate',
                'primary' => '#334155',
                'secondary' => '#1e293b',
                'accent' => '#64748b',
                'on_primary' => '#ffffff',
                'on_secondary' => '#ffffff',
                'sort_order' => 50,
            ],
            [
                'key' => 'quick_basket_coral',
                'label' => 'Quick Basket Coral',
                'primary' => '#e11d48',
                'secondary' => '#be123c',
                'accent' => '#fb923c',
                'on_primary' => '#ffffff',
                'on_secondary' => '#ffffff',
                'sort_order' => 60,
            ],
            [
                'key' => 'midnight_indigo',
                'label' => 'Midnight Indigo',
                'primary' => '#4338ca',
                'secondary' => '#3730a3',
                'accent' => '#a78bfa',
                'on_primary' => '#ffffff',
                'on_secondary' => '#ffffff',
                'sort_order' => 70,
            ],
            [
                'key' => 'emerald_lagoon',
                'label' => 'Emerald Lagoon',
                'primary' => '#059669',
                'secondary' => '#047857',
                'accent' => '#14b8a6',
                'on_primary' => '#ffffff',
                'on_secondary' => '#ffffff',
                'sort_order' => 80,
            ],
            [
                'key' => 'rose_gold',
                'label' => 'Rose Gold',
                'primary' => '#db2777',
                'secondary' => '#9d174d',
                'accent' => '#f59e0b',
                'on_primary' => '#ffffff',
                'on_secondary' => '#ffffff',
                'sort_order' => 90,
            ],
        ];

        foreach ($palettes as $palette) {
            ColorPalette::updateOrCreate(
                ['key' => $palette['key']],
                array_merge(['is_active' => true, 'is_default' => false], $palette),
            );
        }
    }
}
