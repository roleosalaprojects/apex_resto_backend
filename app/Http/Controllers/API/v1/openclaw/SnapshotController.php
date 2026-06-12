<?php

namespace App\Http\Controllers\API\v1\openclaw;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\CustomerRelations\Customer;
use App\Models\Pos\Sale;
use App\Models\Pos\SaleLine;
use App\Models\Products\ItemStore;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SnapshotController extends Controller
{
    use ApiResponse;

    private const LOW_STOCK_THRESHOLD = 10;

    /**
     * Single business-health snapshot for the authenticated tenant.
     */
    public function index(Request $request): JsonResponse
    {
        $tenantUserId = (int) auth()->user()->user_id;
        $tz = config('app.timezone');
        $today = Carbon::today($tz);
        $yesterday = (clone $today)->subDay();
        $monthStart = (clone $today)->startOfMonth();

        return $this->success([
            'tenant_user_id' => $tenantUserId,
            'generated_at' => now()->toIso8601String(),
            'today' => $this->salesWindow($tenantUserId, $today, (clone $today)->endOfDay()),
            'yesterday' => $this->salesWindow($tenantUserId, $yesterday, (clone $yesterday)->endOfDay()),
            'month_to_date' => $this->salesWindow($tenantUserId, $monthStart, (clone $today)->endOfDay()),
            'top_product_today' => $this->topProductToday($tenantUserId, $today),
            'inventory' => $this->inventorySummary($tenantUserId),
            'customers' => $this->customerSummary($tenantUserId),
        ]);
    }

    /**
     * @return array{date_from: string, date_to: string, sales_count: int, sales_total: float, profit: float, refund_count: int, refund_total: float}
     */
    private function salesWindow(int $tenantUserId, Carbon $from, Carbon $to): array
    {
        $sales = Sale::query()
            ->where('user_id', $tenantUserId)
            ->where('cancelled', 0)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('type, COUNT(*) as count, COALESCE(SUM(total), 0) as total, COALESCE(SUM(profit), 0) as profit')
            ->groupBy('type')
            ->get()
            ->keyBy('type');

        $sale = $sales->get(0);
        $refund = $sales->get(1);

        return [
            'date_from' => $from->toIso8601String(),
            'date_to' => $to->toIso8601String(),
            'sales_count' => (int) ($sale->count ?? 0),
            'sales_total' => round((float) ($sale->total ?? 0), 2),
            'profit' => round((float) ($sale->profit ?? 0), 2),
            'refund_count' => (int) ($refund->count ?? 0),
            'refund_total' => round((float) ($refund->total ?? 0), 2),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function topProductToday(int $tenantUserId, Carbon $today): ?array
    {
        $row = SaleLine::query()
            ->join('sales', 'sales.id', '=', 'sale_lines.sales_id')
            ->join('items', 'items.id', '=', 'sale_lines.item_id')
            ->where('sales.user_id', $tenantUserId)
            ->where('sales.cancelled', 0)
            ->where('sales.type', 0)
            ->whereDate('sales.created_at', $today)
            ->selectRaw('items.id as item_id, items.name, SUM(sale_lines.qty) as qty, SUM(sale_lines.sub_total) as revenue')
            ->groupBy('items.id', 'items.name')
            ->orderByDesc('qty')
            ->first();

        if ($row === null) {
            return null;
        }

        return [
            'item_id' => (int) $row->item_id,
            'name' => $row->name,
            'qty' => round((float) $row->qty, 2),
            'revenue' => round((float) $row->revenue, 2),
        ];
    }

    /**
     * @return array{low_stock_count: int, out_of_stock_count: int}
     */
    private function inventorySummary(int $tenantUserId): array
    {
        $effectiveSql = 'COALESCE(items.low_stock_threshold, '.self::LOW_STOCK_THRESHOLD.')';

        $base = ItemStore::query()
            ->join('items', 'item_stores.item_id', '=', 'items.id')
            ->where('items.status', true)
            ->where('items.user_id', $tenantUserId);

        return [
            'low_stock_count' => (clone $base)
                ->where('item_stores.stock', '>', 0)
                ->whereRaw("item_stores.stock <= {$effectiveSql}")
                ->count(),
            'out_of_stock_count' => (clone $base)
                ->where('item_stores.stock', '<=', 0)
                ->count(),
        ];
    }

    /**
     * @return array{outstanding_credit: float, active_credit_count: int, total_points_balance: float}
     */
    private function customerSummary(int $tenantUserId): array
    {
        $row = Customer::query()
            ->where('user_id', $tenantUserId)
            ->selectRaw('COALESCE(SUM(credit_balance), 0) as outstanding_credit, SUM(CASE WHEN credit_balance > 0 THEN 1 ELSE 0 END) as active_credit_count, COALESCE(SUM(points), 0) as total_points_balance')
            ->first();

        return [
            'outstanding_credit' => round((float) ($row->outstanding_credit ?? 0), 2),
            'active_credit_count' => (int) ($row->active_credit_count ?? 0),
            'total_points_balance' => round((float) ($row->total_points_balance ?? 0), 2),
        ];
    }
}
