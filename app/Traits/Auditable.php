<?php

namespace App\Traits;

use App\Models\ApiToken;
use App\Models\Reports\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (Model $model) {
            $model->logAudit('created', null, $model->getAttributes());
        });

        static::updated(function (Model $model) {
            $original = $model->getOriginal();
            $changes = $model->getChanges();

            // Strip framework-managed and per-model excluded fields BEFORE
            // deciding whether to log. An update that only moved excluded
            // fields (e.g. an Item's cost/price during POS receiving) should
            // produce no audit row at all, not an empty-diff row.
            $excluded = array_flip($model->getExcludedAuditFields());
            $changes = array_diff_key($changes, $excluded);

            if (! empty($changes)) {
                $oldValues = array_intersect_key($original, $changes);
                $model->logAudit('updated', $oldValues, $changes);
            }
        });

        static::deleted(function (Model $model) {
            $model->logAudit('deleted', $model->getOriginal(), null);
        });

        if (method_exists(static::class, 'restored')) {
            static::restored(function (Model $model) {
                $model->logAudit('restored', null, $model->getAttributes());
            });
        }
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function logAudit(string $event, ?array $oldValues, ?array $newValues): void
    {
        $excludedFields = $this->getExcludedAuditFields();

        if ($oldValues) {
            $oldValues = array_diff_key($oldValues, array_flip($excludedFields));
        }

        if ($newValues) {
            $newValues = array_diff_key($newValues, array_flip($excludedFields));
        }

        AuditLog::create([
            'user_id' => auth()->id(),
            'auditable_type' => get_class($this),
            'auditable_id' => $this->getKey(),
            'event' => $event,
            'source' => $this->resolveAuditSource(),
            'api_token_id' => $this->resolveAuditApiTokenId(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => app()->runningInConsole() ? null : request()->ip(),
            'user_agent' => app()->runningInConsole() ? null : request()->userAgent(),
            'url' => app()->runningInConsole() ? null : request()->fullUrl(),
        ]);
    }

    /**
     * Map the current request to a coarse "where did this come from" tag.
     * URL prefix wins because PHPUnit also reports runningInConsole()=true
     * when dispatching real HTTP test requests; only treat console as the
     * source when there's actually no request path.
     */
    protected function resolveAuditSource(): ?string
    {
        $path = trim((string) request()->path(), '/');

        if ($path !== '') {
            if (str_starts_with($path, 'admin')) {
                return 'web';
            }
            if (str_starts_with($path, 'api/v1/openclaw')) {
                return 'openclaw';
            }
            if (str_starts_with($path, 'api/v1/mobile')) {
                return 'mobile';
            }
            if (str_starts_with($path, 'api/')) {
                return 'pos';
            }
        }

        if (app()->runningInConsole()) {
            return 'console';
        }

        return null;
    }

    /**
     * If the openclaw guard's middleware set the api_token attribute on the
     * request, capture its id so the audit row can be attributed to a
     * specific bot token (and revoking the token doesn't lose the trail).
     */
    protected function resolveAuditApiTokenId(): ?int
    {
        $token = request()->attributes->get('api_token');

        return $token instanceof ApiToken ? (int) $token->id : null;
    }

    /**
     * @return array<int, string>
     */
    public function getExcludedAuditFields(): array
    {
        return array_merge([
            'password',
            'remember_token',
            'created_at',
            'updated_at',
            'deleted_at',
        ], $this->excludedAuditFields ?? []);
    }
}
