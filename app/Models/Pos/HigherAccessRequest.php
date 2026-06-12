<?php

namespace App\Models\Pos;

use App\Models\Settings\Pos as PosTerminal;
use App\Models\Settings\Store;
use App\Models\User;
use App\Traits\SerializesDateToAppTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HigherAccessRequest extends Model
{
    use SerializesDateToAppTimezone;

    protected $fillable = [
        'request_id',
        'user_id',
        'user_name',
        'store_id',
        'store_name',
        'pos_id',
        'pos_name',
        'permission_type',
        'context_data',
        'device_id',
        'status',
        'approver_id',
        'approver_name',
        'response_message',
        'expires_at',
        'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'context_data' => 'array',
            'expires_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function pos(): BelongsTo
    {
        return $this->belongsTo(PosTerminal::class);
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending')
            ->where('expires_at', '>', now());
    }

    public function scopeForStore($query, $storeId)
    {
        return $query->where('store_id', $storeId);
    }

    public function scopeExpired($query)
    {
        return $query->where('status', 'pending')
            ->where('expires_at', '<=', now());
    }

    // Helpers
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isPending(): bool
    {
        return $this->status === 'pending' && ! $this->isExpired();
    }
}
