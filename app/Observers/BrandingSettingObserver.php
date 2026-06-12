<?php

namespace App\Observers;

use App\Models\Settings\BrandingSetting;
use Illuminate\Support\Facades\Cache;

class BrandingSettingObserver
{
    public function saved(BrandingSetting $setting): void
    {
        Cache::forget("branding.{$setting->user_id}");
        Cache::forget('branding.storefront');
    }

    public function deleted(BrandingSetting $setting): void
    {
        Cache::forget("branding.{$setting->user_id}");
        Cache::forget('branding.storefront');
    }
}
