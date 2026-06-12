<?php

namespace App\Services\Bi;

use App\Models\Accounting\Expense;
use App\Models\Bi\DailyCustomerMetric;
use App\Models\Bi\DailyItemMetric;
use App\Models\Bi\DailyStoreMetric;
use App\Models\Pos\Sale;
use App\Models\Pos\SaleLine;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Rebuilds the daily BI summary tables (daily_store_metrics,
 * daily_item_metrics, daily_customer_metrics) from raw sales,
 * sale_lines and expenses.
 *
 * Semantics mirror ReportService::aggregateSales() / getSoldItems():
 * refund rows (type = 1) store positive totals/profit, so gross columns
 * sum type = 0 only, refund columns sum type = 1 only, and profit/COGS
 * are signed nets (type = 0 adds, type = 1 subtracts). net_sales is
 * computed as gross_sales - refunds_total. Discount, VAT and payment
 * columns are signed nets. cancelled = 1 sales are excluded everywhere.
 *
 * Timestamps round-trip as Manila local time (see
 * PeakHoursAnalysisService::localExpr()), so DATE(created_at) groups by
 * Manila calendar day with no CONVERT_TZ.
 *
 * Expenses are merged on the accrual basis: an expense counts on its
 * expense_date and credit/cheque sales count on the day sold. A future
 * P&L layer should make the cash-vs-accrual choice deliberately.
 *
 * Rebuilds are delete-then-insert per (date range, tenant) inside one
 * transaction, so re-running a range is idempotent and rows disappear
 * when every sale for a day is retroactively cancelled.
 */
class DailyAggregationService
{
    protected const INSERT_CHUNK = 500;

    /**
     * Re-aggregate every day in [$from, $to] (inclusive, Manila days).
     * Returns the number of summary rows written across all three tables.
     */
    public function aggregateRange(CarbonInterface $from, CarbonInterface $to, ?int $userId = null): int
    {
        $fromDate = $from->toDateString();
        $toDate = $to->toDateString();
        $start = $fromDate.' 00:00:00';
        $end = $toDate.' 23:59:59';

        $storeRows = $this->buildStoreMetricRows($start, $end, $fromDate, $toDate, $userId);
        $itemRows = $this->buildItemMetricRows($start, $end, $userId);
        $customerRows = $this->buildCustomerMetricRows($start, $end, $userId);

        DB::transaction(function () use ($fromDate, $toDate, $userId, $storeRows, $itemRows, $customerRows) {
            $this->replaceRows(DailyStoreMetric::query(), $fromDate, $toDate, $userId, $storeRows);
            $this->replaceRows(DailyItemMetric::query(), $fromDate, $toDate, $userId, $itemRows);
            $this->replaceRows(DailyCustomerMetric::query(), $fromDate, $toDate, $userId, $customerRows);
        });

        return count($storeRows) + count($itemRows) + count($customerRows);
    }

    /**
     * Re-aggregate a single Manila day.
     */
    public function aggregateDate(CarbonInterface $date, ?int $userId = null): int
    {
        return $this->aggregateRange($date, $date, $userId);
    }

    /**
     * Earliest non-cancelled sale date (Manila day), for backfills.
     */
    public function earliestSaleDate(?int $userId = null): ?Carbon
    {
        $min = Sale::query()
            ->where('cancelled', 0)
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->min(DB::raw('DATE(created_at)'));

        return $min ? Carbon::parse($min, config('app.timezone')) : null;
    }

    /**
     * Store metrics merge three grouped queries keyed "user|store|date":
     * the sales header aggregate, the sale_lines COGS rollup, and the
     * expenses rollup (expenses join stores for the tenant id; voided
     * and store-less expenses are skipped). Expense-only days still get
     * a row so the P&L can read a single table.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function buildStoreMetricRows(string $start, string $end, string $fromDate, string $toDate, ?int $userId): array
    {
        $signedTotal = 'IF(type = 0, total, -total)';

        $sales = Sale::query()
            ->where('cancelled', 0)
            ->whereBetween('created_at', [$start, $end])
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->select(
                'user_id',
                'store_id',
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(IF(type = 0, total, 0)) as gross_sales'),
                DB::raw('SUM(IF(type = 1, total, 0)) as refunds_total'),
                DB::raw('SUM(IF(type = 0, profit, -profit)) as profit'),
                DB::raw('SUM(IF(type = 0, discount, -discount)) as discount_total'),
                DB::raw('SUM(IF(type = 0, sc_discount, -sc_discount)) as sc_discount_total'),
                DB::raw('SUM(IF(type = 0, pwd_discount, -pwd_discount)) as pwd_discount_total'),
                DB::raw('SUM(IF(type = 0, sp_discount, -sp_discount)) as sp_discount_total'),
                DB::raw('SUM(IF(type = 0, naac_discount, -naac_discount)) as naac_discount_total'),
                DB::raw('SUM(IF(type = 0, voucher_discount, -voucher_discount)) as voucher_discount_total'),
                DB::raw('SUM(IF(type = 0, vatable, -vatable)) as vatable_total'),
                DB::raw('SUM(IF(type = 0, vat, -vat)) as vat_total'),
                DB::raw('SUM(IF(type = 0, non_vat, -non_vat)) as non_vat_total'),
                DB::raw('SUM(IF(type = 0, zero_rated, -zero_rated)) as zero_rated_total'),
                DB::raw('SUM(IF(COALESCE(payment_type, 1) = '.Sale::PAYMENT_CASH.', '.$signedTotal.', 0)) as cash_total'),
                DB::raw('SUM(IF(COALESCE(payment_type, 1) = '.Sale::PAYMENT_EWALLET.', '.$signedTotal.', 0)) as ewallet_total'),
                DB::raw('SUM(IF(COALESCE(payment_type, 1) = '.Sale::PAYMENT_CREDIT.', '.$signedTotal.', 0)) as credit_total'),
                DB::raw('SUM(IF(COALESCE(payment_type, 1) = '.Sale::PAYMENT_BANK_TRANSFER.', '.$signedTotal.', 0)) as bank_transfer_total'),
                DB::raw('SUM(IF(COALESCE(payment_type, 1) = '.Sale::PAYMENT_CHEQUE.', '.$signedTotal.', 0)) as cheque_total'),
                DB::raw('SUM(IF(ecommerce_order_id IS NOT NULL, '.$signedTotal.', 0)) as ecommerce_sales_total'),
                DB::raw('SUM(IF(type = 0, 1, 0)) as transactions'),
                DB::raw('SUM(IF(type = 1, 1, 0)) as refund_count'),
                DB::raw('SUM(IF(ecommerce_order_id IS NOT NULL AND type = 0, 1, 0)) as ecommerce_transactions'),
            )
            ->groupBy('user_id', 'store_id', DB::raw('DATE(created_at)'))
            ->get();

        $cogs = SaleLine::query()
            ->join('sales as s', 's.id', '=', 'sale_lines.sales_id')
            ->where('s.cancelled', 0)
            ->whereBetween('s.created_at', [$start, $end])
            ->when($userId, fn ($query) => $query->where('s.user_id', $userId))
            ->select(
                's.user_id',
                's.store_id',
                DB::raw('DATE(s.created_at) as date'),
                DB::raw('SUM(IF(s.type = 0, sale_lines.qty * sale_lines.unit_qty * sale_lines.cost, -(sale_lines.qty * sale_lines.unit_qty * sale_lines.cost))) as cogs'),
            )
            ->groupBy('s.user_id', 's.store_id', DB::raw('DATE(s.created_at)'))
            ->get();

        $expenses = Expense::query()
            ->join('stores', 'stores.id', '=', 'expenses.store_id')
            ->where('expenses.status', Expense::STATUS_ACTIVE)
            ->whereNotNull('expenses.store_id')
            ->whereBetween('expenses.expense_date', [$fromDate, $toDate])
            ->when($userId, fn ($query) => $query->where('stores.user_id', $userId))
            ->select(
                'stores.user_id',
                'expenses.store_id',
                DB::raw('DATE(expenses.expense_date) as date'),
                DB::raw('SUM(expenses.amount) as expenses_total'),
            )
            ->groupBy('stores.user_id', 'expenses.store_id', DB::raw('DATE(expenses.expense_date)'))
            ->get();

        $rows = [];

        foreach ($sales as $row) {
            $key = $row->user_id.'|'.$row->store_id.'|'.$row->date;
            $gross = round((float) $row->gross_sales, 2);
            $refunds = round((float) $row->refunds_total, 2);

            $rows[$key] = array_merge($this->storeRowDefaults((int) $row->user_id, (int) $row->store_id, $row->date), [
                'gross_sales' => $gross,
                'refunds_total' => $refunds,
                'net_sales' => round($gross - $refunds, 2),
                'profit' => round((float) $row->profit, 2),
                'discount_total' => round((float) $row->discount_total, 2),
                'sc_discount_total' => round((float) $row->sc_discount_total, 2),
                'pwd_discount_total' => round((float) $row->pwd_discount_total, 2),
                'sp_discount_total' => round((float) $row->sp_discount_total, 2),
                'naac_discount_total' => round((float) $row->naac_discount_total, 2),
                'voucher_discount_total' => round((float) $row->voucher_discount_total, 2),
                'vatable_total' => round((float) $row->vatable_total, 2),
                'vat_total' => round((float) $row->vat_total, 2),
                'non_vat_total' => round((float) $row->non_vat_total, 2),
                'zero_rated_total' => round((float) $row->zero_rated_total, 2),
                'cash_total' => round((float) $row->cash_total, 2),
                'ewallet_total' => round((float) $row->ewallet_total, 2),
                'credit_total' => round((float) $row->credit_total, 2),
                'bank_transfer_total' => round((float) $row->bank_transfer_total, 2),
                'cheque_total' => round((float) $row->cheque_total, 2),
                'ecommerce_sales_total' => round((float) $row->ecommerce_sales_total, 2),
                'transactions' => (int) $row->transactions,
                'refund_count' => (int) $row->refund_count,
                'ecommerce_transactions' => (int) $row->ecommerce_transactions,
            ]);
        }

        foreach ($cogs as $row) {
            $key = $row->user_id.'|'.$row->store_id.'|'.$row->date;
            $rows[$key] ??= $this->storeRowDefaults((int) $row->user_id, (int) $row->store_id, $row->date);
            $rows[$key]['cogs'] = round((float) $row->cogs, 2);
        }

        foreach ($expenses as $row) {
            $key = $row->user_id.'|'.$row->store_id.'|'.$row->date;
            $rows[$key] ??= $this->storeRowDefaults((int) $row->user_id, (int) $row->store_id, $row->date);
            $rows[$key]['expenses_total'] = round((float) $row->expenses_total, 2);
        }

        return array_values($rows);
    }

    /**
     * Sale-side columns (qty_sold, revenue, cost_total, discount_total,
     * transactions) sum type = 0 only; refund_qty/refund_total sum
     * type = 1; profit is the signed net, matching getSoldItems().
     *
     * @return array<int, array<string, mixed>>
     */
    protected function buildItemMetricRows(string $start, string $end, ?int $userId): array
    {
        $lineCost = 'sale_lines.qty * sale_lines.unit_qty * sale_lines.cost';

        return SaleLine::query()
            ->join('sales as s', 's.id', '=', 'sale_lines.sales_id')
            ->where('s.cancelled', 0)
            ->whereBetween('s.created_at', [$start, $end])
            ->when($userId, fn ($query) => $query->where('s.user_id', $userId))
            ->select(
                's.user_id',
                's.store_id',
                'sale_lines.item_id',
                DB::raw('DATE(s.created_at) as date'),
                DB::raw('SUM(IF(s.type = 0, sale_lines.qty * sale_lines.unit_qty, 0)) as qty_sold'),
                DB::raw('SUM(IF(s.type = 0, sale_lines.sub_total, 0)) as revenue'),
                DB::raw('SUM(IF(s.type = 0, '.$lineCost.', 0)) as cost_total'),
                DB::raw('SUM(IF(s.type = 0, sale_lines.sub_total - ('.$lineCost.'), -(sale_lines.sub_total - ('.$lineCost.')))) as profit'),
                DB::raw('SUM(IF(s.type = 0, sale_lines.discount, 0)) as discount_total'),
                DB::raw('SUM(IF(s.type = 1, sale_lines.qty * sale_lines.unit_qty, 0)) as refund_qty'),
                DB::raw('SUM(IF(s.type = 1, sale_lines.sub_total, 0)) as refund_total'),
                DB::raw('COUNT(DISTINCT IF(s.type = 0, sale_lines.sales_id, NULL)) as transactions'),
            )
            ->groupBy('s.user_id', 's.store_id', 'sale_lines.item_id', DB::raw('DATE(s.created_at)'))
            ->get()
            ->map(fn ($row) => [
                'user_id' => (int) $row->user_id,
                'store_id' => (int) $row->store_id,
                'item_id' => (int) $row->item_id,
                'date' => $row->date,
                'qty_sold' => round((float) $row->qty_sold, 2),
                'revenue' => round((float) $row->revenue, 2),
                'cost_total' => round((float) $row->cost_total, 2),
                'profit' => round((float) $row->profit, 2),
                'discount_total' => round((float) $row->discount_total, 2),
                'refund_qty' => round((float) $row->refund_qty, 2),
                'refund_total' => round((float) $row->refund_total, 2),
                'transactions' => (int) $row->transactions,
            ])
            ->all();
    }

    /**
     * Walk-ins (customer_id NULL) are skipped — their totals live in
     * the store metrics.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function buildCustomerMetricRows(string $start, string $end, ?int $userId): array
    {
        return Sale::query()
            ->where('cancelled', 0)
            ->whereNotNull('customer_id')
            ->whereBetween('created_at', [$start, $end])
            ->when($userId, fn ($query) => $query->where('user_id', $userId))
            ->select(
                'user_id',
                'customer_id',
                DB::raw('DATE(created_at) as date'),
                DB::raw('SUM(IF(type = 0, total, 0)) as spend_total'),
                DB::raw('SUM(IF(type = 1, total, 0)) as refund_total'),
                DB::raw('SUM(IF(type = 0, profit, -profit)) as profit'),
                DB::raw('SUM(IF(type = 0, acquired_points, 0)) as points_earned'),
                DB::raw('SUM(IF(type = 0, points_used, 0)) as points_used'),
                DB::raw('SUM(IF(type = 0, 1, 0)) as transactions'),
                DB::raw('SUM(IF(type = 1, 1, 0)) as refund_count'),
            )
            ->groupBy('user_id', 'customer_id', DB::raw('DATE(created_at)'))
            ->get()
            ->map(fn ($row) => [
                'user_id' => (int) $row->user_id,
                'customer_id' => (int) $row->customer_id,
                'date' => $row->date,
                'spend_total' => round((float) $row->spend_total, 2),
                'refund_total' => round((float) $row->refund_total, 2),
                'profit' => round((float) $row->profit, 2),
                'points_earned' => round((float) $row->points_earned, 2),
                'points_used' => round((float) $row->points_used, 2),
                'transactions' => (int) $row->transactions,
                'refund_count' => (int) $row->refund_count,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function storeRowDefaults(int $userId, int $storeId, string $date): array
    {
        return [
            'user_id' => $userId,
            'store_id' => $storeId,
            'date' => $date,
            'gross_sales' => 0,
            'refunds_total' => 0,
            'net_sales' => 0,
            'profit' => 0,
            'cogs' => 0,
            'discount_total' => 0,
            'sc_discount_total' => 0,
            'pwd_discount_total' => 0,
            'sp_discount_total' => 0,
            'naac_discount_total' => 0,
            'voucher_discount_total' => 0,
            'vatable_total' => 0,
            'vat_total' => 0,
            'non_vat_total' => 0,
            'zero_rated_total' => 0,
            'cash_total' => 0,
            'ewallet_total' => 0,
            'credit_total' => 0,
            'bank_transfer_total' => 0,
            'cheque_total' => 0,
            'ecommerce_sales_total' => 0,
            'expenses_total' => 0,
            'transactions' => 0,
            'refund_count' => 0,
            'ecommerce_transactions' => 0,
        ];
    }

    /**
     * Delete-then-insert the affected window so re-runs are idempotent.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @param  array<int, array<string, mixed>>  $rows
     */
    protected function replaceRows($query, string $fromDate, string $toDate, ?int $userId, array $rows): void
    {
        $query->clone()
            ->whereBetween('date', [$fromDate, $toDate])
            ->when($userId, fn ($builder) => $builder->where('user_id', $userId))
            ->delete();

        $now = now();

        foreach (array_chunk($rows, self::INSERT_CHUNK) as $chunk) {
            $query->clone()->insert(array_map(
                fn (array $row) => $row + ['created_at' => $now, 'updated_at' => $now],
                $chunk,
            ));
        }
    }
}
