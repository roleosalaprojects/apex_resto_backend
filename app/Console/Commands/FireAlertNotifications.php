<?php

namespace App\Console\Commands;

use App\Models\Settings\Store;
use App\Models\User;
use App\Services\DemandForecastService;
use App\Services\FcmService;
use App\Services\ProfitAnalysisService;
use Illuminate\Console\Command;

/**
 * Single trigger surface for alert-style push notifications.
 *
 * Previously fired by side-effect from controller read paths (every render of
 * /admin/reports/profit-margins, every "refresh" on /admin/forecast).
 * The render-time FCM calls are gone now — this command consolidates alert
 * firing into one scheduled job that runs hourly per tenant.
 *
 * What it does NOT cover (intentional):
 *   - low_stock pushes — those are correctly event-driven from
 *     UpdateItemStocksJob (sale crosses threshold) and do NOT spam.
 *   - higher_access_request, large_sale, large_refund, po_approval —
 *     all genuinely urgent, stay on their original code paths.
 */
class FireAlertNotifications extends Command
{
    protected $signature = 'notifications:fire-alerts';

    protected $description = 'Compute and push margin + reorder alerts per tenant (single hourly trigger surface)';

    public function handle(FcmService $fcm, ProfitAnalysisService $profit, DemandForecastService $forecast): int
    {
        $tenantOwnerIds = User::query()
            ->whereNotNull('user_id')
            ->select('user_id')
            ->distinct()
            ->pluck('user_id');

        $marginPushes = 0;
        $reorderPushes = 0;

        foreach ($tenantOwnerIds as $userId) {
            $marginPushes += $this->fireMarginAlerts($fcm, $profit, (int) $userId);
            $reorderPushes += $this->fireReorderAlerts($fcm, $forecast, (int) $userId);
        }

        $this->info("Margin pushes: {$marginPushes}. Reorder pushes: {$reorderPushes}.");

        return self::SUCCESS;
    }

    private function fireMarginAlerts(FcmService $fcm, ProfitAnalysisService $profit, int $userId): int
    {
        try {
            $result = $profit->getMarginAlerts($userId, null, 5.0);
            $alerts = $result['alerts'] ?? [];
        } catch (\Throwable $e) {
            \Log::warning("Margin alert computation failed for tenant {$userId}: ".$e->getMessage());

            return 0;
        }

        if (empty($alerts)) {
            return 0;
        }

        $count = count($alerts);
        $worst = $alerts[0];

        try {
            $fcm->sendToUsersWithPermission(
                $userId,
                'sls',
                'Margin Alert',
                "{$count} item(s) with margin drops — {$worst['item_name']} down ".number_format($worst['margin_drop_pct'], 1).'%',
                ['type' => 'margin_alert'],
            );

            return 1;
        } catch (\Throwable $e) {
            \Log::warning("FCM margin-alert push failed for tenant {$userId}: ".$e->getMessage());

            return 0;
        }
    }

    private function fireReorderAlerts(FcmService $fcm, DemandForecastService $forecast, int $userId): int
    {
        $storeIds = Store::query()->where('user_id', $userId)->pluck('id');
        $totalUrgent = 0;

        foreach ($storeIds as $storeId) {
            try {
                $suggestions = $forecast->generateReorderSuggestions($userId, (int) $storeId);
                $totalUrgent += $suggestions->whereIn('urgency', ['critical', 'high'])->count();
            } catch (\Throwable $e) {
                \Log::warning("Reorder computation failed for tenant {$userId} store {$storeId}: ".$e->getMessage());
            }
        }

        if ($totalUrgent === 0) {
            return 0;
        }

        try {
            $fcm->sendToUsersWithPermission(
                $userId,
                'invntry',
                'Reorder Alert',
                "{$totalUrgent} critical reorder suggestion(s) need attention",
                ['type' => 'reorder_alert'],
            );

            return 1;
        } catch (\Throwable $e) {
            \Log::warning("FCM reorder-alert push failed for tenant {$userId}: ".$e->getMessage());

            return 0;
        }
    }
}
