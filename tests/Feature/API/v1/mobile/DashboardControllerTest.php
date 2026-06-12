<?php

namespace Tests\Feature\API\v1\mobile;

use App\Models\Employees\Role;
use App\Models\Pos\Sale;
use App\Models\Pos\SaleLine;
use App\Models\Products\Category;
use App\Models\Products\Item;
use App\Models\Settings\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
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

    public function test_can_get_sales_ticker(): void
    {
        Passport::actingAs($this->user);

        Sale::factory()->count(5)->create([
            'user_id' => $this->user->user_id,
            'store_id' => $this->store->id,
            'sales_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/mobile/dashboard/sales-ticker');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'sales' => [
                    '*' => [
                        'id',
                        'son',
                        'total',
                        'customer_name',
                        'store_name',
                        'cashier_name',
                        'payment_type',
                        'time_ago',
                        'created_at',
                    ],
                ],
                'last_updated',
            ],
        ]);
    }

    public function test_sales_ticker_respects_limit_parameter(): void
    {
        Passport::actingAs($this->user);

        Sale::factory()->count(10)->create([
            'user_id' => $this->user->user_id,
            'store_id' => $this->store->id,
            'sales_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/mobile/dashboard/sales-ticker?limit=3');

        $response->assertStatus(200);
        $this->assertCount(3, $response->json('data.sales'));
    }

    public function test_can_get_top_products(): void
    {
        Passport::actingAs($this->user);

        $category = Category::factory()->create(['user_id' => $this->user->user_id]);
        $item = Item::factory()->create([
            'user_id' => $this->user->user_id,
            'category_id' => $category->id,
        ]);

        $sale = Sale::factory()->create([
            'user_id' => $this->user->user_id,
            'store_id' => $this->store->id,
            'sales_by' => $this->user->id,
        ]);

        SaleLine::factory()->forSale($sale->id)->forItem($item->id)->create();

        $response = $this->getJson('/api/v1/mobile/dashboard/top-products');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'products' => [
                    '*' => [
                        'id',
                        'name',
                        'barcode',
                        'category',
                        'total_qty_sold',
                        'total_revenue',
                        'transaction_count',
                    ],
                ],
                'period',
                'date_range' => [
                    'start',
                    'end',
                ],
            ],
        ]);
    }

    public function test_top_products_respects_period_parameter(): void
    {
        Passport::actingAs($this->user);

        $response = $this->getJson('/api/v1/mobile/dashboard/top-products?period=this_week');

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'period' => 'this_week',
            ],
        ]);
    }

    public function test_can_get_revenue_comparison_daily(): void
    {
        Passport::actingAs($this->user);

        $response = $this->getJson('/api/v1/mobile/dashboard/revenue-comparison?period=daily');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'comparison' => [
                    'current' => [
                        'label',
                        'date',
                        'total_sales',
                        'total_profit',
                        'transaction_count',
                        'refunds',
                    ],
                    'previous' => [
                        'label',
                        'date',
                        'total_sales',
                        'total_profit',
                        'transaction_count',
                        'refunds',
                    ],
                    'change' => [
                        'sales_change',
                        'profit_change',
                        'transaction_change',
                    ],
                ],
                'period',
            ],
        ]);
        $response->assertJson([
            'data' => [
                'comparison' => [
                    'current' => ['label' => 'Today'],
                    'previous' => ['label' => 'Yesterday'],
                ],
                'period' => 'daily',
            ],
        ]);
    }

    public function test_can_get_revenue_comparison_weekly(): void
    {
        Passport::actingAs($this->user);

        $response = $this->getJson('/api/v1/mobile/dashboard/revenue-comparison?period=weekly');

        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                'comparison' => [
                    'current' => ['label' => 'This Week'],
                    'previous' => ['label' => 'Last Week'],
                ],
                'period' => 'weekly',
            ],
        ]);
    }

    public function test_can_get_revenue_comparison_monthly(): void
    {
        Passport::actingAs($this->user);

        $response = $this->getJson('/api/v1/mobile/dashboard/revenue-comparison?period=monthly');

        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                'comparison' => [
                    'current' => ['label' => 'This Month'],
                    'previous' => ['label' => 'Last Month'],
                ],
                'period' => 'monthly',
            ],
        ]);
    }

    public function test_revenue_comparison_calculates_change_correctly(): void
    {
        Passport::actingAs($this->user);

        Sale::factory()->create([
            'user_id' => $this->user->user_id,
            'store_id' => $this->store->id,
            'sales_by' => $this->user->id,
            'total' => 1000,
            'profit' => 200,
            'created_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/mobile/dashboard/revenue-comparison?period=daily');

        $response->assertStatus(200);
        $data = $response->json('data.comparison');
        $this->assertEquals(1000, $data['current']['total_sales']);
        $this->assertEquals(1, $data['current']['transaction_count']);
    }

    public function test_can_get_staff_leaderboard(): void
    {
        Passport::actingAs($this->user);

        Sale::factory()->count(3)->create([
            'user_id' => $this->user->user_id,
            'store_id' => $this->store->id,
            'sales_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/mobile/dashboard/staff-leaderboard');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'leaderboard' => [
                    '*' => [
                        'rank',
                        'id',
                        'name',
                        'role',
                        'total_transactions',
                        'total_sales',
                        'total_profit',
                        'average_transaction',
                    ],
                ],
                'period',
                'date_range' => [
                    'start',
                    'end',
                ],
            ],
        ]);
    }

    public function test_staff_leaderboard_ranks_correctly(): void
    {
        Passport::actingAs($this->user);

        $topPerformer = User::factory()->create([
            'role_id' => $this->role->id,
            'user_id' => $this->user->user_id,
        ]);

        Sale::factory()->create([
            'user_id' => $this->user->user_id,
            'store_id' => $this->store->id,
            'sales_by' => $this->user->id,
            'total' => 500,
        ]);

        Sale::factory()->create([
            'user_id' => $this->user->user_id,
            'store_id' => $this->store->id,
            'sales_by' => $topPerformer->id,
            'total' => 1000,
        ]);

        $response = $this->getJson('/api/v1/mobile/dashboard/staff-leaderboard');

        $response->assertStatus(200);
        $leaderboard = $response->json('data.leaderboard');
        $this->assertEquals($topPerformer->id, $leaderboard[0]['id']);
        $this->assertEquals(1, $leaderboard[0]['rank']);
    }

    public function test_staff_leaderboard_respects_period_parameter(): void
    {
        Passport::actingAs($this->user);

        $response = $this->getJson('/api/v1/mobile/dashboard/staff-leaderboard?period=this_month');

        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                'period' => 'this_month',
            ],
        ]);
    }

    public function test_unauthenticated_user_cannot_access_sales_ticker(): void
    {
        $response = $this->getJson('/api/v1/mobile/dashboard/sales-ticker');

        $response->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_access_top_products(): void
    {
        $response = $this->getJson('/api/v1/mobile/dashboard/top-products');

        $response->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_access_revenue_comparison(): void
    {
        $response = $this->getJson('/api/v1/mobile/dashboard/revenue-comparison');

        $response->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_access_staff_leaderboard(): void
    {
        $response = $this->getJson('/api/v1/mobile/dashboard/staff-leaderboard');

        $response->assertStatus(401);
    }
}
