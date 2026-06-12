<?php

namespace Tests\Feature\Services;

use App\Models\Bi\DailyCustomerMetric;
use App\Models\CustomerRelations\Customer;
use App\Models\Ecommerce\EcommerceOrder;
use App\Models\Ecommerce\ShopVisit;
use App\Services\Bi\CustomerIntelligenceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerIntelligenceServiceTest extends TestCase
{
    use RefreshDatabase;

    protected CustomerIntelligenceService $service;

    protected Carbon $asOf;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(CustomerIntelligenceService::class);
        $this->asOf = Carbon::today(config('app.timezone', 'Asia/Manila'));
    }

    protected function makeCustomerMetric(int $customerId, int $daysAgo, array $overrides = []): DailyCustomerMetric
    {
        return DailyCustomerMetric::create(array_merge([
            'user_id' => 1,
            'customer_id' => $customerId,
            'date' => $this->asOf->copy()->subDays($daysAgo)->toDateString(),
            'spend_total' => 100,
            'profit' => 30,
            'transactions' => 1,
        ], $overrides));
    }

    protected function makeVisit(array $overrides = []): ShopVisit
    {
        return ShopVisit::create(array_merge([
            'session_id' => 'sess-'.fake()->unique()->numerify('######'),
            'visitor_id' => fake()->uuid(),
            'ip_address' => '127.0.0.1',
            'page_visited' => '/shop',
            'page_type' => 'browse',
            'entered_at' => now(),
        ], $overrides));
    }

    public function test_rfm_metrics_computed_from_aggregates(): void
    {
        $customer = Customer::factory()->create(['name' => 'Maria', 'user_id' => 1]);
        $this->makeCustomerMetric($customer->id, 10, ['spend_total' => 200, 'transactions' => 2, 'profit' => 60]);
        $this->makeCustomerMetric($customer->id, 40, ['spend_total' => 300, 'refund_total' => 50, 'transactions' => 3, 'profit' => 90]);

        $data = $this->service->getRfmData(1, $this->asOf);

        $this->assertSame(1, $data['totals']['analyzed_customers']);
        $allCustomers = collect($data['segment_customers'])->flatten(1);
        $row = $allCustomers->firstWhere('customer_id', $customer->id);

        $this->assertSame('Maria', $row['name']);
        $this->assertSame(10, $row['recency_days']);
        $this->assertSame(5, $row['frequency']);
        $this->assertSame(450.0, $row['monetary']);
        $this->assertSame(150.0, $row['lifetime_profit']);
    }

    public function test_segments_assigned_across_rfm_grid(): void
    {
        $names = [];
        foreach ([
            'Alice' => ['recency' => 2, 'frequency' => 20],
            'Ben' => ['recency' => 30, 'frequency' => 2],
            'Cara' => ['recency' => 60, 'frequency' => 3],
            'Dan' => ['recency' => 120, 'frequency' => 4],
            'Eve' => ['recency' => 300, 'frequency' => 1],
        ] as $name => $profile) {
            $customer = Customer::factory()->create(['name' => $name, 'user_id' => 1]);
            $names[$name] = $customer->id;
            $this->makeCustomerMetric($customer->id, $profile['recency'], [
                'transactions' => $profile['frequency'],
                'spend_total' => $profile['frequency'] * 100,
            ]);
        }

        $data = $this->service->getRfmData(1, $this->asOf);

        $segmentOf = [];
        foreach ($data['segment_customers'] as $segment => $members) {
            foreach ($members as $member) {
                $segmentOf[$member['customer_id']] = $segment;
            }
        }

        $this->assertSame('Champions', $segmentOf[$names['Alice']]);
        $this->assertSame('Potential Loyalist', $segmentOf[$names['Ben']]);
        $this->assertSame('Loyal', $segmentOf[$names['Cara']]);
        $this->assertSame('At Risk', $segmentOf[$names['Dan']]);
        $this->assertSame('Lost', $segmentOf[$names['Eve']]);

        $segments = collect($data['segments'])->keyBy('segment');
        $this->assertSame(1, $segments['Champions']['count']);
        $this->assertSame(1, $segments['At Risk']['count']);
        $this->assertSame(20.0, $segments['Champions']['pct']);
    }

    public function test_recent_single_purchase_customer_is_new(): void
    {
        $profiles = [
            'Newbie' => ['recency' => 2, 'frequency' => 1],
            'B' => ['recency' => 10, 'frequency' => 5],
            'C' => ['recency' => 100, 'frequency' => 6],
            'D' => ['recency' => 200, 'frequency' => 7],
            'E' => ['recency' => 300, 'frequency' => 8],
        ];

        $newbieId = null;
        foreach ($profiles as $name => $profile) {
            $customer = Customer::factory()->create(['name' => $name, 'user_id' => 1]);
            if ($name === 'Newbie') {
                $newbieId = $customer->id;
            }
            $this->makeCustomerMetric($customer->id, $profile['recency'], ['transactions' => $profile['frequency']]);
        }

        $data = $this->service->getRfmData(1, $this->asOf);

        $newMembers = collect($data['segment_customers']['New'] ?? []);
        $this->assertTrue($newMembers->contains('customer_id', $newbieId));
    }

    public function test_refund_only_days_do_not_count_as_purchases(): void
    {
        $customer = Customer::factory()->create(['user_id' => 1]);
        $this->makeCustomerMetric($customer->id, 100, ['transactions' => 2, 'spend_total' => 500]);
        $this->makeCustomerMetric($customer->id, 5, [
            'transactions' => 0,
            'spend_total' => 0,
            'refund_total' => 100,
            'refund_count' => 1,
            'profit' => -30,
        ]);

        $data = $this->service->getRfmData(1, $this->asOf);

        $row = collect($data['segment_customers'])->flatten(1)->firstWhere('customer_id', $customer->id);
        $this->assertSame(100, $row['recency_days'], 'Refund-only day must not reset recency.');
        $this->assertSame(400.0, $row['monetary'], 'Refunds reduce net spend.');
    }

    public function test_refund_only_customers_are_excluded(): void
    {
        $customer = Customer::factory()->create(['user_id' => 1]);
        $this->makeCustomerMetric($customer->id, 10, [
            'transactions' => 0,
            'spend_total' => 0,
            'refund_total' => 50,
            'refund_count' => 1,
        ]);

        $data = $this->service->getRfmData(1, $this->asOf);

        $this->assertSame(0, $data['totals']['analyzed_customers']);
    }

    public function test_rfm_scopes_to_tenant(): void
    {
        $mine = Customer::factory()->create(['user_id' => 1]);
        $theirs = Customer::factory()->create(['user_id' => 2]);
        $this->makeCustomerMetric($mine->id, 10);
        $this->makeCustomerMetric($theirs->id, 10, ['user_id' => 2]);

        $data = $this->service->getRfmData(1, $this->asOf);

        $this->assertSame(1, $data['totals']['analyzed_customers']);
        $ids = collect($data['segment_customers'])->flatten(1)->pluck('customer_id');
        $this->assertTrue($ids->contains($mine->id));
        $this->assertFalse($ids->contains($theirs->id));
    }

    public function test_window_excludes_old_activity_but_lifetime_profit_includes_it(): void
    {
        $customer = Customer::factory()->create(['user_id' => 1]);
        $this->makeCustomerMetric($customer->id, 10, ['spend_total' => 100, 'profit' => 30]);
        $this->makeCustomerMetric($customer->id, 400, ['spend_total' => 9000, 'profit' => 2000]);

        $data = $this->service->getRfmData(1, $this->asOf, 365);

        $row = collect($data['segment_customers'])->flatten(1)->firstWhere('customer_id', $customer->id);
        $this->assertSame(100.0, $row['monetary'], 'Window excludes the 400-day-old purchase.');
        $this->assertSame(2030.0, $row['lifetime_profit'], 'Lifetime profit is all-time.');
    }

    public function test_customer_active_only_outside_window_is_excluded(): void
    {
        $customer = Customer::factory()->create(['user_id' => 1]);
        $this->makeCustomerMetric($customer->id, 120);

        $data = $this->service->getRfmData(1, $this->asOf, 90);

        $this->assertSame(0, $data['totals']['analyzed_customers']);
    }

    public function test_empty_tenant_returns_zeroed_structure(): void
    {
        $data = $this->service->getRfmData(1, $this->asOf);

        $this->assertSame(0, $data['totals']['analyzed_customers']);
        $this->assertNull($data['totals']['avg_lifetime_profit']);
        $this->assertCount(count(CustomerIntelligenceService::SEGMENTS), $data['segments']);
        $this->assertSame([], $data['segment_customers']);
    }

    public function test_funnel_counts_stages_and_conversion(): void
    {
        $from = $this->asOf->copy()->subDays(6);

        $visitorA = fake()->uuid();
        $visitorB = fake()->uuid();
        $visitorC = fake()->uuid();

        // A browses twice (dedup to 1), views a product, adds to cart, checks out.
        $this->makeVisit(['visitor_id' => $visitorA]);
        $this->makeVisit(['visitor_id' => $visitorA]);
        $this->makeVisit(['visitor_id' => $visitorA, 'page_type' => 'product']);
        $this->makeVisit(['visitor_id' => $visitorA, 'page_type' => 'product', 'action' => 'add_to_cart']);
        $this->makeVisit(['visitor_id' => $visitorA, 'page_type' => 'checkout']);

        // B views a product only. C just browses.
        $this->makeVisit(['visitor_id' => $visitorB, 'page_type' => 'product']);
        $this->makeVisit(['visitor_id' => $visitorC]);

        $customer = Customer::factory()->create(['user_id' => 1]);
        EcommerceOrder::factory()->create(['customer_id' => $customer->id, 'status' => EcommerceOrder::STATUS_PAID]);
        EcommerceOrder::factory()->create(['customer_id' => $customer->id, 'status' => EcommerceOrder::STATUS_PENDING]);

        $funnel = $this->service->getFunnelData(1, $from, $this->asOf);

        $stages = collect($funnel['stages'])->keyBy('stage');
        $this->assertSame(3, $stages['Visitors']['count']);
        $this->assertSame(2, $stages['Viewed a Product']['count']);
        $this->assertSame(1, $stages['Added to Cart']['count']);
        $this->assertSame(1, $stages['Reached Checkout']['count']);
        $this->assertSame(2, $stages['Placed an Order']['count']);
        $this->assertSame(1, $stages['Order Completed']['count']);

        $this->assertNull($stages['Visitors']['pct_of_previous']);
        $this->assertSame(66.7, $stages['Viewed a Product']['pct_of_previous']);
        $this->assertSame(round(1 / 3 * 100, 2), $funnel['overall_conversion_pct']);
    }

    public function test_funnel_orders_scope_to_tenant_via_customer(): void
    {
        $mine = Customer::factory()->create(['user_id' => 1]);
        $theirs = Customer::factory()->create(['user_id' => 2]);
        EcommerceOrder::factory()->create(['customer_id' => $mine->id, 'status' => EcommerceOrder::STATUS_PICKED_UP]);
        EcommerceOrder::factory()->create(['customer_id' => $theirs->id, 'status' => EcommerceOrder::STATUS_PAID]);

        $funnel = $this->service->getFunnelData(1, $this->asOf->copy()->subDays(6), $this->asOf);

        $stages = collect($funnel['stages'])->keyBy('stage');
        $this->assertSame(1, $stages['Placed an Order']['count']);
        $this->assertSame(1, $stages['Order Completed']['count'], 'PICKED_UP counts as completed.');
    }

    public function test_funnel_excludes_activity_outside_range(): void
    {
        $visit = $this->makeVisit([]);
        $visit->forceFill(['created_at' => $this->asOf->copy()->subDays(60)])->save();

        $funnel = $this->service->getFunnelData(1, $this->asOf->copy()->subDays(6), $this->asOf);

        $stages = collect($funnel['stages'])->keyBy('stage');
        $this->assertSame(0, $stages['Visitors']['count']);
        $this->assertNull($funnel['overall_conversion_pct']);
    }
}
