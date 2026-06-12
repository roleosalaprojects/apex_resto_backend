<?php

use App\Mail\OrderShipped;
use App\Models\ScheduledJob;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    \Log::info('Inspire', [$this->comment(Inspiring::quote())]);
})->describe('Display an inspiring quote');

Artisan::command('sendEmail', function () {
    Mail::to('roleosala@gmail.com')->send(new OrderShipped(rand(1, 100000000000)));
});

Artisan::command('poActions', function () {
    // Get Purchase Orders that has not been received.
    $purchases = \App\Purchase::where(DB::raw('items - received'), '>', 0)
        ->with([
            'supplier',
        ])
        ->get();
    Mail::to('roleosala@gmail.com')->send(new \App\Mail\Purchases\PerformAction($purchases));
});

/**
 * Bind a scheduled command to the ScheduledJob admin toggle: skip the
 * run when the row is disabled, and stamp last_run_at + status on
 * every execution. Returns the Event so the caller can append more
 * scheduler hooks (->withoutOverlapping(), etc.).
 */
$bindJob = function (Event $event, string $key): Event {
    $startedAt = null;

    return $event
        ->when(fn () => ScheduledJob::isEnabled($key))
        ->before(function () use (&$startedAt) {
            $startedAt = microtime(true);
        })
        ->onSuccess(function () use ($key, &$startedAt) {
            $duration = $startedAt === null ? null : (int) round((microtime(true) - $startedAt) * 1000);
            ScheduledJob::recordRun($key, ScheduledJob::STATUS_SUCCESS, $duration);
        })
        ->onFailure(function () use ($key, &$startedAt) {
            $duration = $startedAt === null ? null : (int) round((microtime(true) - $startedAt) * 1000);
            ScheduledJob::recordRun($key, ScheduledJob::STATUS_FAILED, $duration);
        });
};

// Schedule higher access request expiration
$bindJob(Schedule::command('higher-access:expire')->everyMinute(), ScheduledJob::KEY_HIGHER_ACCESS_EXPIRE);

// Fetch weather forecasts for store locations twice daily
$bindJob(Schedule::command('weather:fetch')->twiceDaily(6, 18), ScheduledJob::KEY_WEATHER_FETCH);

// Send daily sales reports at 8 AM
$bindJob(Schedule::command('report:generate --type=daily')->dailyAt('08:00'), ScheduledJob::KEY_REPORT_DAILY);

// Send weekly sales reports every Monday at 8 AM
$bindJob(Schedule::command('report:generate --type=weekly')->weeklyOn(1, '08:00'), ScheduledJob::KEY_REPORT_WEEKLY);

// Poll VeroSMS for delivery status of recently-sent SMS. Every 2
// minutes is a reasonable cadence — fast enough to flip "sent" rows
// to "delivered" while the admin is still watching the page, gentle
// enough not to hammer the relay.
$bindJob(
    Schedule::command('sms-logs:poll-pending')->everyTwoMinutes()->withoutOverlapping(),
    ScheduledJob::KEY_SMS_LOGS_POLL,
);

// Send daily sales summary push notification
$bindJob(
    Schedule::command('notification:daily-sales-summary')->dailyAt(config('notifications.daily_summary_time', '20:00')),
    ScheduledJob::KEY_DAILY_SALES_SUMMARY,
);

// Margin + reorder alerts. Hourly is the single trigger surface — read-side
// controllers no longer fire these notifications on render.
$bindJob(
    Schedule::command('notifications:fire-alerts')->hourly()->withoutOverlapping(),
    ScheduledJob::KEY_FIRE_ALERTS,
);

// Rebuild the daily BI summary tables shortly after midnight. The
// command's default trailing window re-covers the last few days, so
// retroactive cancellations heal on the next run.
$bindJob(
    Schedule::command('bi:aggregate-daily')->dailyAt('00:30')->withoutOverlapping(),
    ScheduledJob::KEY_BI_AGGREGATE_DAILY,
);
