<?php

namespace App\Models\Pos;

use App\Models\Products\Discount;
use App\Models\Products\Item;
use App\Models\Products\Unit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderLine extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'qty',
        'price',
        'unit_name',
        'item_name',
        'discount',
        'sub_total', // qty * price
        'unit_qty', // number of pc or kg in a unit
        'cost',
        'vat_type', // 0 = Non-VAT, 1 = VATable, 2 = Zero-Rated
        'item_id',
        'unit_id',
        'discount_by',
        'discount_id',
        'tax_id',
        'rate',
        'discount_type',
        'pwd_rate',
        'sc_rate',
        'discountable',
        'type',
        'order_id',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function discount(): BelongsTo
    {
        return $this->belongsTo(Discount::class);
    }
}
