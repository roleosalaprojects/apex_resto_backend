<?php

namespace Tests\Feature\API\v1\mobile;

use App\Models\CustomerRelations\Customer;
use App\Models\CustomerRelations\CustomerPointsHistory;
use App\Models\Employees\Role;
use App\Models\Pos\Sale;
use App\Models\Settings\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class CustomerAnalyticsControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Role $role;

    protected Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->role = Role::factory()->admin()->create();
        $this->user = User::factory()->create([
            'role_id' => $this->role->id,
            'user_id' => 1,
        ]);
        $this->store = Store::factory()->create(['user_id' => 1]);
    }

    public function test_can_get_top_customers(): void
    {
        Passport::actingAs($this->user);

        $customer = Customer::factory()->create([
            'user_id' => $this->user->user_id,
            'points' => 100,
        ]);

        Sale::factory()->count(3)->forCustomer($customer->id)->create([
            'user_id' => $this->user->user_id,
            'store_id' => $this->store->id,
            'sales_by' => $this->user->id,
            'total' => 500,
        ]);

        $response = $this->getJson('/api/v1/mobile/customers/analytics/top');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'top_customers' => [
                    '*' => [
                        'id',
                        'name',
                        'code',
                        'phone',
                        'email',
                        'total_transactions',
                        'total_spent',
                        'average_transaction',
                        'last_purchase_date',
                        'loyalty_points_balance',
                        'rank',
                    ],
                ],
                'summary' => [
                    'total_customers_with_purchases',
                    'total_revenue_from_customers',
                    'average_customer_spend',
                ],
            ],
        ]);
    }

    public function test_top_customers_respects_limit_parameter(): void
    {
        Passport::actingAs($this->user);

        $customers = Customer::factory()->count(5)->create([
            'user_id' => $this->user->user_id,
        ]);

        foreach ($customers as $customer) {
            Sale::factory()->forCustomer($customer->id)->create([
                'user_id' => $this->user->user_id,
                'store_id' => $this->store->id,
                'sales_by' => $this->user->id,
            ]);
        }

        $response = $this->getJson('/api/v1/mobile/customers/analytics/top?limit=2');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data.top_customers'));
    }

    public function test_can_get_customer_trends(): void
    {
        Passport::actingAs($this->user);

        $customer = Customer::factory()->create([
            'user_id' => $this->user->user_id,
        ]);

        Sale::factory()->forCustomer($customer->id)->create([
            'user_id' => $this->user->user_id,
            'store_id' => $this->store->id,
            'sales_by' => $this->user->id,
            'created_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/mobile/customers/analytics/trends');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'trends' => [
                    '*' => [
                        'period',
                        'new_customers',
                        'returning_customers',
                        'total_active_customers',
                        'total_transactions',
                        'total_revenue',
                        'points_earned',
                        'points_redeemed',
                    ],
                ],
                'summary' => [
                    'period_start',
                    'period_end',
                    'total_new_customers',
                    'total_returning_customers',
                    'average_daily_active',
                    'customer_retention_rate',
                ],
            ],
        ]);
    }

    public function test_customer_trends_respects_period_parameter(): void
    {
        Passport::actingAs($this->user);

        $response = $this->getJson('/api/v1/mobile/customers/analytics/trends?period=monthly');

        $response->assertStatus(200);
    }

    public function test_can_get_points_history(): void
    {
        Passport::actingAs($this->user);

        $customer = Customer::factory()->create([
            'user_id' => $this->user->user_id,
        ]);

        CustomerPointsHistory::factory()->earned()->create([
            'customer_id' => $customer->id,
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/mobile/customers/analytics/points-history');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'history' => [
                    '*' => [
                        'id',
                        'customer',
                        'type',
                        'points',
                        'balance_after',
                        'reference_type',
                        'description',
                        'store',
                        'created_at',
                    ],
                ],
                'summary' => [
                    'total_points_earned',
                    'total_points_redeemed',
                    'total_points_expired',
                    'total_points_adjusted',
                    'net_points_issued',
                ],
                'pagination' => [
                    'total',
                    'limit',
                    'offset',
                    'has_more',
                ],
            ],
        ]);
    }

    public function test_points_history_filters_by_type(): void
    {
        Passport::actingAs($this->user);

        $customer = Customer::factory()->create([
            'user_id' => $this->user->user_id,
        ]);

        CustomerPointsHistory::factory()->earned()->create([
            'customer_id' => $customer->id,
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
        ]);
        CustomerPointsHistory::factory()->redeemed()->create([
            'customer_id' => $customer->id,
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/mobile/customers/analytics/points-history?type=earned');

        $response->assertStatus(200);
        $history = $response->json('data.history');
        foreach ($history as $record) {
            $this->assertEquals('earned', $record['type']);
        }
    }

    public function test_points_history_filters_by_customer(): void
    {
        Passport::actingAs($this->user);

        $customer1 = Customer::factory()->create(['user_id' => $this->user->user_id]);
        $customer2 = Customer::factory()->create(['user_id' => $this->user->user_id]);

        CustomerPointsHistory::factory()->create([
            'customer_id' => $customer1->id,
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
        ]);
        CustomerPointsHistory::factory()->create([
            'customer_id' => $customer2->id,
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
        ]);

        $response = $this->getJson("/api/v1/mobile/customers/analytics/points-history?customer_id={$customer1->id}");

        $response->assertStatus(200);
        $history = $response->json('data.history');
        foreach ($history as $record) {
            $this->assertEquals($customer1->id, $record['customer']['id']);
        }
    }

    public function test_can_get_points_summary(): void
    {
        Passport::actingAs($this->user);

        $customer = Customer::factory()->create([
            'user_id' => $this->user->user_id,
            'points' => 100,
            'status' => true,
        ]);

        Sale::factory()->forCustomer($customer->id)->create([
            'user_id' => $this->user->user_id,
            'store_id' => $this->store->id,
            'sales_by' => $this->user->id,
            'acquired_points' => 10,
            'points_used' => 5,
        ]);

        $response = $this->getJson('/api/v1/mobile/customers/analytics/points-summary');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'total_points_in_circulation',
                'total_customers_with_points',
                'average_points_per_customer',
                'points_earned_this_period',
                'points_redeemed_this_period',
                'redemption_rate',
                'top_point_holders',
            ],
        ]);
    }

    public function test_points_summary_returns_top_point_holders(): void
    {
        Passport::actingAs($this->user);

        Customer::factory()->create([
            'user_id' => $this->user->user_id,
            'points' => 500,
            'status' => true,
        ]);
        Customer::factory()->create([
            'user_id' => $this->user->user_id,
            'points' => 300,
            'status' => true,
        ]);
        Customer::factory()->create([
            'user_id' => $this->user->user_id,
            'points' => 100,
            'status' => true,
        ]);

        $response = $this->getJson('/api/v1/mobile/customers/analytics/points-summary');

        $response->assertStatus(200);
        $topHolders = $response->json('data.top_point_holders');
        $this->assertGreaterThanOrEqual(1, count($topHolders));
        $this->assertEquals(500, $topHolders[0]['points_balance']);
    }

    public function test_unauthenticated_user_cannot_access_top_customers(): void
    {
        $response = $this->getJson('/api/v1/mobile/customers/analytics/top');

        $response->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_access_trends(): void
    {
        $response = $this->getJson('/api/v1/mobile/customers/analytics/trends');

        $response->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_access_points_history(): void
    {
        $response = $this->getJson('/api/v1/mobile/customers/analytics/points-history');

        $response->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_access_points_summary(): void
    {
        $response = $this->getJson('/api/v1/mobile/customers/analytics/points-summary');

        $response->assertStatus(401);
    }
}
