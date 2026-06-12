<?php

namespace App\Http\Controllers\API\v1\mobile;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\InventoryManagement\Supplier;
use App\Models\Pos\Sale;
use App\Models\Products\Category;
use App\Models\Products\ItemStore;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportsController extends Controller
{
    use ApiResponse;

    private const LOW_STOCK_THRESHOLD = 10;

    /**
     * Get inventory report.
     */
    public function inventory(Request $request): JsonResponse
    {
        $query = ItemStore::query()
            ->join('items', 'item_stores.item_id', '=', 'items.id')
            ->leftJoin('categories', 'items.category_id', '=', 'categories.id')
            ->where('items.status', true);

        if ($request->filled('store_id')) {
            $query->where('item_stores.store_id', $request->input('store_id'));
        }

        if ($request->filled('category_id')) {
            $query->where('items.category_id', $request->input('category_id'));
        }

        if ($request->boolean('low_stock')) {
            $query->where('item_stores.stock', '<=', self::LOW_STOCK_THRESHOLD)
                ->where('item_stores.stock', '>', 0);
        }

        $items = $query
            ->select([
                'items.id',
                'items.name',
                'items.barcode',
                'item_stores.stock as current_stock',
                'items.cost as unit_cost',
                DB::raw('(item_stores.stock * items.cost) as stock_value'),
                'categories.name as category',
            ])
            ->orderBy('items.name')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'barcode' => $item->barcode,
                    'current_stock' => (float) $item->current_stock,
                    'reorder_point' => self::LOW_STOCK_THRESHOLD,
                    'unit_cost' => round((float) $item->unit_cost, 2),
                    'stock_value' => round((float) $item->stock_value, 2),
                    'category' => $item->category,
                ];
            });

        $summary = $this->getInventorySummary($request->input('store_id'));

        return $this->success([
            'items' => $items,
            'summary' => $summary,
        ]);
    }

    /**
     * Get low stock alerts.
     */
    public function lowStock(Request $request): JsonResponse
    {
        $limit = $request->input('limit', 20);

        $query = ItemStore::query()
            ->join('items', 'item_stores.item_id', '=', 'items.id')
            ->where('items.status', true)
            ->where('item_stores.stock', '<=', self::LOW_STOCK_THRESHOLD)
            ->where('item_stores.stock', '>', 0);

        if ($request->filled('store_id')) {
            $query->where('item_stores.store_id', $request->input('store_id'));
        }

        $items = $query
            ->select([
                'items.id',
                'items.name',
                'item_stores.stock as current_stock',
            ])
            ->orderBy('item_stores.stock', 'asc')
            ->limit($limit)
            ->get()
            ->map(function ($item) {
                $suggestedOrder = (self::LOW_STOCK_THRESHOLD * 2) - $item->current_stock;

                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'current_stock' => (float) $item->current_stock,
                    'reorder_point' => self::LOW_STOCK_THRESHOLD,
                    'suggested_order_quantity' => max($suggestedOrder, 0),
                ];
            });

        return $this->success(['items' => $items]);
    }

    /**
     * Get supplier report.
     */
    public function suppliers(Request $request): JsonResponse
    {
        [$startDate, $endDate] = $this->getDateRange($request);

        $query = Supplier::query()
            ->leftJoin('purchases', function ($join) use ($startDate, $endDate) {
                $join->on('suppliers.id', '=', 'purchases.supplier_id')
                    ->whereBetween('purchases.created_at', [$startDate, $endDate]);
            })
            ->where('suppliers.status', true);

        if ($request->filled('supplier_id')) {
            $query->where('suppliers.id', $request->input('supplier_id'));
        }

        $suppliers = $query
            ->select([
                'suppliers.id',
                'suppliers.name',
                DB::raw('COALESCE(SUM(purchases.total), 0) as total_purchase_value'),
                DB::raw('COUNT(purchases.id) as order_count'),
                DB::raw('COALESCE(AVG(purchases.total), 0) as average_order_value'),
                DB::raw('MAX(purchases.created_at) as last_order_date'),
            ])
            ->groupBy('suppliers.id', 'suppliers.name')
            ->orderByDesc('total_purchase_value')
            ->get()
            ->map(function ($supplier) {
                return [
                    'id' => $supplier->id,
                    'name' => $supplier->name,
                    'total_purchase_value' => round((float) $supplier->total_purchase_value, 2),
                    'order_count' => (int) $supplier->order_count,
                    'average_order_value' => round((float) $supplier->average_order_value, 2),
                    'last_order_date' => $supplier->last_order_date
                        ? Carbon::parse($supplier->last_order_date)->toDateString()
                        : null,
                ];
            });

        $totalPurchaseValue = $suppliers->sum('total_purchase_value');

        return $this->success([
            'suppliers' => $suppliers,
            'summary' => [
                'total_suppliers' => $suppliers->count(),
                'total_purchase_value' => round($totalPurchaseValue, 2),
            ],
        ]);
    }

    /**
     * Get category performance report.
     */
    public function categories(Request $request): JsonResponse
    {
        [$startDate, $endDate] = $this->getDateRange($request);

        // Build subquery for sales data filtered by date
        $salesSubquery = DB::table('sale_lines')
            ->join('sales', function ($join) use ($startDate, $endDate, $request) {
                $join->on('sale_lines.sales_id', '=', 'sales.id')
                    ->where('sales.cancelled', false)
                    ->where('sales.type', 0)
                    ->whereBetween('sales.created_at', [$startDate, $endDate]);

                if ($request->filled('store_id')) {
                    $join->where('sales.store_id', $request->input('store_id'));
                }
            })
            ->join('items', 'sale_lines.item_id', '=', 'items.id')
            ->select(
                'items.category_id',
                DB::raw('SUM(sale_lines.sub_total) as total_sales'),
                DB::raw('SUM(sale_lines.qty * sale_lines.unit_qty) as items_sold')
            )
            ->groupBy('items.category_id');

        // Get product counts per category
        $productCounts = DB::table('items')
            ->where('status', true)
            ->select('category_id', DB::raw('COUNT(*) as product_count'))
            ->groupBy('category_id');

        $categories = Category::query()
            ->leftJoinSub($salesSubquery, 'sales_data', 'categories.id', '=', 'sales_data.category_id')
            ->leftJoinSub($productCounts, 'product_data', 'categories.id', '=', 'product_data.category_id')
            ->select([
                'categories.id',
                'categories.name',
                DB::raw('COALESCE(sales_data.total_sales, 0) as total_sales'),
                DB::raw('COALESCE(sales_data.items_sold, 0) as items_sold'),
                DB::raw('COALESCE(product_data.product_count, 0) as product_count'),
            ])
            ->orderByDesc('total_sales')
            ->get();

        $totalSales = $categories->sum('total_sales');
        $totalItemsSold = $categories->sum('items_sold');

        $categoriesWithPercentage = $categories->map(function ($category) use ($totalSales) {
            $percentage = $totalSales > 0 ? ($category->total_sales / $totalSales) * 100 : 0;

            return [
                'id' => $category->id,
                'name' => $category->name,
                'total_sales' => round((float) $category->total_sales, 2),
                'items_sold' => (int) $category->items_sold,
                'product_count' => (int) $category->product_count,
                'percentage_of_total' => round($percentage, 1),
            ];
        });

        $categoryCount = $categories->count();

        return $this->success([
            'categories' => $categoriesWithPercentage,
            'summary' => [
                'total_categories' => $categoryCount,
                'total_sales' => round($totalSales, 2),
                'total_items_sold' => (int) $totalItemsSold,
                'average_per_category' => $categoryCount > 0 ? round($totalSales / $categoryCount, 2) : 0,
            ],
        ]);
    }

    /**
     * Get category items with sales breakdown.
     */
    public function categoryItems(Request $request, int $categoryId): JsonResponse
    {
        [$startDate, $endDate] = $this->getDateRange($request);

        $category = Category::find($categoryId);

        if (! $category) {
            return $this->error('Category not found', 404);
        }

        // Build subquery for sales data filtered by date
        $salesSubquery = DB::table('sale_lines')
            ->join('sales', function ($join) use ($startDate, $endDate, $request) {
                $join->on('sale_lines.sales_id', '=', 'sales.id')
                    ->where('sales.cancelled', false)
                    ->where('sales.type', 0)
                    ->whereBetween('sales.created_at', [$startDate, $endDate]);

                if ($request->filled('store_id')) {
                    $join->where('sales.store_id', $request->input('store_id'));
                }
            })
            ->select(
                'sale_lines.item_id',
                DB::raw('SUM(sale_lines.sub_total) as total_sales'),
                DB::raw('SUM(sale_lines.qty * sale_lines.unit_qty) as items_sold')
            )
            ->groupBy('sale_lines.item_id');

        // Get items with sales data for this category
        $itemsQuery = DB::table('items')
            ->leftJoinSub($salesSubquery, 'sales_data', 'items.id', '=', 'sales_data.item_id')
            ->where('items.category_id', $categoryId)
            ->where('items.status', true)
            ->select([
                'items.id',
                'items.name',
                'items.barcode',
                DB::raw('COALESCE(sales_data.total_sales, 0) as total_sales'),
                DB::raw('COALESCE(sales_data.items_sold, 0) as items_sold'),
            ])
            ->orderByDesc('total_sales')
            ->get();

        $totalCategorySales = $itemsQuery->sum('total_sales');

        $items = $itemsQuery->map(function ($item) use ($totalCategorySales) {
            $percentage = $totalCategorySales > 0 ? ($item->total_sales / $totalCategorySales) * 100 : 0;

            return [
                'id' => $item->id,
                'name' => $item->name,
                'barcode' => $item->barcode,
                'total_sales' => round((float) $item->total_sales, 2),
                'items_sold' => (int) $item->items_sold,
                'percentage_of_category' => round($percentage, 1),
            ];
        });

        return $this->success([
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
            ],
            'items' => $items,
            'summary' => [
                'total_items' => $items->count(),
                'total_sales' => round($totalCategorySales, 2),
                'total_quantity_sold' => (int) $itemsQuery->sum('items_sold'),
            ],
        ]);
    }

    /**
     * Get refund report.
     */
    public function refunds(Request $request): JsonResponse
    {
        $limit = $request->input('limit', 50);
        $offset = $request->input('offset', 0);
        [$startDate, $endDate] = $this->getDateRange($request);

        $query = Sale::query()
            ->with(['customer:id,name', 'sold_by:id,name', 'refund:id,son'])
            ->where('type', 1)
            ->where('cancelled', false)
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($request->filled('store_id')) {
            $query->where('store_id', $request->input('store_id'));
        }

        $total = $query->count();

        $refunds = $query
            ->orderByDesc('created_at')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->map(function ($refund) {
                return [
                    'id' => $refund->id,
                    'refund_number' => $refund->son,
                    'original_sale_number' => $refund->refund?->son,
                    'customer_name' => $refund->customer?->name ?? 'Walk-in',
                    'reason' => 'Not specified',
                    'amount' => round((float) $refund->total, 2),
                    'item_count' => $refund->lines_count ?? 0,
                    'created_at' => $refund->created_at->toIso8601String(),
                    'processed_by' => $refund->sold_by?->name,
                ];
            });

        return $this->success([
            'refunds' => $refunds,
            'pagination' => [
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
                'has_more' => ($offset + $limit) < $total,
            ],
        ]);
    }

    /**
     * Get refund summary.
     */
    public function refundSummary(Request $request): JsonResponse
    {
        [$startDate, $endDate] = $this->getDateRange($request);

        $refundStats = Sale::query()
            ->where('type', 1)
            ->where('cancelled', false)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->select([
                DB::raw('SUM(total) as total_refund_amount'),
                DB::raw('COUNT(id) as refund_count'),
                DB::raw('AVG(total) as average_refund'),
            ])
            ->first();

        $salesCount = Sale::query()
            ->where('type', 0)
            ->where('cancelled', false)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        $refundCount = (int) ($refundStats->refund_count ?? 0);
        $refundRate = $salesCount > 0 ? ($refundCount / $salesCount) * 100 : 0;

        $reasonsBreakdown = $refundCount > 0
            ? [[
                'reason' => 'Not specified',
                'count' => $refundCount,
                'amount' => round((float) ($refundStats->total_refund_amount ?? 0), 2),
                'percentage' => 100.0,
            ]]
            : [];

        return $this->success([
            'total_refund_amount' => round((float) ($refundStats->total_refund_amount ?? 0), 2),
            'refund_count' => $refundCount,
            'refund_rate' => round($refundRate, 1),
            'average_refund' => round((float) ($refundStats->average_refund ?? 0), 2),
            'reasons_breakdown' => $reasonsBreakdown,
        ]);
    }

    /**
     * Get date range from request or default to current month.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function getDateRange(Request $request): array
    {
        $startDate = $request->filled('start_date')
            ? Carbon::parse($request->input('start_date'))->startOfDay()
            : Carbon::now()->startOfMonth();

        $endDate = $request->filled('end_date')
            ? Carbon::parse($request->input('end_date'))->endOfDay()
            : Carbon::now()->endOfDay();

        return [$startDate, $endDate];
    }

    /**
     * Get inventory summary.
     *
     * @return array{total_products: int, total_stock_value: float, low_stock_count: int, out_of_stock_count: int}
     */
    private function getInventorySummary(?int $storeId): array
    {
        $query = ItemStore::query()
            ->join('items', 'item_stores.item_id', '=', 'items.id')
            ->where('items.status', true);

        if ($storeId) {
            $query->where('item_stores.store_id', $storeId);
        }

        $stats = $query
            ->select([
                DB::raw('COUNT(DISTINCT items.id) as total_products'),
                DB::raw('SUM(item_stores.stock * items.cost) as total_stock_value'),
                DB::raw('SUM(CASE WHEN item_stores.stock <= '.self::LOW_STOCK_THRESHOLD.' AND item_stores.stock > 0 THEN 1 ELSE 0 END) as low_stock_count'),
                DB::raw('SUM(CASE WHEN item_stores.stock = 0 THEN 1 ELSE 0 END) as out_of_stock_count'),
            ])
            ->first();

        return [
            'total_products' => (int) ($stats->total_products ?? 0),
            'total_stock_value' => round((float) ($stats->total_stock_value ?? 0), 2),
            'low_stock_count' => (int) ($stats->low_stock_count ?? 0),
            'out_of_stock_count' => (int) ($stats->out_of_stock_count ?? 0),
        ];
    }
}
