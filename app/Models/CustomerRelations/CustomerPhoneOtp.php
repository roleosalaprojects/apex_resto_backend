<?php

namespace App\Models\CustomerRelations;

use Illuminate\Database\Eloquent\Model;

/**
 * Single-use OTP record for customer phone verification at /shop
 * registration. Created when we send the code, consumed when the
 * customer enters it. Inspectable for fraud / delivery debugging.
 *
 * Updated_at isn't tracked — these rows are immutable once issued
 * (only `attempts` and `consumed_at` can change, and we read those
 * directly without caring about modification timestamps).
 */
class CustomerPhoneOtp extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'phone',
        'code_hash',
        'attempts',
        'expires_at',
        'consumed_at',
        'ip_address',
        'sms_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'created_at' => 'datetime',
            'attempts' => 'integer',
            'sms_id' => 'integer',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }
}
