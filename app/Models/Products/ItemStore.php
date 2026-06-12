<?php

namespace App\Models\Products;

use App\Models\Settings\Store;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemStore extends Model
{
    use HasFactory;

    //
    protected $fillable = [
        'stock', 'status', 'store_id', 'item_id',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
