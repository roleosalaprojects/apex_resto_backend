<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ApiToken extends Model
{
    use Auditable, HasFactory;

    /**
     * Don't write the token hash to the audit_logs old_values/new_values
     * payloads. We need the audit row, but the hash isn't useful in
     * cleartext-with-history form (it's already a one-way hash, but no
     * point making it easier to find).
     *
     * @var array<int, string>
     */
    protected array $excludedAuditFields = ['token', 'last_used_at'];

    protected $fillable = [
        'user_id',
        'name',
        'token',
        'abilities',
        'last_used_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'abilities' => 'array',
            'last_used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    /**
     * Hash a plain-text token for storage/lookup.
     */
    public static function hashToken(string $plain): string
    {
        return hash('sha256', $plain);
    }

    /**
     * Resolve a non-revoked api token from a plain-text bearer string.
     * Used by both the openclaw guard and the ability middleware so that
     * each can do a self-contained lookup without depending on the other's
     * side effects (the auth guard caches resolved users across requests
     * in tests, which can leave request attributes stale).
     */
    public static function findByBearer(?string $bearer): ?self
    {
        if ($bearer === null || $bearer === '') {
            return null;
        }

        return static::query()
            ->where('token', static::hashToken($bearer))
            ->whereNull('revoked_at')
            ->first();
    }

    /**
     * Generate a fresh plain-text token.
     */
    public static function generatePlainToken(): string
    {
        return Str::random(64);
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    /**
     * Default abilities applied when the abilities column is NULL.
     * Tokens minted before the abilities feature existed should not silently
     * gain write access; treat them as read-only.
     *
     * @return array<int, string>
     */
    public static function defaultAbilities(): array
    {
        return ['openclaw:read'];
    }

    /**
     * Whether this token may exercise the given ability. The wildcard '*'
     * grants everything. NULL abilities resolve to defaultAbilities().
     */
    public function hasAbility(string $ability): bool
    {
        $granted = $this->abilities ?? self::defaultAbilities();

        if (in_array('*', $granted, true)) {
            return true;
        }

        return in_array($ability, $granted, true);
    }
}
