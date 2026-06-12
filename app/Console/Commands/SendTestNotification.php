<?php

namespace App\Console\Commands;

use App\Models\DeviceToken;
use App\Services\FcmService;
use Illuminate\Console\Command;

class SendTestNotification extends Command
{
    protected $signature = 'notification:test
                            {--user= : User ID to send to (sends to all if omitted)}
                            {--title=Test Notification : Notification title}
                            {--body=This is a test notification from Apex Backend : Notification body}
                            {--type=test : Notification type (ecommerce_order, po_approval, margin_alert, low_stock, daily_sales_summary, reorder_alert, late_clockin, large_sale, refund_alert, higher_access_request, test)}
                            {--id= : Target entity ID for deep linking}';

    protected $description = 'Send a test push notification via FCM';

    public function handle(FcmService $fcm): int
    {
        $userId = $this->option('user');
        $title = $this->option('title');
        $body = $this->option('body');
        $type = $this->option('type');
        $targetId = $this->option('id');

        $data = ['type' => $type];
        if ($targetId) {
            $data['id'] = $targetId;
        }

        // Show registered tokens
        $query = $userId ? DeviceToken::forUser((int) $userId) : DeviceToken::query();
        $tokenCount = $query->count();

        if ($tokenCount === 0) {
            $this->error('No device tokens registered'.($userId ? " for user #{$userId}" : '').'.');
            $this->line('Make sure a device has logged in to register its FCM token.');

            return self::FAILURE;
        }

        $this->info("Sending to {$tokenCount} device(s)...");
        $this->table(['Field', 'Value'], [
            ['Title', $title],
            ['Body', $body],
            ['Type', $type],
            ['Target ID', $targetId ?? '(none)'],
            ['User', $userId ?? '(all)'],
        ]);

        $sent = $userId
            ? $fcm->sendToUser((int) $userId, $title, $body, $data)
            : $fcm->sendToAll($title, $body, $data);

        if ($sent > 0) {
            $this->info("Sent successfully to {$sent}/{$tokenCount} device(s).");
        } else {
            $this->error('Failed to send to any device. Check logs for details.');
        }

        return $sent > 0 ? self::SUCCESS : self::FAILURE;
    }
}
