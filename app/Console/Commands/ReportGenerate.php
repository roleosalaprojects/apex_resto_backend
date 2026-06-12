<?php

namespace App\Console\Commands;

use App\Mail\DailySalesReport;
use App\Mail\WeeklySalesReport;
use App\Models\Reports\ReportRecipient;
use App\Services\ReportService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class ReportGenerate extends Command
{
    protected $signature = 'report:generate
                            {--type=daily : Report type (daily or weekly)}
                            {--user= : Specific user ID to generate for}
                            {--email= : Send to specific email instead of recipients}';

    protected $description = 'Generate and send scheduled sales reports';

    public function handle(ReportService $reportService): int
    {
        $type = $this->option('type');
        $userId = $this->option('user');
        $email = $this->option('email');

        if (! in_array($type, ['daily', 'weekly'])) {
            $this->error('Invalid report type. Use "daily" or "weekly".');

            return self::FAILURE;
        }

        // If specific user and email provided, send just that one
        if ($userId && $email) {
            $this->sendReport($reportService, (int) $userId, $email, $type);
            $this->info("Report sent to {$email}.");

            return self::SUCCESS;
        }

        // Otherwise, send to all active recipients
        $query = ReportRecipient::where('is_active', true);

        if ($type === 'daily') {
            $query->whereIn('report_type', ['daily', 'both']);
        } else {
            $query->whereIn('report_type', ['weekly', 'both']);
        }

        if ($userId) {
            $query->where('user_id', $userId);
        }

        $recipients = $query->get();

        if ($recipients->isEmpty()) {
            $this->info('No active recipients found for '.$type.' reports.');

            return self::SUCCESS;
        }

        $sentCount = 0;
        foreach ($recipients as $recipient) {
            $this->sendReport($reportService, $recipient->user_id, $recipient->email, $type);
            $sentCount++;
        }

        $this->info("{$type} reports sent to {$sentCount} recipients.");

        return self::SUCCESS;
    }

    protected function sendReport(ReportService $reportService, int $userId, string $email, string $type): void
    {
        $reportData = $reportService->generateReportData($userId, $type);

        if ($type === 'daily') {
            $date = Carbon::yesterday()->format('M d, Y');
            Mail::to($email)->sendNow(new DailySalesReport($reportData, $date));
        } else {
            $start = Carbon::today()->subWeek()->startOfWeek()->format('M d');
            $end = Carbon::today()->subWeek()->endOfWeek()->format('M d, Y');
            Mail::to($email)->sendNow(new WeeklySalesReport($reportData, "{$start} - {$end}"));
        }
    }
}
