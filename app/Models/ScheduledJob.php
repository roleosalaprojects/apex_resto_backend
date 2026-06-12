<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Row-per-Artisan-command toggle backing the Scheduled Jobs admin UI.
 * `key` matches the command signature passed to `Schedule::command(...)`
 * in routes/console.php (including any `--type=daily` style args).
 *
 * Default behavior when no row exists: ENABLED. This means a fresh
 * deploy that hasn't seeded yet won't silently halt scheduled work —
 * the seeder populates rows but the scheduler keeps firing in the
 * gap.
 */
class ScheduledJob extends Model
{
    public const KEY_HIGHER_ACCESS_EXPIRE = 'higher-access:expire';

    public const KEY_WEATHER_FETCH = 'weather:fetch';

    public const KEY_REPORT_DAILY = 'report:generate --type=daily';

    public const KEY_REPORT_WEEKLY = 'report:generate --type=weekly';

    public const KEY_SMS_LOGS_POLL = 'sms-logs:poll-pending';

    public const KEY_DAILY_SALES_SUMMARY = 'notification:daily-sales-summary';

    public const KEY_FIRE_ALERTS = 'notifications:fire-alerts';

    public const KEY_BI_AGGREGATE_DAILY = 'bi:aggregate-daily';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'key',
        'description',
        'cadence_label',
        'enabled',
        'last_run_at',
        'last_run_status',
        'last_run_duration_ms',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'last_run_at' => 'datetime',
            'last_run_duration_ms' => 'integer',
        ];
    }

    public static function findByKey(string $key): ?self
    {
        return static::query()->where('key', $key)->first();
    }

    /**
     * Defaults to ENABLED when no row exists for this key. Keeps a
     * freshly migrated environment from accidentally silencing every
     * scheduled command until the seeder runs.
     */
    public static function isEnabled(string $key): bool
    {
        $row = static::findByKey($key);

        if ($row === null) {
            return true;
        }

        return (bool) $row->enabled;
    }

    public static function recordRun(string $key, string $status, ?int $durationMs = null): void
    {
        $row = static::findByKey($key);

        if ($row === null) {
            return;
        }

        $row->forceFill([
            'last_run_at' => now(),
            'last_run_status' => $status,
            'last_run_duration_ms' => $durationMs,
        ])->save();
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
