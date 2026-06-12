<?php

namespace App\Observers;

use App\Models\Settings\BrandingSetting;
use App\Models\Settings\ColorPalette;
use Illuminate\Support\Facades\Cache;

class ColorPaletteObserver
{
    public function saving(ColorPalette $palette): void
    {
        if ($palette->is_default) {
            ColorPalette::query()
                ->where('is_default', true)
                ->when($palette->exists, fn ($q) => $q->where('id', '!=', $palette->id))
                ->update(['is_default' => false]);
        }
    }

    public function deleting(ColorPalette $palette): void
    {
        if ($palette->is_default) {
            throw new \RuntimeException('The default palette cannot be deleted.');
        }
    }

    public function saved(ColorPalette $palette): void
    {
        $this->invalidateAllTenantCaches();
    }

    public function deleted(ColorPalette $palette): void
    {
        $this->invalidateAllTenantCaches();
    }

    public function restored(ColorPalette $palette): void
    {
        $this->invalidateAllTenantCaches();
    }

    private function invalidateAllTenantCaches(): void
    {
        BrandingSetting::query()
            ->pluck('user_id')
            ->each(fn ($userId) => Cache::forget("branding.{$userId}"));

        Cache::forget('branding.storefront');
    }
}
