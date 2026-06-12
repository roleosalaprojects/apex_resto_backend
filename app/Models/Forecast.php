<?php

namespace App\Models;

use App\Models\Products\Item;
use App\Models\Settings\Store;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Forecast extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'store_id',
        'item_id',
        'forecast_date',
        'forecast_type',
        'predicted_value',
        'confidence',
        'lower_bound',
        'upper_bound',
        'factors',
        'ai_insight',
        'historical_data',
        'weather_data',
    ];

    protected $casts = [
        'forecast_date' => 'date',
        'predicted_value' => 'decimal:2',
        'confidence' => 'decimal:2',
        'lower_bound' => 'decimal:2',
        'upper_bound' => 'decimal:2',
        'factors' => 'array',
        'historical_data' => 'array',
        'weather_data' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
