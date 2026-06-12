<?php

namespace App\Models\Bi;

use App\Models\CustomerRelations\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pre-aggregated daily per-customer spend metrics per (tenant,
 * customer, day). Walk-ins (customer_id NULL on the sale) are not
 * represented here — store-level totals cover them. Derived data —
 * rebuilt by `bi:aggregate-daily`.
 */
class DailyCustomerMetric extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'spend_total' => 'float',
            'refund_total' => 'float',
            'profit' => 'float',
            'points_earned' => 'float',
            'points_used' => 'float',
            'transactions' => 'integer',
            'refund_count' => 'integer',
        ];
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeBetweenDates(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('date', [$from, $to]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
