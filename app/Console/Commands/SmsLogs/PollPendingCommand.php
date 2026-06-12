<?php

namespace App\Console\Commands\SmsLogs;

use App\Jobs\PollVeroSmsStatusJob;
use App\Models\OutboundSmsLog;
use Illuminate\Console\Command;

/**
 * Scheduled poller that keeps the SMS log fresh without admin
 * intervention. Picks up any "sent" row that:
 *   - has a sms_id (was actually accepted by the relay)
 *   - was sent at least --send-grace minutes ago (default 1 min — give
 *     the device time to actually relay before asking)
 *   - hasn't been polled in --recheck-after minutes (default 5)
 *
 * Schedule it from routes/console.php at whatever cadence makes sense
 * for your volume — every 2 minutes is a reasonable default.
 */
class PollPendingCommand extends Command
{
    protected $signature = 'sms-logs:poll-pending
                            {--limit=500 : Maximum number of rows to enqueue this tick}
                            {--send-grace=1 : Skip rows sent fewer than this many minutes ago}
                            {--recheck-after=5 : Skip rows polled within this many minutes}';

    protected $description = 'Background-poll VeroSMS for pending SMS delivery status.';

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $sendGrace = (int) $this->option('send-grace');
        $recheckAfter = (int) $this->option('recheck-after');

        $ids = OutboundSmsLog::query()
            ->where('status', OutboundSmsLog::STATUS_SENT)
            ->whereNotNull('sms_id')
            ->where('created_at', '<=', now()->subMinutes($sendGrace))
            ->where(function ($q) use ($recheckAfter) {
                $q->whereNull('last_checked_at')
                    ->orWhere('last_checked_at', '<', now()->subMinutes($recheckAfter));
            })
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        foreach ($ids as $id) {
            PollVeroSmsStatusJob::dispatch($id);
        }

        $this->info("Queued {$ids->count()} status poll(s).");

        return self::SUCCESS;
    }
}
