<?php

namespace App\Jobs;

use App\Contracts\SmsRelayContract;
use App\Models\OutboundSmsLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Hit /api/check/status on a single outbound_sms_logs row and refresh
 * its delivery state. Designed for bulk polling — the admin "Poll All
 * Pending" button + the sms-logs:poll-pending scheduled command both
 * dispatch a job per row, so the worker spreads the relay traffic
 * naturally and a slow VeroSMS response doesn't block the UI.
 */
class PollVeroSmsStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Cap the worker retries — a 500ms blip is fine, a permanent outage isn't worth replaying. */
    public int $tries = 2;

    /** Don't sit on a single VeroSMS request forever. */
    public int $timeout = 20;

    public function __construct(public int $smsLogId) {}

    public function handle(SmsRelayContract $sms): void
    {
        $log = OutboundSmsLog::find($this->smsLogId);
        if (! $log || ! $log->sms_id) {
            return;
        }

        $sms->pollStatus($log);
    }
}
