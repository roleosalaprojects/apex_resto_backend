<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsTemplate extends Model
{
    public const KEY_ORDER_VERIFIED = 'order.verified';

    public const KEY_ORDER_PAID = 'order.paid';

    public const KEY_ORDER_PREPARING = 'order.preparing';

    public const KEY_ORDER_PICKED_UP = 'order.picked_up';

    public const KEY_ORDER_CANCELLED = 'order.cancelled';

    protected $fillable = [
        'key',
        'description',
        'body',
        'enabled',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }

    public static function findByKey(string $key): ?self
    {
        return static::query()->where('key', $key)->first();
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
