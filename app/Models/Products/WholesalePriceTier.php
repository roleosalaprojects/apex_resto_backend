<?php

namespace App\Models\Products;

use App\Traits\SerializesDateToAppTimezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WholesalePriceTier extends Model
{
    /** @use HasFactory<\Database\Factories\WholesalePriceTierFactory> */
    use HasFactory, SerializesDateToAppTimezone;

    protected $fillable = [
        'item_id',
        'min_qty',
        'discount',
    ];

    protected function casts(): array
    {
        return [
            'min_qty' => 'integer',
            'discount' => 'decimal:2',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
