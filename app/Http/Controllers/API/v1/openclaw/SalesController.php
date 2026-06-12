<?php

namespace App\Http\Controllers\API\v1\openclaw;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Pos\Sale;
use App\Models\Pos\SaleLine;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesController extends Controller
{
    use ApiResponse;

    /**
     * Aggregate totals + daily breakdown over a date range.
     */
    public function summary(Request $request): JsonResponse
    {
        [$from, $to] = $this->resolveRange($request);
        $tenantUserId = (int) auth()->user()->user_id;
        $storeId = $request->filled('store_id') ? (int) $request->input('store_id') : null;

        $base = Sale::query()
            ->where('user_id', $tenantUserId)
            ->where('cancelled', 0)
            ->whereBetween('created_at', [$from, $to])
            ->when($storeId !== null, fn ($q) => $q->where('store_id', $storeId));

        $totals = (clone $base)
            ->selectRaw('type, COUNT(*) as count, COALESCE(SUM(total), 0) as total, COALESCE(SUM(profit), 0) as profit, COALESCE(SUM(discount), 0) as discount')
            ->groupBy('type')
            ->get()
            ->keyBy('type');

        $sale = $totals->get(0);
        $refund = $totals->get(1);

        $daily = (clone $base)
            ->where('type', 0)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as count, COALESCE(SUM(total), 0) as total, COALESCE(SUM(profit), 0) as profit')
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->map(fn ($row) => [
                'date' => $row->day,
                'count' => (int) $row->count,
                'total' => round((float) $row->total, 2),
                'profit' => round((float) $row->profit, 2),
            ]);

        return $this->success([
            'date_from' => $from->toIso8601String(),
            'date_to' => $to->toIso8601String(),
            'store_id' => $storeId,
            'sales' => [
                'count' => (int) ($sale->count ?? 0),
                'total' => round((float) ($sale->total ?? 0), 2),
                'profit' => round((float) ($sale->profit ?? 0), 2),
                'discount' => round((float) ($sale->discount ?? 0), 2),
            ],
            'refunds' => [
                'count' => (int) ($refund->count ?? 0),
                'total' => round((float) ($refund->total ?? 0), 2),
            ],
            'daily' => $daily,
        ]);
    }

    /**
     * Sales aggregated by item (qty + revenue + profit).
     */
    public function byItem(Request $request): JsonResponse
    {
        [$from, $to] = $this->resolveRange($request);
        $tenantUserId = (int) auth()->user()->user_id;
        $limit = (int) $request->input('limit', 50);
        $storeId = $request->filled('store_id') ? (int) $request->input('store_id') : null;

        $rows = SaleLine::query()
            ->join('sales', 'sales.id', '=', 'sale_lines.sales_id')
            ->join('items', 'items.id', '=', 'sale_lines.item_id')
            ->where('sales.user_id', $tenantUserId)
            ->where('sales.cancelled', 0)
            ->where('sales.type', 0)
            ->whereBetween('sales.created_at', [$from, $to])
            ->when($storeId !== null, fn ($q) => $q->where('sales.store_id', $storeId))
            ->selectRaw('items.id as item_id, items.name, items.barcode, SUM(sale_lines.qty) as qty, SUM(sale_lines.sub_total) as revenue, SUM(sale_lines.profit) as profit')
            ->groupBy('items.id', 'items.name', 'items.barcode')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => [
                'item_id' => (int) $r->item_id,
                'name' => $r->name,
                'barcode' => $r->barcode,
                'qty' => round((float) $r->qty, 2),
                'revenue' => round((float) $r->revenue, 2),
                'profit' => round((float) $r->profit, 2),
            ]);

        return $this->success([
            'date_from' => $from->toIso8601String(),
            'date_to' => $to->toIso8601String(),
            'store_id' => $storeId,
            'limit' => $limit,
            'items' => $rows,
        ]);
    }

    /**
     * Refund transactions in the window with line item context.
     */
    public function refunds(Request $request): JsonResponse
    {
        [$from, $to] = $this->resolveRange($request);
        $tenantUserId = (int) auth()->user()->user_id;
        $limit = (int) $request->input('limit', 100);
        $storeId = $request->filled('store_id') ? (int) $request->input('store_id') : null;

        $refunds = Sale::query()
            ->with(['store:id,name', 'sold_by:id,name', 'customer:id,name,code'])
            ->where('user_id', $tenantUserId)
            ->where('cancelled', 0)
            ->where('type', 1)
            ->whereBetween('created_at', [$from, $to])
            ->when($storeId !== null, fn ($q) => $q->where('store_id', $storeId))
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (Sale $s) => [
                'id' => $s->id,
                'son' => $s->son,
                'total' => round((float) $s->total, 2),
                'sale_id' => $s->sale_id,
                'store_id' => $s->store_id,
                'store_name' => $s->store?->name,
                'cashier_name' => $s->sold_by?->name,
                'customer_name' => $s->customer?->name,
                'created_at' => $s->created_at?->toIso8601String(),
            ]);

        return $this->success([
            'date_from' => $from->toIso8601String(),
            'date_to' => $to->toIso8601String(),
            'store_id' => $storeId,
            'limit' => $limit,
            'refunds' => $refunds,
        ]);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveRange(Request $request): array
    {
        $request->validate([
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'store_id' => 'nullable|integer|min:1',
            'limit' => 'nullable|integer|min:1|max:1000',
        ]);

        $tz = config('app.timezone');
        $to = $request->filled('date_to')
            ? Carbon::parse($request->input('date_to'), $tz)->endOfDay()
            : Carbon::today($tz)->endOfDay();
        $from = $request->filled('date_from')
            ? Carbon::parse($request->input('date_from'), $tz)->startOfDay()
            : (clone $to)->subDays(6)->startOfDay();

        return [$from, $to];
    }
}
