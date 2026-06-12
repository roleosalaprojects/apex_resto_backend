<?php

namespace App\Models\InventoryManagement;

use App\Models\Products\Item;
use App\Models\Products\Unit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseLine extends Model
{
    //
    protected $fillable = [
        'item_id',
        'qty',
        'cost',
        'unit_id',
        'unit_qty',
        'unit_name',
        'received',
        'purchase_id',
    ];

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id', 'id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }
}
