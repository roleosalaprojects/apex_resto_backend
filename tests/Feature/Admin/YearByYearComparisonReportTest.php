<?php

namespace Tests\Feature\Admin;

use App\Models\Employees\Role;
use App\Models\Pos\Sale;
use App\Models\Settings\Pos;
use App\Models\Settings\Store;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class YearByYearComparisonReportTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $cashier;

    protected Store $store;

    protected Pos $pos;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::factory()->admin()->create();
        $cashierRole = Role::factory()->create(['sls' => false]);

        $this->admin = User::factory()->create(['role_id' => $adminRole->id]);
        $this->cashier = User::factory()->create([
            'name' => 'Test Cashier',
            'role_id' => $cashierRole->id,
        ]);
        $this->store = Store::factory()->create();
        $this->pos = Pos::factory()->create(['store_id' => $this->store->id]);
    }

    protected function createSale(array $attributes = []): Sale
    {
        return Sale::factory()->create(array_merge([
            'sales_by' => $this->admin->id,
            'user_id' => $this->admin->id,
            'store_id' => $this->store->id,
            'pos_id' => $this->pos->id,
        ], $attributes));
    }

    public function test_year_by_year_comparison_page_loads_for_user_with_sls_role(): void
    {
        $response = $this->actingAs($this->admin)->get(route('reports.year_by_year_comparison'));

        $response->assertStatus(200);
        $response->assertSee('Year by Year Comparison');
    }

    public function test_year_by_year_comparison_page_redirects_users_without_sls_role(): void
    {
        $response = $this->actingAs($this->cashier)->get(route('reports.year_by_year_comparison'));

        $response->assertRedirect('/admin/home');
    }

    public function test_data_endpoint_returns_yearly_aggregates_with_growth(): void
    {
        $currentYear = (int) Carbon::now()->year;

        $this->createSale([
            'type' => 0,
            'total' => 1000,
            'profit' => 200,
            'created_at' => Carbon::create($currentYear - 1, 6, 15)->utc(),
        ]);

        $this->createSale([
            'type' => 0,
            'total' => 2000,
            'profit' => 500,
            'created_at' => Carbon::create($currentYear, 3, 10)->utc(),
        ]);

        $this->createSale([
            'type' => 1,
            'total' => 200,
            'profit' => 50,
            'created_at' => Carbon::create($currentYear, 4, 5)->utc(),
        ]);

        $response = $this->actingAs($this->admin)->getJson(
            route('reports.year_by_year_comparison.data', [
                'end_year' => $currentYear,
                'years_count' => 2,
            ])
        );

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'summary' => ['latest_year', 'latest_net_sales', 'latest_profit', 'previous_net_sales', 'yoy_growth'],
            'rows' => [['year', 'sales', 'refunds', 'net_sales', 'profit', 'receipts', 'growth']],
            'series' => [['year', 'data']],
            'years',
        ]);

        $payload = $response->json();

        $this->assertSame([$currentYear - 1, $currentYear], $payload['years']);
        $this->assertCount(2, $payload['rows']);

        $previousRow = $payload['rows'][0];
        $latestRow = $payload['rows'][1];

        $this->assertSame($currentYear - 1, $previousRow['year']);
        $this->assertEqualsWithDelta(1000.0, $previousRow['net_sales'], 0.01);
        $this->assertNull($previousRow['growth']);

        $this->assertSame($currentYear, $latestRow['year']);
        $this->assertEqualsWithDelta(1800.0, $latestRow['net_sales'], 0.01);
        $this->assertEqualsWithDelta(80.0, $latestRow['growth'], 0.01);

        $this->assertSame($currentYear, $payload['summary']['latest_year']);
        $this->assertEqualsWithDelta(1800.0, $payload['summary']['latest_net_sales'], 0.01);
        $this->assertEqualsWithDelta(80.0, $payload['summary']['yoy_growth'], 0.01);
    }

    public function test_data_endpoint_filters_by_store(): void
    {
        $currentYear = (int) Carbon::now()->year;
        $otherStore = Store::factory()->create();
        $otherPos = Pos::factory()->create(['store_id' => $otherStore->id]);

        $this->createSale([
            'type' => 0,
            'total' => 5000,
            'created_at' => Carbon::create($currentYear, 5, 1)->utc(),
        ]);

        Sale::factory()->create([
            'sales_by' => $this->admin->id,
            'user_id' => $this->admin->id,
            'store_id' => $otherStore->id,
            'pos_id' => $otherPos->id,
            'type' => 0,
            'total' => 9999,
            'created_at' => Carbon::create($currentYear, 5, 2)->utc(),
        ]);

        $response = $this->actingAs($this->admin)->getJson(
            route('reports.year_by_year_comparison.data', [
                'end_year' => $currentYear,
                'years_count' => 2,
                'store_select' => $this->store->id,
            ])
        );

        $response->assertStatus(200);
        $latest = $response->json('rows.1');
        $this->assertSame($currentYear, $latest['year']);
        $this->assertEqualsWithDelta(5000.0, $latest['net_sales'], 0.01);
    }

    public function test_monthly_series_places_values_in_correct_month_bucket(): void
    {
        $currentYear = (int) Carbon::now()->year;

        $this->createSale([
            'type' => 0,
            'total' => 1500,
            'created_at' => Carbon::create($currentYear, 7, 20)->utc(),
        ]);

        $response = $this->actingAs($this->admin)->getJson(
            route('reports.year_by_year_comparison.data', [
                'end_year' => $currentYear,
                'years_count' => 2,
            ])
        );

        $response->assertStatus(200);
        $latestSeries = collect($response->json('series'))->firstWhere('year', $currentYear);

        $this->assertNotNull($latestSeries);
        $this->assertCount(12, $latestSeries['data']);
        $this->assertEqualsWithDelta(1500.0, $latestSeries['data'][6], 0.01);
        $this->assertEqualsWithDelta(0.0, $latestSeries['data'][0], 0.01);
    }

    public function test_monthly_rows_returns_twelve_months_with_per_year_values(): void
    {
        $currentYear = (int) Carbon::now()->year;

        $response = $this->actingAs($this->admin)->getJson(
            route('reports.year_by_year_comparison.data', [
                'end_year' => $currentYear,
                'years_count' => 3,
            ])
        );

        $response->assertStatus(200);
        $monthlyRows = $response->json('monthly_rows');

        $this->assertCount(12, $monthlyRows);
        $this->assertSame(['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'], array_column($monthlyRows, 'month'));
        $this->assertCount(3, $monthlyRows[0]['values']);

        $firstCell = $monthlyRows[0]['values'][0];
        foreach (['sales', 'refunds', 'net_sales', 'profit', 'receipts'] as $metric) {
            $this->assertArrayHasKey($metric, $firstCell);
            $this->assertArrayHasKey($metric.'_growth', $firstCell);
            $this->assertNull($firstCell[$metric.'_growth']);
        }
    }

    public function test_monthly_rows_include_receipts_count_with_growth(): void
    {
        $currentYear = (int) Carbon::now()->year;

        for ($i = 0; $i < 4; $i++) {
            $this->createSale([
                'type' => 0,
                'total' => 500,
                'created_at' => Carbon::create($currentYear - 1, 9, 10 + $i)->utc(),
            ]);
        }

        for ($i = 0; $i < 5; $i++) {
            $this->createSale([
                'type' => 0,
                'total' => 600,
                'created_at' => Carbon::create($currentYear, 9, 10 + $i)->utc(),
            ]);
        }

        $response = $this->actingAs($this->admin)->getJson(
            route('reports.year_by_year_comparison.data', [
                'end_year' => $currentYear,
                'years_count' => 2,
            ])
        );

        $response->assertStatus(200);
        $sep = $response->json('monthly_rows.8');

        $this->assertSame('Sep', $sep['month']);
        $this->assertSame(4, $sep['values'][0]['receipts']);
        $this->assertNull($sep['values'][0]['receipts_growth']);
        $this->assertSame(5, $sep['values'][1]['receipts']);
        $this->assertEqualsWithDelta(25.0, $sep['values'][1]['receipts_growth'], 0.01);
    }

    public function test_monthly_rows_compute_year_over_year_growth_per_month(): void
    {
        $currentYear = (int) Carbon::now()->year;

        $this->createSale([
            'type' => 0,
            'total' => 1000,
            'created_at' => Carbon::create($currentYear - 1, 7, 10)->utc(),
        ]);

        $this->createSale([
            'type' => 0,
            'total' => 1500,
            'created_at' => Carbon::create($currentYear, 7, 12)->utc(),
        ]);

        $this->createSale([
            'type' => 1,
            'total' => 100,
            'created_at' => Carbon::create($currentYear, 7, 14)->utc(),
        ]);

        $response = $this->actingAs($this->admin)->getJson(
            route('reports.year_by_year_comparison.data', [
                'end_year' => $currentYear,
                'years_count' => 2,
            ])
        );

        $response->assertStatus(200);
        $july = $response->json('monthly_rows.6');

        $this->assertSame('Jul', $july['month']);
        $this->assertEqualsWithDelta(1000.0, $july['values'][0]['net_sales'], 0.01);
        $this->assertNull($july['values'][0]['net_sales_growth']);
        $this->assertEqualsWithDelta(1400.0, $july['values'][1]['net_sales'], 0.01);
        $this->assertEqualsWithDelta(40.0, $july['values'][1]['net_sales_growth'], 0.01);
    }

    public function test_monthly_rows_carry_sales_refunds_and_profit_with_growth(): void
    {
        $currentYear = (int) Carbon::now()->year;

        $this->createSale([
            'type' => 0,
            'total' => 800,
            'profit' => 200,
            'created_at' => Carbon::create($currentYear - 1, 8, 5)->utc(),
        ]);

        $this->createSale([
            'type' => 1,
            'total' => 100,
            'profit' => 25,
            'created_at' => Carbon::create($currentYear - 1, 8, 6)->utc(),
        ]);

        $this->createSale([
            'type' => 0,
            'total' => 1200,
            'profit' => 400,
            'created_at' => Carbon::create($currentYear, 8, 4)->utc(),
        ]);

        $this->createSale([
            'type' => 1,
            'total' => 200,
            'profit' => 50,
            'created_at' => Carbon::create($currentYear, 8, 7)->utc(),
        ]);

        $response = $this->actingAs($this->admin)->getJson(
            route('reports.year_by_year_comparison.data', [
                'end_year' => $currentYear,
                'years_count' => 2,
            ])
        );

        $response->assertStatus(200);
        $aug = $response->json('monthly_rows.7');
        $this->assertSame('Aug', $aug['month']);

        $previous = $aug['values'][0];
        $latest = $aug['values'][1];

        $this->assertEqualsWithDelta(800.0, $previous['sales'], 0.01);
        $this->assertEqualsWithDelta(100.0, $previous['refunds'], 0.01);
        $this->assertEqualsWithDelta(700.0, $previous['net_sales'], 0.01);
        $this->assertEqualsWithDelta(175.0, $previous['profit'], 0.01);

        $this->assertEqualsWithDelta(1200.0, $latest['sales'], 0.01);
        $this->assertEqualsWithDelta(200.0, $latest['refunds'], 0.01);
        $this->assertEqualsWithDelta(1000.0, $latest['net_sales'], 0.01);
        $this->assertEqualsWithDelta(350.0, $latest['profit'], 0.01);

        $this->assertEqualsWithDelta(50.0, $latest['sales_growth'], 0.01);
        $this->assertEqualsWithDelta(100.0, $latest['refunds_growth'], 0.01);
        $this->assertEqualsWithDelta(((1000 - 700) / 700) * 100, $latest['net_sales_growth'], 0.01);
        $this->assertEqualsWithDelta(100.0, $latest['profit_growth'], 0.01);
    }
}
