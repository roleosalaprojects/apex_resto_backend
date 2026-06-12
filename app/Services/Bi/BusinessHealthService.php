<?php

namespace App\Services\Bi;

use App\Models\Bi\DailyCustomerMetric;
use App\Models\Bi\DailyItemMetric;
use App\Models\Bi\DailyStoreMetric;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

/**
 * Reads the pre-aggregated daily_*_metrics tables (rebuilt nightly by
 * `bi:aggregate-daily`) to serve the Business Health / P&L dashboard.
 * Never touches raw sales — data is only as fresh as the last
 * aggregation run, surfaced to the UI via `data_through`.
 *
 * P&L shape: net_sales - cogs ≈ gross_profit (the recorded sale profit
 * is authoritative), gross_profit - expenses = net_profit. Accrual
 * basis — credit/cheque sales count on the day sold, expenses on their
 * expense_date.
 */
class BusinessHealthService
{
    /**
     * @return array<string, mixed>
     */
    public function getDashboardData(int $userId, CarbonInterface $from, CarbonInterface $to, ?int $storeId = null): array
    {
        $current = $this->summarize($userId, $from, $to, $storeId);

        $periodDays = (int) $from->diffInDays($to) + 1;
        $previousTo = $from->copy()->subDay();
        $previousFrom = $previousTo->copy()->subDays($periodDays - 1);
        $previous = $this->summarize($userId, $previousFrom, $previousTo, $storeId);

        return [
            'summary' => $current,
            'previous' => $previous + [
                'from' => $previousFrom->toDateString(),
                'to' => $previousTo->toDateString(),
            ],
            'change_pct' => [
                'net_sales' => $this->changePct($current['net_sales'], $previous['net_sales']),
                'gross_profit' => $this->changePct($current['gross_profit'], $previous['gross_profit']),
                'net_profit' => $this->changePct($current['net_profit'], $previous['net_profit']),
                'transactions' => $this->changePct($current['transactions'], $previous['transactions']),
            ],
            'trend' => $this->trend($userId, $from, $to, $storeId),
            'payment_mix' => [
                'cash' => $current['cash_total'],
                'ewallet' => $current['ewallet_total'],
                'credit' => $current['credit_total'],
                'bank_transfer' => $current['bank_transfer_total'],
                'cheque' => $current['cheque_total'],
            ],
            'discounts' => [
                'regular' => $current['discount_total'],
                'senior_citizen' => $current['sc_discount_total'],
                'pwd' => $current['pwd_discount_total'],
                'solo_parent' => $current['sp_discount_total'],
                'naac' => $current['naac_discount_total'],
                'voucher' => $current['voucher_discount_total'],
            ],
            'channels' => [
                'instore_sales' => round($current['net_sales'] - $current['ecommerce_sales_total'], 2),
                'ecommerce_sales' => $current['ecommerce_sales_total'],
                'instore_transactions' => $current['transactions'] - $current['ecommerce_transactions'],
                'ecommerce_transactions' => $current['ecommerce_transactions'],
            ],
            'top_items' => $this->topItems($userId, $from, $to, $storeId),
            'top_customers' => $this->topCustomers($userId, $from, $to),
            'data_through' => DailyStoreMetric::forUser($userId)->max('date'),
        ];
    }

    /**
     * @return array<string, float|int>
     */
    protected function summarize(int $userId, CarbonInterface $from, CarbonInterface $to, ?int $storeId): array
    {
        $row = DailyStoreMetric::forUser($userId)
            ->betweenDates($from->toDateString(), $to->toDateString())
            ->when($storeId, fn ($query) => $query->where('store_id', $storeId))
            ->select(
                DB::raw('COALESCE(SUM(gross_sales), 0) as gross_sales'),
                DB::raw('COALESCE(SUM(refunds_total), 0) as refunds_total'),
                DB::raw('COALESCE(SUM(net_sales), 0) as net_sales'),
                DB::raw('COALESCE(SUM(profit), 0) as gross_profit'),
                DB::raw('COALESCE(SUM(cogs), 0) as cogs'),
                DB::raw('COALESCE(SUM(expenses_total), 0) as expenses_total'),
                DB::raw('COALESCE(SUM(discount_total), 0) as discount_total'),
                DB::raw('COALESCE(SUM(sc_discount_total), 0) as sc_discount_total'),
                DB::raw('COALESCE(SUM(pwd_discount_total), 0) as pwd_discount_total'),
                DB::raw('COALESCE(SUM(sp_discount_total), 0) as sp_discount_total'),
                DB::raw('COALESCE(SUM(naac_discount_total), 0) as naac_discount_total'),
                DB::raw('COALESCE(SUM(voucher_discount_total), 0) as voucher_discount_total'),
                DB::raw('COALESCE(SUM(cash_total), 0) as cash_total'),
                DB::raw('COALESCE(SUM(ewallet_total), 0) as ewallet_total'),
                DB::raw('COALESCE(SUM(credit_total), 0) as credit_total'),
                DB::raw('COALESCE(SUM(bank_transfer_total), 0) as bank_transfer_total'),
                DB::raw('COALESCE(SUM(cheque_total), 0) as cheque_total'),
                DB::raw('COALESCE(SUM(ecommerce_sales_total), 0) as ecommerce_sales_total'),
                DB::raw('COALESCE(SUM(transactions), 0) as transactions'),
                DB::raw('COALESCE(SUM(refund_count), 0) as refund_count'),
                DB::raw('COALESCE(SUM(ecommerce_transactions), 0) as ecommerce_transactions'),
            )
            ->first();

        $netSales = round((float) $row->net_sales, 2);
        $grossProfit = round((float) $row->gross_profit, 2);
        $expenses = round((float) $row->expenses_total, 2);
        $netProfit = round($grossProfit - $expenses, 2);
        $transactions = (int) $row->transactions;

        return [
            'gross_sales' => round((float) $row->gross_sales, 2),
            'refunds_total' => round((float) $row->refunds_total, 2),
            'net_sales' => $netSales,
            'cogs' => round((float) $row->cogs, 2),
            'gross_profit' => $grossProfit,
            'expenses_total' => $expenses,
            'net_profit' => $netProfit,
            'gross_margin_pct' => $netSales > 0 ? round($grossProfit / $netSales * 100, 2) : null,
            'net_margin_pct' => $netSales > 0 ? round($netProfit / $netSales * 100, 2) : null,
            'transactions' => $transactions,
            'refund_count' => (int) $row->refund_count,
            'avg_transaction_value' => $transactions > 0 ? round((float) $row->gross_sales / $transactions, 2) : null,
            'discount_total' => round((float) $row->discount_total, 2),
            'sc_discount_total' => round((float) $row->sc_discount_total, 2),
            'pwd_discount_total' => round((float) $row->pwd_discount_total, 2),
            'sp_discount_total' => round((float) $row->sp_discount_total, 2),
            'naac_discount_total' => round((float) $row->naac_discount_total, 2),
            'voucher_discount_total' => round((float) $row->voucher_discount_total, 2),
            'cash_total' => round((float) $row->cash_total, 2),
            'ewallet_total' => round((float) $row->ewallet_total, 2),
            'credit_total' => round((float) $row->credit_total, 2),
            'bank_transfer_total' => round((float) $row->bank_transfer_total, 2),
            'cheque_total' => round((float) $row->cheque_total, 2),
            'ecommerce_sales_total' => round((float) $row->ecommerce_sales_total, 2),
            'ecommerce_transactions' => (int) $row->ecommerce_transactions,
        ];
    }

    /**
     * Per-day P&L series with zero-filled gaps so the chart stays
     * contiguous.
     *
     * @return array<int, array{date: string, net_sales: float, gross_profit: float, expenses: float, net_profit: float}>
     */
    protected function trend(int $userId, CarbonInterface $from, CarbonInterface $to, ?int $storeId): array
    {
        $rows = DailyStoreMetric::forUser($userId)
            ->betweenDates($from->toDateString(), $to->toDateString())
            ->when($storeId, fn ($query) => $query->where('store_id', $storeId))
            ->select(
                'date',
                DB::raw('SUM(net_sales) as net_sales'),
                DB::raw('SUM(profit) as gross_profit'),
                DB::raw('SUM(expenses_total) as expenses'),
            )
            ->groupBy('date')
            ->get()
            ->keyBy(fn ($row) => $row->date->toDateString());

        $trend = [];

        foreach (CarbonPeriod::create($from->toDateString(), $to->toDateString()) as $day) {
            $key = $day->toDateString();
            $row = $rows->get($key);

            $netSales = $row ? round((float) $row->net_sales, 2) : 0.0;
            $grossProfit = $row ? round((float) $row->gross_profit, 2) : 0.0;
            $expenses = $row ? round((float) $row->expenses, 2) : 0.0;

            $trend[] = [
                'date' => $key,
                'net_sales' => $netSales,
                'gross_profit' => $grossProfit,
                'expenses' => $expenses,
                'net_profit' => round($grossProfit - $expenses, 2),
            ];
        }

        return $trend;
    }

    /**
     * Full per-day P&L rows for CSV export — wider than trend() and
     * zero-filled the same way so the export covers every day in the
     * requested range.
     *
     * @return array<int, array{date: string, gross_sales: float, refunds_total: float, net_sales: float, cogs: float, gross_profit: float, expenses_total: float, net_profit: float, transactions: int, refund_count: int}>
     */
    public function getDailyPnlRows(int $userId, CarbonInterface $from, CarbonInterface $to, ?int $storeId = null): array
    {
        $rows = DailyStoreMetric::forUser($userId)
            ->betweenDates($from->toDateString(), $to->toDateString())
            ->when($storeId, fn ($query) => $query->where('store_id', $storeId))
            ->select(
                'date',
                DB::raw('SUM(gross_sales) as gross_sales'),
                DB::raw('SUM(refunds_total) as refunds_total'),
                DB::raw('SUM(net_sales) as net_sales'),
                DB::raw('SUM(cogs) as cogs'),
                DB::raw('SUM(profit) as gross_profit'),
                DB::raw('SUM(expenses_total) as expenses_total'),
                DB::raw('SUM(transactions) as transactions'),
                DB::raw('SUM(refund_count) as refund_count'),
            )
            ->groupBy('date')
            ->get()
            ->keyBy(fn ($row) => $row->date->toDateString());

        $daily = [];

        foreach (CarbonPeriod::create($from->toDateString(), $to->toDateString()) as $day) {
            $key = $day->toDateString();
            $row = $rows->get($key);

            $grossProfit = $row ? round((float) $row->gross_profit, 2) : 0.0;
            $expenses = $row ? round((float) $row->expenses_total, 2) : 0.0;

            $daily[] = [
                'date' => $key,
                'gross_sales' => $row ? round((float) $row->gross_sales, 2) : 0.0,
                'refunds_total' => $row ? round((float) $row->refunds_total, 2) : 0.0,
                'net_sales' => $row ? round((float) $row->net_sales, 2) : 0.0,
                'cogs' => $row ? round((float) $row->cogs, 2) : 0.0,
                'gross_profit' => $grossProfit,
                'expenses_total' => $expenses,
                'net_profit' => round($grossProfit - $expenses, 2),
                'transactions' => $row ? (int) $row->transactions : 0,
                'refund_count' => $row ? (int) $row->refund_count : 0,
            ];
        }

        return $daily;
    }

    /**
     * @return array<int, array{item_id: int, name: string, qty_sold: float, revenue: float, profit: float}>
     */
    protected function topItems(int $userId, CarbonInterface $from, CarbonInterface $to, ?int $storeId, int $limit = 10): array
    {
        return DailyItemMetric::query()
            ->join('items', 'items.id', '=', 'daily_item_metrics.item_id')
            ->where('daily_item_metrics.user_id', $userId)
            ->whereBetween('daily_item_metrics.date', [$from->toDateString(), $to->toDateString()])
            ->when($storeId, fn ($query) => $query->where('daily_item_metrics.store_id', $storeId))
            ->select(
                'daily_item_metrics.item_id',
                'items.name',
                DB::raw('SUM(daily_item_metrics.qty_sold) as qty_sold'),
                DB::raw('SUM(daily_item_metrics.revenue) as revenue'),
                DB::raw('SUM(daily_item_metrics.profit) as profit'),
            )
            ->groupBy('daily_item_metrics.item_id', 'items.name')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'item_id' => (int) $row->item_id,
                'name' => $row->name,
                'qty_sold' => round((float) $row->qty_sold, 2),
                'revenue' => round((float) $row->revenue, 2),
                'profit' => round((float) $row->profit, 2),
            ])
            ->all();
    }

    /**
     * Customer metrics carry no store dimension, so this list is always
     * tenant-wide — the UI labels it "(all stores)".
     *
     * @return array<int, array{customer_id: int, name: string, transactions: int, spend_total: float, profit: float}>
     */
    protected function topCustomers(int $userId, CarbonInterface $from, CarbonInterface $to, int $limit = 10): array
    {
        return DailyCustomerMetric::query()
            ->join('customers', 'customers.id', '=', 'daily_customer_metrics.customer_id')
            ->where('daily_customer_metrics.user_id', $userId)
            ->whereBetween('daily_customer_metrics.date', [$from->toDateString(), $to->toDateString()])
            ->select(
                'daily_customer_metrics.customer_id',
                'customers.name',
                DB::raw('SUM(daily_customer_metrics.transactions) as transactions'),
                DB::raw('SUM(daily_customer_metrics.spend_total) as spend_total'),
                DB::raw('SUM(daily_customer_metrics.profit) as profit'),
            )
            ->groupBy('daily_customer_metrics.customer_id', 'customers.name')
            ->orderByDesc('spend_total')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'customer_id' => (int) $row->customer_id,
                'name' => $row->name,
                'transactions' => (int) $row->transactions,
                'spend_total' => round((float) $row->spend_total, 2),
                'profit' => round((float) $row->profit, 2),
            ])
            ->all();
    }

    protected function changePct(float|int $current, float|int $previous): ?float
    {
        if ((float) $previous == 0.0) {
            return null;
        }

        return round(($current - $previous) / abs($previous) * 100, 2);
    }
}
