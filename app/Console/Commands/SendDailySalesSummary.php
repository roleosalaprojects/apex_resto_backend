<?php

namespace App\Console\Commands;

use App\Models\Pos\Sale;
use App\Models\Pos\SaleLine;
use App\Models\User;
use App\Services\FcmService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SendDailySalesSummary extends Command
{
    protected $signature = 'notification:daily-sales-summary';

    protected $description = 'Send daily sales summary push notification to users with sales permission';

    public function handle(FcmService $fcm): int
    {
        $today = Carbon::today();

        // Get distinct business owner user_ids that had sales today
        $businessUserIds = User::whereNotNull('user_id')
            ->select('user_id')
            ->distinct()
            ->pluck('user_id');

        $sent = 0;

        foreach ($businessUserIds as $businessUserId) {
            $sales = Sale::where('user_id', $businessUserId)
                ->where('cancelled', 0)
                ->where('type', 0)
                ->whereDate('created_at', $today)
                ->get();

            if ($sales->isEmpty()) {
                continue;
            }

            $total = $sales->sum('total');
            $count = $sales->count();

            // Get top-selling product by quantity
            $topProduct = SaleLine::join('sales', 'sales.id', '=', 'sale_lines.sales_id')
                ->join('items', 'items.id', '=', 'sale_lines.item_id')
                ->where('sales.user_id', $businessUserId)
                ->where('sales.cancelled', 0)
                ->where('sales.type', 0)
                ->whereDate('sales.created_at', $today)
                ->select('items.name', DB::raw('SUM(sale_lines.qty) as total_qty'))
                ->groupBy('items.name')
                ->orderByDesc('total_qty')
                ->first();

            $topName = $topProduct?->name ?? 'N/A';

            $body = 'Today: P'.number_format($total, 2)." from {$count} transaction(s). Top: {$topName}";

            $result = $fcm->sendToUsersWithPermission(
                $businessUserId,
                'sls',
                'Daily Sales Summary',
                $body,
                ['type' => 'daily_sales_summary']
            );

            if ($result > 0) {
                $sent++;
            }
        }

        $this->info("Daily sales summary sent to {$sent} business(es).");

        return self::SUCCESS;
    }
}
