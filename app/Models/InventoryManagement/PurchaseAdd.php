<?php

namespace App\Models\InventoryManagement;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseAdd extends Model
{
    //
    protected $fillable = [
        'description',
        'amount',
        'purchase_id',
    ];

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }
}
