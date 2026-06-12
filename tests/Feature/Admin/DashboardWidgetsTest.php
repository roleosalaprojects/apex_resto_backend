<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\Dashboard\RevenueComparison;
use App\Livewire\Admin\Dashboard\SalesTicker;
use App\Livewire\Admin\Dashboard\StaffLeaderboard;
use App\Livewire\Admin\Dashboard\TopProducts;
use App\Models\Employees\Role;
use App\Models\Pos\Sale;
use App\Models\Pos\SaleLine;
use App\Models\Products\Item;
use App\Models\Settings\Pos;
use App\Models\Settings\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardWidgetsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $cashier;

    protected Store $store;

    protected Pos $pos;

    protected Role $role;

    protected function setUp(): void
    {
        parent::setUp();

        $this->role = Role::factory()->admin()->create();
        $this->admin = User::factory()->create(['role_id' => $this->role->id]);
        $this->cashier = User::factory()->create(['name' => 'Test Cashier', 'role_id' => $this->role->id]);
        $this->store = Store::factory()->create();
        $this->pos = Pos::factory()->create(['store_id' => $this->store->id]);
    }

    protected function createSale(array $attributes = []): Sale
    {
        return Sale::factory()->create(array_merge([
            'sales_by' => $this->cashier->id,
            'user_id' => $this->cashier->id,
            'store_id' => $this->store->id,
            'pos_id' => $this->pos->id,
        ], $attributes));
    }

    public function test_dashboard_page_loads_with_widgets(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.home'));

        $response->assertStatus(200);
        $response->assertSeeLivewire('admin.dashboard.revenue-comparison');
        $response->assertSeeLivewire('admin.dashboard.top-products');
        $response->assertSeeLivewire('admin.dashboard.staff-leaderboard');
        $response->assertSeeLivewire('admin.dashboard.sales-ticker');
    }

    public function test_sales_ticker_shows_recent_sales(): void
    {
        $sale = $this->createSale([
            'type' => 0,
            'total' => 500,
            'created_at' => now(),
        ]);

        Livewire::test(SalesTicker::class)
            ->assertSee('Sale #'.$sale->id)
            ->assertSee('500.00');
    }

    public function test_sales_ticker_excludes_refunds(): void
    {
        $sale = $this->createSale([
            'type' => 0,
            'total' => 500,
        ]);

        $refund = $this->createSale([
            'type' => 1,
            'total' => 100,
        ]);

        Livewire::test(SalesTicker::class)
            ->assertSee('Sale #'.$sale->id)
            ->assertDontSee('Sale #'.$refund->id);
    }

    public function test_sales_ticker_checks_for_new_sales(): void
    {
        $sale1 = $this->createSale([
            'type' => 0,
            'total' => 500,
        ]);

        $component = Livewire::test(SalesTicker::class)
            ->assertSee('Sale #'.$sale1->id);

        // Create new sale
        $sale2 = $this->createSale([
            'type' => 0,
            'total' => 750,
        ]);

        $component->call('checkForNewSales')
            ->assertSee('Sale #'.$sale2->id);
    }

    public function test_top_products_shows_bestsellers(): void
    {
        $item = Item::factory()->create(['name' => 'Test Product']);

        $sale = $this->createSale([
            'type' => 0,
            'total' => 1000,
        ]);

        SaleLine::factory()->create([
            'sales_id' => $sale->id,
            'item_id' => $item->id,
            'qty' => 10,
            'sub_total' => 1000,
        ]);

        Livewire::test(TopProducts::class)
            ->assertSee('Test Product')
            ->assertSee('10 sold');
    }

    public function test_top_products_orders_by_total_sales(): void
    {
        $item1 = Item::factory()->create(['name' => 'Low Seller']);
        $item2 = Item::factory()->create(['name' => 'High Seller']);

        $sale = $this->createSale(['type' => 0]);

        SaleLine::factory()->create([
            'sales_id' => $sale->id,
            'item_id' => $item1->id,
            'qty' => 5,
            'sub_total' => 500,
        ]);

        SaleLine::factory()->create([
            'sales_id' => $sale->id,
            'item_id' => $item2->id,
            'qty' => 10,
            'sub_total' => 2000,
        ]);

        $component = Livewire::test(TopProducts::class);

        // High Seller should be ranked #1
        $this->assertEquals('High Seller', $component->get('topProducts')->first()->name);
    }

    public function test_revenue_comparison_shows_today_sales(): void
    {
        $this->createSale([
            'type' => 0,
            'total' => 1500,
            'created_at' => now(),
        ]);

        Livewire::test(RevenueComparison::class)
            ->assertSee('1,500.00')
            ->assertSee('Today');
    }

    public function test_revenue_comparison_shows_yesterday_sales(): void
    {
        $this->createSale([
            'type' => 0,
            'total' => 2000,
            'created_at' => now()->subDay(),
        ]);

        Livewire::test(RevenueComparison::class)
            ->assertSee('2,000.00')
            ->assertSee('Yesterday');
    }

    public function test_revenue_comparison_shows_last_week_same_day(): void
    {
        $this->createSale([
            'type' => 0,
            'total' => 3000,
            'created_at' => now()->subWeek(),
        ]);

        $dayName = now()->subWeek()->format('l');

        Livewire::test(RevenueComparison::class)
            ->assertSee('3,000.00')
            ->assertSee('Last '.$dayName);
    }

    public function test_revenue_comparison_calculates_percentage_change(): void
    {
        // Today: 1000
        $this->createSale([
            'type' => 0,
            'total' => 1000,
            'created_at' => now(),
        ]);

        // Yesterday: 500 (today is 100% more)
        $this->createSale([
            'type' => 0,
            'total' => 500,
            'created_at' => now()->subDay(),
        ]);

        $component = Livewire::test(RevenueComparison::class);

        $this->assertEquals(100, $component->get('vsYesterdayPercent'));
    }

    public function test_staff_leaderboard_shows_top_cashiers(): void
    {
        $this->createSale([
            'type' => 0,
            'total' => 5000,
            'created_at' => now(),
        ]);

        Livewire::test(StaffLeaderboard::class)
            ->assertSee('Test Cashier')
            ->assertSee('5,000.00');
    }

    public function test_staff_leaderboard_orders_by_total_sales(): void
    {
        $cashier1 = User::factory()->create(['name' => 'Low Performer', 'role_id' => $this->role->id]);
        $cashier2 = User::factory()->create(['name' => 'Top Performer', 'role_id' => $this->role->id]);

        $this->createSale([
            'type' => 0,
            'total' => 1000,
            'sales_by' => $cashier1->id,
            'created_at' => now(),
        ]);

        $this->createSale([
            'type' => 0,
            'total' => 5000,
            'sales_by' => $cashier2->id,
            'created_at' => now(),
        ]);

        $component = Livewire::test(StaffLeaderboard::class);

        // Top Performer should be #1
        $this->assertEquals('Top Performer', $component->get('leaderboard')->first()->name);
    }

    public function test_staff_leaderboard_calculates_total_team_sales(): void
    {
        $this->createSale([
            'type' => 0,
            'total' => 3000,
            'created_at' => now(),
        ]);

        $this->createSale([
            'type' => 0,
            'total' => 2000,
            'sales_by' => $this->admin->id,
            'created_at' => now(),
        ]);

        $component = Livewire::test(StaffLeaderboard::class);

        $this->assertEquals(5000, $component->get('totalTeamSales'));
    }

    public function test_widgets_can_be_refreshed(): void
    {
        Livewire::test(TopProducts::class)
            ->call('refresh')
            ->assertStatus(200);

        Livewire::test(RevenueComparison::class)
            ->call('refresh')
            ->assertStatus(200);

        Livewire::test(StaffLeaderboard::class)
            ->call('refresh')
            ->assertStatus(200);
    }
}
