<?php

namespace App\Models\Bi;

use App\Models\Settings\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pre-aggregated daily sales/expense metrics per (tenant, store, day).
 * Derived data — rebuilt by `bi:aggregate-daily` from sales + expenses,
 * never written by application flows.
 */
class DailyStoreMetric extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'gross_sales' => 'float',
            'refunds_total' => 'float',
            'net_sales' => 'float',
            'profit' => 'float',
            'cogs' => 'float',
            'discount_total' => 'float',
            'sc_discount_total' => 'float',
            'pwd_discount_total' => 'float',
            'sp_discount_total' => 'float',
            'naac_discount_total' => 'float',
            'voucher_discount_total' => 'float',
            'vatable_total' => 'float',
            'vat_total' => 'float',
            'non_vat_total' => 'float',
            'zero_rated_total' => 'float',
            'cash_total' => 'float',
            'ewallet_total' => 'float',
            'credit_total' => 'float',
            'bank_transfer_total' => 'float',
            'cheque_total' => 'float',
            'ecommerce_sales_total' => 'float',
            'expenses_total' => 'float',
            'transactions' => 'integer',
            'refund_count' => 'integer',
            'ecommerce_transactions' => 'integer',
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
}
