<?php

namespace Tests\Feature\Admin;

use App\Models\Bi\DailyCustomerMetric;
use App\Models\Bi\DailyItemMetric;
use App\Models\Bi\DailyStoreMetric;
use App\Models\CustomerRelations\Customer;
use App\Models\Employees\Role;
use App\Models\Products\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Business Health dashboard reads ONLY the daily_*_metrics
 * aggregate tables, so these tests seed those tables directly —
 * raw-sales-to-aggregate fidelity is covered by
 * DailyAggregationServiceTest.
 */
class BusinessIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $cashier;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::factory()->admin()->create();
        $cashierRole = Role::factory()->create(['sls' => false]);

        $this->admin = User::factory()->create(['role_id' => $adminRole->id]);
        $this->cashier = User::factory()->create(['role_id' => $cashierRole->id]);
    }

    protected function makeStoreMetric(array $overrides = []): DailyStoreMetric
    {
        return DailyStoreMetric::create(array_merge([
            'user_id' => $this->admin->user_id,
            'store_id' => 1,
            'date' => '2026-03-10',
        ], $overrides));
    }

    protected function fetchData(array $params = [])
    {
        return $this->actingAs($this->admin)->getJson(
            route('reports.business_intelligence.data', array_merge([
                'start_date' => '2026-03-01',
                'end_date' => '2026-03-31',
            ], $params)),
        );
    }

    public function test_page_loads_for_user_with_sls_role(): void
    {
        $this->actingAs($this->admin)
            ->get(route('reports.business_intelligence'))
            ->assertOk()
            ->assertSee('Business Health');
    }

    public function test_page_redirects_users_without_sls_role(): void
    {
        $this->actingAs($this->cashier)
            ->get(route('reports.business_intelligence'))
            ->assertRedirect('/admin/home');
    }

    public function test_data_endpoint_returns_pnl_summary_from_aggregates(): void
    {
        $this->makeStoreMetric([
            'date' => '2026-03-10',
            'gross_sales' => 1000,
            'refunds_total' => 100,
            'net_sales' => 900,
            'profit' => 300,
            'cogs' => 600,
            'expenses_total' => 50,
            'cash_total' => 700,
            'ewallet_total' => 200,
            'discount_total' => 25,
            'transactions' => 10,
            'refund_count' => 1,
        ]);
        $this->makeStoreMetric([
            'date' => '2026-03-11',
            'gross_sales' => 500,
            'net_sales' => 500,
            'profit' => 200,
            'cogs' => 300,
            'expenses_total' => 150,
            'cash_total' => 500,
            'transactions' => 5,
        ]);

        $response = $this->fetchData()->assertOk();

        $summary = $response->json('summary');
        $this->assertEquals(1500.0, $summary['gross_sales']);
        $this->assertEquals(100.0, $summary['refunds_total']);
        $this->assertEquals(1400.0, $summary['net_sales']);
        $this->assertEquals(900.0, $summary['cogs']);
        $this->assertEquals(500.0, $summary['gross_profit']);
        $this->assertEquals(200.0, $summary['expenses_total']);
        $this->assertEquals(300.0, $summary['net_profit']);
        $this->assertEquals(round(500 / 1400 * 100, 2), $summary['gross_margin_pct']);
        $this->assertEquals(round(300 / 1400 * 100, 2), $summary['net_margin_pct']);
        $this->assertEquals(15, $summary['transactions']);
        $this->assertEquals(1, $summary['refund_count']);
        $this->assertEquals(100.0, $summary['avg_transaction_value']);

        $this->assertEquals(1200.0, $response->json('payment_mix.cash'));
        $this->assertEquals(200.0, $response->json('payment_mix.ewallet'));
        $this->assertEquals(25.0, $response->json('discounts.regular'));
    }

    public function test_data_endpoint_scopes_to_tenant(): void
    {
        $this->makeStoreMetric(['net_sales' => 100, 'gross_sales' => 100]);
        $this->makeStoreMetric([
            'user_id' => $this->admin->user_id + 99,
            'net_sales' => 9999,
            'gross_sales' => 9999,
        ]);

        $this->fetchData()
            ->assertOk()
            ->assertJsonPath('summary.net_sales', 100);
    }

    public function test_data_endpoint_filters_by_store(): void
    {
        $this->makeStoreMetric(['store_id' => 1, 'net_sales' => 100, 'gross_sales' => 100]);
        $this->makeStoreMetric(['store_id' => 2, 'net_sales' => 400, 'gross_sales' => 400]);

        $this->fetchData(['store_id' => 2])
            ->assertOk()
            ->assertJsonPath('summary.net_sales', 400);

        $this->fetchData()
            ->assertOk()
            ->assertJsonPath('summary.net_sales', 500);
    }

    public function test_data_endpoint_filters_by_date_range(): void
    {
        $this->makeStoreMetric(['date' => '2026-03-15', 'net_sales' => 100, 'gross_sales' => 100]);
        $this->makeStoreMetric(['date' => '2026-04-01', 'net_sales' => 250, 'gross_sales' => 250]);

        $this->fetchData()
            ->assertOk()
            ->assertJsonPath('summary.net_sales', 100);
    }

    public function test_change_pct_compares_against_preceding_equal_length_period(): void
    {
        // Current week: 2026-03-08..2026-03-14. Previous week: 2026-03-01..2026-03-07.
        $this->makeStoreMetric(['date' => '2026-03-10', 'net_sales' => 200, 'gross_sales' => 200, 'transactions' => 4]);
        $this->makeStoreMetric(['date' => '2026-03-03', 'net_sales' => 100, 'gross_sales' => 100, 'transactions' => 2]);

        $response = $this->fetchData(['start_date' => '2026-03-08', 'end_date' => '2026-03-14'])->assertOk();

        $this->assertEquals(100.0, $response->json('change_pct.net_sales'));
        $this->assertEquals(100.0, $response->json('change_pct.transactions'));
        $this->assertSame('2026-03-01', $response->json('previous.from'));
        $this->assertSame('2026-03-07', $response->json('previous.to'));
        $this->assertEquals(100.0, $response->json('previous.net_sales'));
    }

    public function test_change_pct_is_null_when_previous_period_is_empty(): void
    {
        $this->makeStoreMetric(['date' => '2026-03-10', 'net_sales' => 200, 'gross_sales' => 200]);

        $this->fetchData(['start_date' => '2026-03-08', 'end_date' => '2026-03-14'])
            ->assertOk()
            ->assertJsonPath('change_pct.net_sales', null);
    }

    public function test_trend_zero_fills_days_without_aggregates(): void
    {
        $this->makeStoreMetric(['date' => '2026-03-02', 'net_sales' => 100, 'gross_sales' => 100, 'profit' => 40]);

        $response = $this->fetchData(['start_date' => '2026-03-01', 'end_date' => '2026-03-03'])->assertOk();

        $trend = $response->json('trend');
        $this->assertCount(3, $trend);
        $this->assertSame(['2026-03-01', '2026-03-02', '2026-03-03'], array_column($trend, 'date'));
        $this->assertEquals(0, $trend[0]['net_sales']);
        $this->assertEquals(100, $trend[1]['net_sales']);
        $this->assertEquals(40, $trend[1]['gross_profit']);
        $this->assertEquals(0, $trend[2]['net_sales']);
    }

    public function test_top_items_ranked_by_revenue_with_names(): void
    {
        $widget = Item::factory()->create(['name' => 'WIDGET A']);
        $gadget = Item::factory()->create(['name' => 'GADGET B']);

        DailyItemMetric::create([
            'user_id' => $this->admin->user_id,
            'store_id' => 1,
            'item_id' => $widget->id,
            'date' => '2026-03-10',
            'qty_sold' => 5,
            'revenue' => 500,
            'profit' => 100,
        ]);
        DailyItemMetric::create([
            'user_id' => $this->admin->user_id,
            'store_id' => 1,
            'item_id' => $gadget->id,
            'date' => '2026-03-11',
            'qty_sold' => 2,
            'revenue' => 900,
            'profit' => 300,
        ]);

        $response = $this->fetchData()->assertOk();

        $items = $response->json('top_items');
        $this->assertCount(2, $items);
        $this->assertSame('GADGET B', $items[0]['name']);
        $this->assertEquals(900.0, $items[0]['revenue']);
        $this->assertSame('WIDGET A', $items[1]['name']);
        $this->assertEquals(5.0, $items[1]['qty_sold']);
    }

    public function test_top_customers_are_tenant_wide_even_with_store_filter(): void
    {
        $customer = Customer::factory()->create(['name' => 'Big Spender']);

        DailyCustomerMetric::create([
            'user_id' => $this->admin->user_id,
            'customer_id' => $customer->id,
            'date' => '2026-03-10',
            'spend_total' => 750,
            'profit' => 150,
            'transactions' => 3,
        ]);

        // Customer metrics carry no store dimension — a store filter must
        // not blank this list out.
        $response = $this->fetchData(['store_id' => 2])->assertOk();

        $customers = $response->json('top_customers');
        $this->assertCount(1, $customers);
        $this->assertSame('Big Spender', $customers[0]['name']);
        $this->assertEquals(750.0, $customers[0]['spend_total']);
        $this->assertEquals(3, $customers[0]['transactions']);
    }

    public function test_data_through_reports_latest_aggregated_date(): void
    {
        $this->makeStoreMetric(['date' => '2026-03-10']);
        $this->makeStoreMetric(['date' => '2026-03-20']);

        $this->fetchData()
            ->assertOk()
            ->assertJsonPath('data_through', '2026-03-20');
    }

    public function test_data_through_is_null_with_no_aggregates(): void
    {
        $this->fetchData()
            ->assertOk()
            ->assertJsonPath('data_through', null);
    }

    public function test_export_redirects_users_without_sls_role(): void
    {
        $this->actingAs($this->cashier)
            ->get(route('reports.business_intelligence.export'))
            ->assertRedirect('/admin/home');
    }

    public function test_export_streams_daily_pnl_csv_with_zero_filled_days(): void
    {
        $this->makeStoreMetric([
            'date' => '2026-03-02',
            'gross_sales' => 1000,
            'refunds_total' => 100,
            'net_sales' => 900,
            'cogs' => 600,
            'profit' => 300,
            'expenses_total' => 50,
            'transactions' => 10,
            'refund_count' => 1,
        ]);

        $response = $this->actingAs($this->admin)->get(route('reports.business_intelligence.export', [
            'start_date' => '2026-03-01',
            'end_date' => '2026-03-03',
        ]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=utf-8');
        $response->assertDownload('business_health_2026-03-01_to_2026-03-03.csv');

        $lines = array_map('str_getcsv', explode("\n", trim($response->streamedContent())));
        $this->assertSame(['Date', 'Gross Sales', 'Refunds', 'Net Sales', 'COGS', 'Gross Profit', 'Expenses', 'Net Profit', 'Transactions', 'Refund Count'], $lines[0]);
        $this->assertCount(4, $lines, 'Header plus one row per day in range.');
        $this->assertSame(['2026-03-01', '0', '0', '0', '0', '0', '0', '0', '0', '0'], $lines[1]);
        $this->assertSame(['2026-03-02', '1000', '100', '900', '600', '300', '50', '250', '10', '1'], $lines[2]);
        $this->assertSame('2026-03-03', $lines[3][0]);
    }

    public function test_export_honors_store_filter_and_tenant_scope(): void
    {
        $this->makeStoreMetric(['date' => '2026-03-02', 'store_id' => 1, 'gross_sales' => 100, 'net_sales' => 100]);
        $this->makeStoreMetric(['date' => '2026-03-02', 'store_id' => 2, 'gross_sales' => 400, 'net_sales' => 400]);
        $this->makeStoreMetric(['date' => '2026-03-02', 'user_id' => $this->admin->user_id + 99, 'gross_sales' => 9999, 'net_sales' => 9999]);

        $response = $this->actingAs($this->admin)->get(route('reports.business_intelligence.export', [
            'start_date' => '2026-03-02',
            'end_date' => '2026-03-02',
            'store_id' => 2,
        ]));

        $lines = array_map('str_getcsv', explode("\n", trim($response->streamedContent())));
        $this->assertCount(2, $lines);
        $this->assertSame('400', $lines[1][1]);
    }
}
