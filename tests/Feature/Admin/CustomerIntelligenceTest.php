<?php

namespace Tests\Feature\Admin;

use App\Models\Bi\DailyCustomerMetric;
use App\Models\CustomerRelations\Customer;
use App\Models\Employees\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Customer Intelligence page reads daily_customer_metrics (RFM)
 * and shop_visits/ecommerce_orders (funnel). RFM math fidelity is
 * covered by CustomerIntelligenceServiceTest — these tests cover the
 * HTTP layer: gating, JSON shape, and input validation.
 */
class CustomerIntelligenceTest extends TestCase
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

    protected function makeCustomerMetric(int $customerId, int $daysAgo, array $overrides = []): DailyCustomerMetric
    {
        $asOf = Carbon::today(config('app.timezone', 'Asia/Manila'));

        return DailyCustomerMetric::create(array_merge([
            'user_id' => $this->admin->user_id,
            'customer_id' => $customerId,
            'date' => $asOf->copy()->subDays($daysAgo)->toDateString(),
            'spend_total' => 100,
            'profit' => 30,
            'transactions' => 1,
        ], $overrides));
    }

    public function test_page_loads_for_user_with_sls_role(): void
    {
        $this->actingAs($this->admin)
            ->get(route('reports.customer_intelligence'))
            ->assertOk()
            ->assertSee('Customer Intelligence');
    }

    public function test_page_redirects_users_without_sls_role(): void
    {
        $this->actingAs($this->cashier)
            ->get(route('reports.customer_intelligence'))
            ->assertRedirect('/admin/home');
    }

    public function test_data_endpoint_returns_rfm_and_funnel_structure(): void
    {
        $customer = Customer::factory()->create(['name' => 'Maria', 'user_id' => $this->admin->user_id]);
        $this->makeCustomerMetric($customer->id, 10, ['spend_total' => 250, 'profit' => 75]);

        $response = $this->actingAs($this->admin)
            ->getJson(route('reports.customer_intelligence.data'))
            ->assertOk()
            ->assertJsonStructure([
                'rfm' => ['as_of', 'window_days', 'totals', 'segments', 'segment_customers'],
                'funnel' => ['from', 'to', 'stages', 'overall_conversion_pct'],
            ]);

        $this->assertEquals(365, $response->json('rfm.window_days'));
        $this->assertEquals(1, $response->json('rfm.totals.analyzed_customers'));

        $row = collect($response->json('rfm.segment_customers'))
            ->flatten(1)
            ->firstWhere('customer_id', $customer->id);
        $this->assertSame('Maria', $row['name']);
        $this->assertEquals(250.0, $row['monetary']);
        $this->assertEquals(75.0, $row['lifetime_profit']);
    }

    public function test_data_endpoint_accepts_allowed_window(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson(route('reports.customer_intelligence.data', ['window_days' => 90]))
            ->assertOk();

        $this->assertEquals(90, $response->json('rfm.window_days'));
    }

    public function test_data_endpoint_falls_back_to_default_window_on_bad_input(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson(route('reports.customer_intelligence.data', ['window_days' => 7]))
            ->assertOk();

        $this->assertEquals(365, $response->json('rfm.window_days'));
    }

    public function test_data_endpoint_scopes_rfm_to_tenant(): void
    {
        $mine = Customer::factory()->create(['user_id' => $this->admin->user_id]);
        $theirs = Customer::factory()->create(['user_id' => $this->admin->user_id + 1]);
        $this->makeCustomerMetric($mine->id, 10);
        $this->makeCustomerMetric($theirs->id, 10, ['user_id' => $this->admin->user_id + 1]);

        $response = $this->actingAs($this->admin)
            ->getJson(route('reports.customer_intelligence.data'))
            ->assertOk();

        $this->assertEquals(1, $response->json('rfm.totals.analyzed_customers'));
        $ids = collect($response->json('rfm.segment_customers'))->flatten(1)->pluck('customer_id');
        $this->assertTrue($ids->contains($mine->id));
        $this->assertFalse($ids->contains($theirs->id));
    }

    public function test_export_redirects_users_without_sls_role(): void
    {
        $this->actingAs($this->cashier)
            ->get(route('reports.customer_intelligence.export'))
            ->assertRedirect('/admin/home');
    }

    public function test_export_streams_segment_csv_scoped_to_tenant(): void
    {
        $mine = Customer::factory()->create(['name' => 'Maria', 'user_id' => $this->admin->user_id]);
        $theirs = Customer::factory()->create(['name' => 'Intruder', 'user_id' => $this->admin->user_id + 1]);
        $this->makeCustomerMetric($mine->id, 10, ['spend_total' => 250, 'profit' => 75]);
        $this->makeCustomerMetric($theirs->id, 10, ['user_id' => $this->admin->user_id + 1]);

        $asOf = Carbon::today(config('app.timezone', 'Asia/Manila'))->toDateString();

        $response = $this->actingAs($this->admin)->get(route('reports.customer_intelligence.export'));

        $response->assertOk();
        $response->assertDownload("customer_segments_{$asOf}_365d.csv");

        $content = $response->streamedContent();
        $lines = array_map('str_getcsv', explode("\n", trim($content)));
        $this->assertSame(['Customer', 'Segment', 'Recency (Days)', 'Frequency', 'Net Spend', 'Lifetime Profit', 'R', 'F', 'M'], $lines[0]);
        $this->assertCount(2, $lines);
        $this->assertSame('Maria', $lines[1][0]);
        $this->assertContains($lines[1][1], array_keys(\App\Services\Bi\CustomerIntelligenceService::SEGMENTS));
        $this->assertSame('250', $lines[1][4]);
        $this->assertStringNotContainsString('Intruder', $content);
    }

    public function test_export_falls_back_to_default_window_on_bad_input(): void
    {
        $asOf = Carbon::today(config('app.timezone', 'Asia/Manila'))->toDateString();

        $this->actingAs($this->admin)
            ->get(route('reports.customer_intelligence.export', ['window_days' => 7]))
            ->assertOk()
            ->assertDownload("customer_segments_{$asOf}_365d.csv");
    }

    public function test_funnel_range_swaps_reversed_dates(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson(route('reports.customer_intelligence.data', [
                'start_date' => '2026-03-31',
                'end_date' => '2026-03-01',
            ]))
            ->assertOk();

        $this->assertSame('2026-03-01', $response->json('funnel.from'));
        $this->assertSame('2026-03-31', $response->json('funnel.to'));
    }
}
