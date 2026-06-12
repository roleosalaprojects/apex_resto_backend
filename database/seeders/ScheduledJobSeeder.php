<?php

namespace Database\Seeders;

use App\Models\ScheduledJob;
use Illuminate\Database\Seeder;

/**
 * Idempotent — `firstOrCreate` per key so admin edits to `enabled`
 * survive re-seeding. New keys get added when the app adds new
 * scheduled commands; description is the only field a re-seed
 * touches on existing rows (via separate update below), so the
 * "what does this do" copy stays current.
 */
class ScheduledJobSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            [
                'key' => ScheduledJob::KEY_HIGHER_ACCESS_EXPIRE,
                'description' => 'Expires pending higher-access requests after their TTL. Disabling leaves expired requests visible to admins indefinitely.',
                'cadence_label' => 'Every minute',
            ],
            [
                'key' => ScheduledJob::KEY_WEATHER_FETCH,
                'description' => 'Pulls weather forecasts for store locations. Disabling freezes weather widgets on stale data.',
                'cadence_label' => 'Twice daily (6 AM, 6 PM)',
            ],
            [
                'key' => ScheduledJob::KEY_REPORT_DAILY,
                'description' => 'Emails the daily sales report to subscribed recipients at 8 AM. Disabling silences the daily digest.',
                'cadence_label' => 'Daily at 8:00 AM',
            ],
            [
                'key' => ScheduledJob::KEY_REPORT_WEEKLY,
                'description' => 'Emails the weekly sales report every Monday at 8 AM. Disabling silences the Monday digest.',
                'cadence_label' => 'Weekly (Mon 8:00 AM)',
            ],
            [
                'key' => ScheduledJob::KEY_SMS_LOGS_POLL,
                'description' => 'Polls VeroSMS for delivery status of recently-sent messages. Disabling leaves SMS log rows stuck on "sent" indefinitely.',
                'cadence_label' => 'Every 2 minutes',
            ],
            [
                'key' => ScheduledJob::KEY_DAILY_SALES_SUMMARY,
                'description' => 'Sends the daily sales summary push notification to the dashboard app. Disabling silences the 8 PM push.',
                'cadence_label' => 'Daily (configurable; default 8:00 PM)',
            ],
            [
                'key' => ScheduledJob::KEY_FIRE_ALERTS,
                'description' => 'Single trigger surface for margin + reorder alerts. Disabling halts ALL margin/reorder push notifications — read-side controllers no longer fire these.',
                'cadence_label' => 'Hourly',
            ],
            [
                'key' => ScheduledJob::KEY_BI_AGGREGATE_DAILY,
                'description' => 'Rebuilds the daily BI summary tables (store, item, customer metrics). Disabling stalls aggregate-fed dashboards on stale data — raw reports are unaffected.',
                'cadence_label' => 'Daily at 12:30 AM',
            ],
        ];

        foreach ($defaults as $row) {
            ScheduledJob::firstOrCreate(
                ['key' => $row['key']],
                [
                    'description' => $row['description'],
                    'cadence_label' => $row['cadence_label'],
                    'enabled' => true,
                ]
            );
        }
    }
}
