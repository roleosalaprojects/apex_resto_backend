<?php

namespace App\Models\Products;

use App\Models\Settings\Store;
use App\Models\User;
use App\Traits\SerializesDateToAppTimezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceHistory extends Model
{
    use HasFactory, SerializesDateToAppTimezone;

    protected $table = 'price_histories';

    protected $fillable = [
        'item_id',
        'old_price',
        'new_price',
        'old_cost',
        'new_cost',
        'old_markup',
        'new_markup',
        'change_reason',
        'description',
        'user_id',
        'store_id',
    ];

    protected function casts(): array
    {
        return [
            'old_price' => 'decimal:2',
            'new_price' => 'decimal:2',
            'old_cost' => 'decimal:2',
            'new_cost' => 'decimal:2',
            'old_markup' => 'decimal:2',
            'new_markup' => 'decimal:2',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Get the price change amount (positive for increase, negative for decrease)
     */
    public function getPriceChangeAttribute(): float
    {
        return $this->new_price - ($this->old_price ?? 0);
    }

    /**
     * Get the price change percentage
     */
    public function getPriceChangePercentAttribute(): ?float
    {
        if (! $this->old_price || $this->old_price == 0) {
            return null;
        }

        return (($this->new_price - $this->old_price) / $this->old_price) * 100;
    }
}
