<?php

namespace App\Models\Ecommerce;

use App\Models\Products\Item;
use App\Traits\SerializesDateToAppTimezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EcommerceOrderLine extends Model
{
    use HasFactory, SerializesDateToAppTimezone;

    protected $fillable = [
        'ecommerce_order_id',
        'item_id',
        'item_name',
        'qty',
        'price',
        'sub_total',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'sub_total' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(EcommerceOrder::class, 'ecommerce_order_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
