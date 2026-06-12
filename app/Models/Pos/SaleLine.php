<?php

namespace App\Models\Pos;

use App\Models\Products\Category;
use App\Models\Products\Discount;
use App\Models\Products\Item;
use App\Models\Products\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class SaleLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'qty',
        'unit',
        'discount',
        'price',
        'sub_total',
        'vatable',
        'vat',
        'exempt',
        'zero_rated',
        'cost',
        'refundable',
        'refunded',
        'item_id',
        'unit_id',
        'unit_qty',
        'discount_id',
        'discount_by',
        'sc_discount',
        'pwd_discount',
        'sp_discount',
        'naac_discount',
        'profit',
        'sales_id',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'id', 'sales_id');
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

    public function discountBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'discount_by', 'id');
    }

    public function category(): HasOneThrough
    {
        return $this->hasOneThrough(Item::class, Category::class, 'id', 'id', 'item_id');
    }
}
