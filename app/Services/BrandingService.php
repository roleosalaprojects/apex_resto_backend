<?php

namespace App\Services;

use App\Models\Settings\BrandingSetting;
use App\Models\Settings\ColorPalette;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Storage;

class BrandingService
{
    private const FALLBACK_HEX = '#1858fd';

    private const CACHE_TTL_SECONDS = 300;

    public function __construct(private CacheRepository $cache) {}

    /**
     * @return array<string, mixed>
     */
    public function forCurrentTenant(): array
    {
        $tenantId = auth()->user()?->user_id;

        return $tenantId
            ? $this->forTenant((int) $tenantId)
            : $this->defaultPayload();
    }

    /**
     * Storefront branding for anonymous visitors (shop home, /shop/terms,
     * /customer/login, /customer/register, etc.). Resolves to the
     * platform's primary tenant — the first active admin who owns the
     * shop — falling back to platform defaults when no tenant exists.
     *
     * @return array<string, mixed>
     */
    public function forStorefront(): array
    {
        return $this->cache->remember(
            'branding.storefront',
            self::CACHE_TTL_SECONDS,
            fn () => $this->resolveStorefront(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveStorefront(): array
    {
        $tenantUserId = \App\Models\User::query()
            ->whereColumn('id', 'user_id')
            ->where('status', true)
            ->orderBy('id')
            ->value('user_id');

        return $tenantUserId
            ? $this->resolve((int) $tenantUserId)
            : $this->defaultPayload();
    }

    /**
     * @return array<string, mixed>
     */
    public function forTenant(int $tenantUserId): array
    {
        return $this->cache->remember(
            "branding.{$tenantUserId}",
            self::CACHE_TTL_SECONDS,
            fn () => $this->resolve($tenantUserId),
        );
    }

    /**
     * Strict hex validator used to guard the write path AND the read path.
     */
    public function sanitizeHex(string $hex): string
    {
        $hex = strtolower($hex);

        return preg_match('/^#[0-9a-f]{6}$/', $hex) === 1
            ? $hex
            : self::FALLBACK_HEX;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolve(int $userId): array
    {
        $setting = BrandingSetting::query()
            ->where('user_id', $userId)
            ->first();

        $palette = $this->palette($setting?->palette_key);

        $logoUrl = $setting?->logo_path && Storage::disk('public')->exists($setting->logo_path)
            ? Storage::disk('public')->url($setting->logo_path)
            : null;

        return [
            'palette_key' => $palette->key,
            'primary' => $this->sanitizeHex($palette->primary),
            'secondary' => $this->sanitizeHex($palette->secondary),
            'accent' => $this->sanitizeHex($palette->accent),
            'on_primary' => $this->sanitizeHex($palette->on_primary),
            'on_secondary' => $this->sanitizeHex($palette->on_secondary),
            'logo_url' => $logoUrl,
            'brand_name' => $setting?->brand_name ?: 'APEX',
            'updated_at' => $setting?->updated_at?->toIso8601String(),
        ];
    }

    private function palette(?string $key): ColorPalette
    {
        $palette = $key
            ? ColorPalette::query()->where('key', $key)->first()
            : null;

        if (! $palette || ! $palette->is_active) {
            $palette = ColorPalette::query()->where('is_default', true)->first();
        }

        if (! $palette) {
            // No default exists (seeder hasn't run). Return a synthetic
            // palette so the app never crashes — branding gracefully
            // falls back to the hardcoded Apex blue.
            $palette = new ColorPalette([
                'key' => 'apex_default',
                'label' => 'Apex Blue',
                'primary' => self::FALLBACK_HEX,
                'secondary' => self::FALLBACK_HEX,
                'accent' => self::FALLBACK_HEX,
                'on_primary' => '#ffffff',
                'on_secondary' => '#ffffff',
                'is_default' => true,
                'is_active' => true,
            ]);
        }

        return $palette;
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultPayload(): array
    {
        $palette = $this->palette(null);

        return [
            'palette_key' => $palette->key,
            'primary' => $this->sanitizeHex($palette->primary),
            'secondary' => $this->sanitizeHex($palette->secondary),
            'accent' => $this->sanitizeHex($palette->accent),
            'on_primary' => $this->sanitizeHex($palette->on_primary),
            'on_secondary' => $this->sanitizeHex($palette->on_secondary),
            'logo_url' => null,
            'brand_name' => 'APEX',
            'updated_at' => null,
        ];
    }
}
