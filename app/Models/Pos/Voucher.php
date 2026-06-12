<?php

namespace App\Models\Pos;

use App\Models\Settings\Store;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Voucher extends Model
{
    protected $fillable = [
        'code',
        'name',
        'amount',
        'minimum_amount',
        'max_uses',
        'used_count',
        'store_id',
        'expires_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'minimum_amount' => 'decimal:2',
            'max_uses' => 'integer',
            'used_count' => 'integer',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    // Relationships
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function usages(): HasMany
    {
        return $this->hasMany(VoucherUsage::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeNotExpired($query)
    {
        return $query->where('expires_at', '>', now());
    }

    public function scopeHasUsesRemaining($query)
    {
        return $query->whereColumn('used_count', '<', 'max_uses');
    }

    public function scopeForStore($query, $storeId)
    {
        return $query->where(function ($q) use ($storeId) {
            $q->whereNull('store_id')
                ->orWhere('store_id', $storeId);
        });
    }

    public function scopeValid($query, $storeId = null)
    {
        $query->active()->notExpired()->hasUsesRemaining();

        if ($storeId) {
            $query->forStore($storeId);
        }

        return $query;
    }

    // Helpers
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function hasUsesRemaining(): bool
    {
        return $this->used_count < $this->max_uses;
    }

    public function getRemainingUsesAttribute(): int
    {
        return max(0, $this->max_uses - $this->used_count);
    }

    public function isValid(?int $storeId = null): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->isExpired()) {
            return false;
        }

        if (! $this->hasUsesRemaining()) {
            return false;
        }

        if ($storeId && $this->store_id && $this->store_id !== $storeId) {
            return false;
        }

        return true;
    }

    public function canApplyToAmount(float $cartTotal): bool
    {
        return $cartTotal >= $this->minimum_amount;
    }

    public function incrementUsage(): void
    {
        $this->increment('used_count');
    }
}
