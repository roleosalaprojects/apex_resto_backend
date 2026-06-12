<?php

namespace App\Models\Settings;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BrandingSetting extends Model
{
    /** @use HasFactory<\Database\Factories\Settings\BrandingSettingFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'palette_key',
        'logo_path',
        'brand_name',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function palette(): BelongsTo
    {
        return $this->belongsTo(ColorPalette::class, 'palette_key', 'key');
    }
}
