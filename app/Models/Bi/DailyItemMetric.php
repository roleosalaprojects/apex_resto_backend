<?php

namespace App\Models\Bi;

use App\Models\Products\Item;
use App\Models\Settings\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pre-aggregated daily per-item sales metrics per (tenant, store,
 * item, day). Derived data — rebuilt by `bi:aggregate-daily`.
 * qty_sold is base-unit quantity (qty * unit_qty).
 */
class DailyItemMetric extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'qty_sold' => 'float',
            'revenue' => 'float',
            'cost_total' => 'float',
            'profit' => 'float',
            'discount_total' => 'float',
            'refund_qty' => 'float',
            'refund_total' => 'float',
            'transactions' => 'integer',
        ];
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForStore(Builder $query, int $storeId): Builder
    {
        return $query->where('store_id', $storeId);
    }

    public function scopeBetweenDates(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('date', [$from, $to]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
