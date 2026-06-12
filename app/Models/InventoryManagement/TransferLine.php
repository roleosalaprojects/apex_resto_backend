<?php

namespace App\Models\InventoryManagement;

use App\Models\Products\Item;
use App\Models\Products\Unit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransferLine extends Model
{
    //
    protected $fillable = [
        'qty',
        'received',
        'item_id',
        'unit_id',
        'unit',
        'unit_qty',
        'transfer_id',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function unitRelation(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id');
    }
}
