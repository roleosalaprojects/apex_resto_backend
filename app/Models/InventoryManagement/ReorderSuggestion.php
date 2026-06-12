<?php

namespace App\Models\InventoryManagement;

use App\Models\Products\Item;
use App\Models\Settings\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReorderSuggestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'item_id',
        'store_id',
        'current_stock',
        'predicted_demand',
        'suggested_quantity',
        'days_until_stockout',
        'urgency',
        'ai_reason',
        'is_acknowledged',
    ];

    protected $casts = [
        'current_stock' => 'decimal:2',
        'predicted_demand' => 'decimal:2',
        'suggested_quantity' => 'decimal:2',
        'is_acknowledged' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function scopeUnacknowledged($query)
    {
        return $query->where('is_acknowledged', false);
    }

    public function scopeCritical($query)
    {
        return $query->where('urgency', 'critical');
    }

    public function scopeByUrgency($query, string $urgency)
    {
        return $query->where('urgency', $urgency);
    }
}
