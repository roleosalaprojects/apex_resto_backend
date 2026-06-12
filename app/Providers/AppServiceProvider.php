<?php

namespace App\Providers;

use App\Models\Accounting\PosLog;
use App\Models\ApiToken;
use App\Models\Ecommerce\EcommerceOrderStatusChange;
use App\Models\Settings\BrandingSetting;
use App\Models\Settings\ColorPalette;
use App\Models\User;
use App\Observers\BrandingSettingObserver;
use App\Observers\ColorPaletteObserver;
use App\Observers\EcommerceOrderStatusChangeObserver;
use App\Observers\PosLogObserver;
use App\Services\BrandingService;
use App\View\Composers\EcommerceComposer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\AliasLoader;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register Form facade alias
        $loader = AliasLoader::getInstance();
        $loader->alias('Form', \App\Helpers\Form::class);
        $loader->alias('Batch', \Mavinoo\Batch\BatchFacade::class);

        // SMS relay — pick the implementation by config flag. Existing
        // callers type-hint VeroSmsService directly today; they'll be
        // migrated to the contract one by one. The fallback binding
        // for VeroSmsService and SmsGateService also resolves naturally
        // since both are auto-resolvable classes.
        $this->app->bind(\App\Contracts\SmsRelayContract::class, function () {
            return match (config('services.sms.driver', 'verosms')) {
                'sms_gate' => $this->app->make(\App\Services\SmsGateService::class),
                default => $this->app->make(\App\Services\VeroSmsService::class),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        Gate::define('viewPulse', function ($user) {
            return (bool) $user->role?->pulse;
        });

        Gate::define('record-cashless-payment', function ($user) {
            return (bool) $user->role?->sls;
        });

        View::composer('components.ecommerce.layout.app', EcommerceComposer::class);

        // Shop + mail layouts: use the storefront tenant's branding so
        // anonymous visitors and queued mail (where no user is auth'd)
        // still see the platform's primary brand rather than the
        // platform default. The /admin backoffice intentionally stays
        // on Apex defaults — no branding composer for layout.app.
        View::composer([
            'components.ecommerce.layout.app',
            'vendor.mail.html.header',
            'vendor.mail.html.message',
        ], function ($view) {
            $view->with('branding', app(BrandingService::class)->forStorefront());
        });

        // Mirror POS cash-outs into the expenses table in real time. Type=12
        // logs become cashless expenses; type=13 voids cascade to the
        // mirrored expense. Idempotent with the cashouts:sync-to-expenses
        // artisan command via the receipt_number = "POS-CASHOUT-<id>" key.
        PosLog::observe(PosLogObserver::class);

        ColorPalette::observe(ColorPaletteObserver::class);
        BrandingSetting::observe(BrandingSettingObserver::class);

        // Every order status mutation creates one EcommerceOrderStatusChange
        // row; the observer dispatches SendOrderUpdateSmsJob keyed off that
        // row's id, so retries/double-fires can't double-send.
        EcommerceOrderStatusChange::observe(EcommerceOrderStatusChangeObserver::class);

        // Keeps Purchase.approval_status (int enum) in sync with
        // PurchaseApproval.status (string enum) — see §1.5 of
        // development/specs/purchase_order_audit_and_remediation.md.
        \App\Models\InventoryManagement\PurchaseApproval::observe(
            \App\Observers\PurchaseApprovalObserver::class
        );

        Auth::viaRequest('openclaw-token', function (Request $request): ?User {
            $apiToken = ApiToken::findByBearer($request->bearerToken());

            if ($apiToken === null) {
                return null;
            }

            $apiToken->forceFill(['last_used_at' => now()])->saveQuietly();
            $request->attributes->set('api_token', $apiToken);

            return User::query()
                ->where('user_id', $apiToken->user_id)
                ->where('id', $apiToken->user_id)
                ->first();
        });

        RateLimiter::for('openclaw', function (Request $request): Limit {
            $token = $request->attributes->get('api_token');
            $key = $token instanceof ApiToken
                ? "openclaw:token:{$token->id}"
                : 'openclaw:ip:'.$request->ip();

            return Limit::perMinute(
                (int) config('openclaw.rate_limit_per_minute', 120)
            )->by($key);
        });
    }
}
