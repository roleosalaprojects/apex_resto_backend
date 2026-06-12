<?php

namespace App\Models\Settings;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ColorPalette extends Model
{
    /** @use HasFactory<\Database\Factories\Settings\ColorPaletteFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'key',
        'label',
        'primary',
        'secondary',
        'accent',
        'on_primary',
        'on_secondary',
        'is_default',
        'is_active',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
