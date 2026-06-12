<?php

namespace Tests\Feature\Services;

use App\Models\Accounting\Expense;
use App\Models\Bi\DailyCustomerMetric;
use App\Models\Bi\DailyItemMetric;
use App\Models\Bi\DailyStoreMetric;
use App\Models\Ecommerce\EcommerceOrder;
use App\Models\Pos\Sale;
use App\Models\Pos\SaleLine;
use App\Models\Products\Item;
use App\Models\Settings\Store;
use App\Services\Bi\DailyAggregationService;
use App\Services\ReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DailyAggregationServiceTest extends TestCase
{
    use RefreshDatabase;

    private DailyAggregationService $service;

    private string $tz;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(DailyAggregationService::class);
        $this->tz = config('app.timezone', 'Asia/Manila');
    }

    /**
     * Local Manila day, $daysAgo days back, at 10:00 by default.
     */
    private function day(int $daysAgo = 1): Carbon
    {
        return Carbon::today($this->tz)->subDays($daysAgo);
    }

    private function makeSale(array $attributes = []): Sale
    {
        return Sale::factory()->create(array_merge([
            'user_id' => 1,
            'store_id' => 1,
            'type' => 0,
            'cancelled' => 0,
            'total' => 100,
            'profit' => 30,
            'discount' => 0,
            'created_at' => $this->day()->copy()->setTime(10, 0),
        ], $attributes));
    }

    public function test_groups_store_metrics_per_user_store_and_day(): void
    {
        $this->makeSale(['total' => 100, 'profit' => 30]);
        $this->makeSale(['total' => 50, 'profit' => 10]);
        $this->makeSale(['store_id' => 2, 'total' => 200]);
        $this->makeSale(['user_id' => 2, 'total' => 75]);
        $this->makeSale(['total' => 40, 'created_at' => $this->day(2)->copy()->setTime(15, 0)]);

        $this->service->aggregateRange($this->day(2), $this->day());

        $this->assertSame(4, DailyStoreMetric::count());

        $row = DailyStoreMetric::forUser(1)->forStore(1)
            ->whereDate('date', $this->day()->toDateString())
            ->firstOrFail();

        $this->assertSame(150.0, $row->gross_sales);
        $this->assertSame(150.0, $row->net_sales);
        $this->assertSame(40.0, $row->profit);
        $this->assertSame(2, $row->transactions);
    }

    public function test_refunds_reduce_net_and_profit_but_not_gross(): void
    {
        $this->makeSale(['total' => 100, 'profit' => 30]);
        $this->makeSale(['type' => 1, 'total' => 40, 'profit' => 12]);

        $this->service->aggregateDate($this->day());

        $row = DailyStoreMetric::firstOrFail();

        $this->assertSame(100.0, $row->gross_sales);
        $this->assertSame(40.0, $row->refunds_total);
        $this->assertSame(60.0, $row->net_sales);
        $this->assertSame(18.0, $row->profit);
        $this->assertSame(1, $row->transactions);
        $this->assertSame(1, $row->refund_count);
    }

    public function test_cancelled_sales_are_excluded(): void
    {
        $this->makeSale(['total' => 100]);
        $this->makeSale(['total' => 999, 'cancelled' => 1]);
        $this->makeSale([
            'total' => 500,
            'cancelled' => 1,
            'created_at' => $this->day(2)->copy()->setTime(12, 0),
        ]);

        $this->service->aggregateRange($this->day(2), $this->day());

        $this->assertSame(1, DailyStoreMetric::count());
        $this->assertSame(100.0, DailyStoreMetric::firstOrFail()->gross_sales);
    }

    public function test_manila_midnight_boundary_splits_days(): void
    {
        $this->makeSale(['total' => 100, 'created_at' => $this->day(2)->copy()->setTime(23, 59, 0)]);
        $this->makeSale(['total' => 50, 'created_at' => $this->day(1)->copy()->setTime(0, 1, 0)]);

        $this->service->aggregateRange($this->day(2), $this->day(1));

        $this->assertSame(2, DailyStoreMetric::count());

        $earlier = DailyStoreMetric::whereDate('date', $this->day(2)->toDateString())->firstOrFail();
        $later = DailyStoreMetric::whereDate('date', $this->day(1)->toDateString())->firstOrFail();

        $this->assertSame(100.0, $earlier->gross_sales);
        $this->assertSame(50.0, $later->gross_sales);
    }

    public function test_rerun_is_idempotent(): void
    {
        $this->makeSale(['total' => 100]);

        $this->service->aggregateDate($this->day());
        $this->service->aggregateDate($this->day());

        $this->assertSame(1, DailyStoreMetric::count());
        $this->assertSame(100.0, DailyStoreMetric::firstOrFail()->gross_sales);
    }

    public function test_retroactive_cancellation_removes_row_on_rerun(): void
    {
        $sale = $this->makeSale(['total' => 100]);

        $this->service->aggregateDate($this->day());
        $this->assertSame(1, DailyStoreMetric::count());

        $sale->update(['cancelled' => 1]);

        $this->service->aggregateDate($this->day());
        $this->assertSame(0, DailyStoreMetric::count());
    }

    public function test_payment_mix_and_ecommerce_totals(): void
    {
        $this->makeSale(['total' => 100, 'payment_type' => null]);
        $this->makeSale(['total' => 50, 'payment_type' => Sale::PAYMENT_EWALLET]);
        $this->makeSale(['total' => 25, 'payment_type' => Sale::PAYMENT_CREDIT]);
        $this->makeSale(['total' => 10, 'payment_type' => Sale::PAYMENT_BANK_TRANSFER]);
        $this->makeSale(['total' => 5, 'payment_type' => Sale::PAYMENT_CHEQUE]);
        $order = EcommerceOrder::factory()->create();
        $this->makeSale(['total' => 80, 'payment_type' => Sale::PAYMENT_EWALLET, 'ecommerce_order_id' => $order->id]);
        $this->makeSale(['type' => 1, 'total' => 20, 'payment_type' => Sale::PAYMENT_CASH]);

        $this->service->aggregateDate($this->day());

        $row = DailyStoreMetric::firstOrFail();

        $this->assertSame(80.0, $row->cash_total);
        $this->assertSame(130.0, $row->ewallet_total);
        $this->assertSame(25.0, $row->credit_total);
        $this->assertSame(10.0, $row->bank_transfer_total);
        $this->assertSame(5.0, $row->cheque_total);
        $this->assertSame(80.0, $row->ecommerce_sales_total);
        $this->assertSame(1, $row->ecommerce_transactions);
    }

    public function test_expenses_merge_into_store_rows(): void
    {
        $store = Store::factory()->create(['user_id' => 1]);

        $this->makeSale(['store_id' => $store->id, 'total' => 100]);
        Expense::factory()->create([
            'store_id' => $store->id,
            'amount' => 500,
            'expense_date' => $this->day()->toDateString(),
        ]);
        Expense::factory()->voided()->create([
            'store_id' => $store->id,
            'amount' => 999,
            'expense_date' => $this->day()->toDateString(),
        ]);
        Expense::factory()->create([
            'store_id' => null,
            'amount' => 777,
            'expense_date' => $this->day()->toDateString(),
        ]);

        $this->service->aggregateDate($this->day());

        $row = DailyStoreMetric::forStore($store->id)->firstOrFail();

        $this->assertSame(100.0, $row->gross_sales);
        $this->assertSame(500.0, $row->expenses_total);
    }

    public function test_expense_only_day_still_gets_store_row(): void
    {
        $store = Store::factory()->create(['user_id' => 1]);

        Expense::factory()->create([
            'store_id' => $store->id,
            'amount' => 350,
            'expense_date' => $this->day()->toDateString(),
        ]);

        $this->service->aggregateDate($this->day());

        $row = DailyStoreMetric::forStore($store->id)->firstOrFail();

        $this->assertSame(0.0, $row->gross_sales);
        $this->assertSame(0.0, $row->net_sales);
        $this->assertSame(350.0, $row->expenses_total);
        $this->assertSame(0, $row->transactions);
    }

    public function test_cogs_rolls_up_from_sale_lines(): void
    {
        $item = Item::factory()->create();
        $sale = $this->makeSale(['total' => 120]);
        SaleLine::factory()->forSale($sale->id)->forItem($item->id)->create([
            'qty' => 2,
            'unit_qty' => 6,
            'cost' => 5,
            'sub_total' => 120,
        ]);

        $refund = $this->makeSale(['type' => 1, 'total' => 60]);
        SaleLine::factory()->forSale($refund->id)->forItem($item->id)->create([
            'qty' => 1,
            'unit_qty' => 6,
            'cost' => 5,
            'sub_total' => 60,
        ]);

        $this->service->aggregateDate($this->day());

        $row = DailyStoreMetric::firstOrFail();

        $this->assertSame(30.0, $row->cogs);
    }

    public function test_item_metrics_use_base_unit_qty_and_signed_refunds(): void
    {
        $item = Item::factory()->create();
        $sale = $this->makeSale(['total' => 120]);
        SaleLine::factory()->forSale($sale->id)->forItem($item->id)->create([
            'qty' => 2,
            'unit_qty' => 6,
            'cost' => 5,
            'sub_total' => 120,
            'discount' => 8,
        ]);

        $refund = $this->makeSale(['type' => 1, 'total' => 60]);
        SaleLine::factory()->forSale($refund->id)->forItem($item->id)->create([
            'qty' => 1,
            'unit_qty' => 6,
            'cost' => 5,
            'sub_total' => 60,
        ]);

        $this->service->aggregateDate($this->day());

        $row = DailyItemMetric::where('item_id', $item->id)->firstOrFail();

        $this->assertSame(12.0, $row->qty_sold);
        $this->assertSame(120.0, $row->revenue);
        $this->assertSame(60.0, $row->cost_total);
        $this->assertSame(8.0, $row->discount_total);
        $this->assertSame(6.0, $row->refund_qty);
        $this->assertSame(60.0, $row->refund_total);
        $this->assertSame(30.0, $row->profit);
        $this->assertSame(1, $row->transactions);
    }

    public function test_customer_metrics_skip_walkins_and_capture_points(): void
    {
        $this->makeSale(['customer_id' => null, 'total' => 500]);
        $this->makeSale([
            'customer_id' => 5,
            'total' => 100,
            'profit' => 30,
            'acquired_points' => 10,
            'points_used' => 2,
        ]);
        $this->makeSale(['customer_id' => 5, 'type' => 1, 'total' => 20, 'profit' => 5]);

        $this->service->aggregateDate($this->day());

        $this->assertSame(1, DailyCustomerMetric::count());

        $row = DailyCustomerMetric::where('customer_id', 5)->firstOrFail();

        $this->assertSame(100.0, $row->spend_total);
        $this->assertSame(20.0, $row->refund_total);
        $this->assertSame(25.0, $row->profit);
        $this->assertSame(10.0, $row->points_earned);
        $this->assertSame(2.0, $row->points_used);
        $this->assertSame(1, $row->transactions);
        $this->assertSame(1, $row->refund_count);
    }

    public function test_user_filter_limits_rebuild_to_that_tenant(): void
    {
        $this->makeSale(['user_id' => 1, 'total' => 100]);
        $this->makeSale(['user_id' => 2, 'total' => 200]);

        $this->service->aggregateDate($this->day(), 1);

        $this->assertSame(1, DailyStoreMetric::count());
        $this->assertSame(1, DailyStoreMetric::firstOrFail()->user_id);

        $this->service->aggregateDate($this->day(), 2);

        $this->assertSame(2, DailyStoreMetric::count());
        $this->assertSame(100.0, DailyStoreMetric::forUser(1)->firstOrFail()->gross_sales);
    }

    public function test_earliest_sale_date_ignores_cancelled_sales(): void
    {
        $this->makeSale(['cancelled' => 1, 'created_at' => $this->day(10)->copy()->setTime(9, 0)]);
        $this->makeSale(['created_at' => $this->day(5)->copy()->setTime(9, 0)]);

        $this->assertSame(
            $this->day(5)->toDateString(),
            $this->service->earliestSaleDate()->toDateString(),
        );
    }

    public function test_store_metrics_reconcile_with_report_service_summary(): void
    {
        $this->makeSale(['total' => 100, 'profit' => 30]);
        $this->makeSale(['total' => 55.55, 'profit' => 11.11, 'store_id' => 2]);
        $this->makeSale(['type' => 1, 'total' => 20, 'profit' => 6]);

        $this->service->aggregateDate($this->day());

        $summary = app(ReportService::class)->getSalesSummary(1, 'daily', $this->day()->toDateString());
        $rows = DailyStoreMetric::forUser(1)->whereDate('date', $this->day()->toDateString())->get();

        $this->assertSame($summary['sales'], round($rows->sum('gross_sales'), 2));
        $this->assertSame($summary['refunds'], round($rows->sum('refunds_total'), 2));
        $this->assertSame($summary['profit'], round($rows->sum('profit'), 2));
        $this->assertSame($summary['transactions'], (int) ($rows->sum('transactions') + $rows->sum('refund_count')));
    }
}
