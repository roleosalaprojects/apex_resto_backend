<?php

namespace App\Models\Reports;

use App\Models\ApiToken;
use App\Models\User;
use App\Traits\SerializesDateToAppTimezone;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    use HasFactory, SerializesDateToAppTimezone;

    protected $fillable = [
        'user_id',
        'auditable_type',
        'auditable_id',
        'event',
        'source',
        'api_token_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'url',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function apiToken(): BelongsTo
    {
        return $this->belongsTo(ApiToken::class, 'api_token_id');
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Record a service-layer event against any model. Mirrors the
     * field shape the Auditable trait produces for created/updated
     * model events, but is meant for *actions* (payment_recorded,
     * cheque_cleared, etc.) the trait doesn't generate.
     *
     * @param  array<string, mixed>  $newValues
     * @param  array<string, mixed>|null  $oldValues
     */
    public static function record(
        Model $model,
        string $event,
        array $newValues,
        ?array $oldValues = null,
        ?int $userId = null,
    ): self {
        // Resolve from the WEB (admin) guard explicitly. `auth()->id()`
        // without a guard would pick up whatever guard is active —
        // including the `customer` guard. Customer ids would then be
        // inserted into audit_logs.user_id, which FKs to users (admins),
        // and the whole request would 23000 out. Customer-driven flows
        // write directly via static::create([...]) instead.
        return static::create([
            'user_id' => $userId ?? auth('web')->id(),
            'auditable_type' => get_class($model),
            'auditable_id' => $model->getKey(),
            'event' => $event,
            'source' => app()->runningInConsole() ? null : 'web',
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
            'user_agent' => app()->runningInConsole() ? null : request()->userAgent(),
            'url' => app()->runningInConsole() ? null : request()->fullUrl(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getChangedFieldsAttribute(): array
    {
        $oldValues = $this->old_values ?? [];
        $newValues = $this->new_values ?? [];
        $changes = [];

        $allKeys = array_unique(array_merge(array_keys($oldValues), array_keys($newValues)));

        foreach ($allKeys as $key) {
            $oldValue = $oldValues[$key] ?? null;
            $newValue = $newValues[$key] ?? null;

            if ($oldValue !== $newValue) {
                $changes[$key] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }
        }

        return $changes;
    }
}
