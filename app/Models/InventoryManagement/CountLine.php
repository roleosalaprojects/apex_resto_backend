<?php

namespace App\Models\InventoryManagement;

use App\Models\Products\Item;
use App\Models\Products\ItemStore;
use App\Models\Products\ItemUnit;
use App\Models\Products\Unit;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CountLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_id',
        'unit_id',
        'count_id',
        'counted_qty',
    ];

    protected $casts = [
        'counted_qty' => 'decimal:2',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(CountLine::class, 'count_id', 'id');
    }

    public function item(): HasOne
    {
        return $this->hasOne(Item::class, 'id', 'item_id');
    }

    public function unit(): HasOne
    {
        return $this->hasOne(Unit::class, 'id', 'unit_id');
    }

    public function count(): BelongsTo
    {
        return $this->belongsTo(Count::class, 'count_id', 'id');
    }

    public function item_stock(): HasMany
    {
        return $this->hasMany(ItemStore::class, 'item_id', 'item_id');
    }

    public function item_unit(): HasMany
    {
        return $this->hasMany(ItemUnit::class, 'item_id', 'item_id');
    }
}
