<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Per-send forensic record for VeroSMS dispatches. See migration
 * docstring for column-by-column semantics.
 */
class OutboundSmsLog extends Model
{
    use HasFactory;

    public const STATUS_SENT = 'sent';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_FAILED = 'failed';

    public const VERO_DELIVERED = 1;

    public const VERO_PROCESSING = 2;

    public const VERO_FAILED = 3;

    public const TYPE_OTP_REGISTER = 'otp_register';

    public const TYPE_GENERAL = 'general';

    public const TYPE_ORDER_UPDATE = 'order_update';

    protected $fillable = [
        'phone',
        'type',
        'sms_id',
        'vero_status_code',
        'status',
        'message_length',
        'last_checked_at',
        'error',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            // sms_id stays a string so both VeroSMS's integer ids and
            // SMS Gate's ULID-shaped strings round-trip cleanly. The
            // column itself was widened to varchar(64) in the migration
            // 2026_06_09_..._alter_outbound_sms_logs_sms_id_to_string.
            'sms_id' => 'string',
            'vero_status_code' => 'integer',
            'message_length' => 'integer',
            'last_checked_at' => 'datetime',
        ];
    }

    /**
     * Lift the VeroSMS numeric code to a human label. Used both for
     * filtering and for the admin badge.
     */
    public static function labelFromVeroCode(?int $code): ?string
    {
        return match ($code) {
            self::VERO_DELIVERED => self::STATUS_DELIVERED,
            self::VERO_PROCESSING => self::STATUS_PROCESSING,
            self::VERO_FAILED => self::STATUS_FAILED,
            default => null,
        };
    }

    /**
     * Bootstrap badge variant matching the lifecycle colour palette
     * the rest of the app already uses.
     */
    public function statusBadgeVariant(): string
    {
        return match ($this->status) {
            self::STATUS_DELIVERED => 'success',
            self::STATUS_PROCESSING => 'info',
            self::STATUS_SENT => 'primary',
            self::STATUS_FAILED => 'danger',
            default => 'secondary',
        };
    }

    public function statusLabel(): string
    {
        return ucfirst($this->status);
    }
}
