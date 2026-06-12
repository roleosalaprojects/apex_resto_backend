<?php

namespace Tests\Feature\API\v1\openclaw;

use App\Models\ApiToken;
use App\Models\CustomerRelations\Customer;
use App\Models\Employees\Role;
use App\Models\Pos\Sale;
use App\Models\Pos\SaleLine;
use App\Models\Products\Item;
use App\Models\Settings\Store;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpenclawSnapshotTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected User $otherOwner;

    protected Store $store;

    protected string $plainToken;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::factory()->admin()->create();
        $this->owner = User::factory()->create(['role_id' => $role->id]);
        $this->owner->forceFill(['user_id' => $this->owner->id])->save();

        $this->otherOwner = User::factory()->create(['role_id' => $role->id]);
        $this->otherOwner->forceFill(['user_id' => $this->otherOwner->id])->save();

        $this->store = Store::factory()->create(['user_id' => $this->owner->user_id]);

        $this->plainToken = ApiToken::generatePlainToken();
        ApiToken::create([
            'user_id' => $this->owner->user_id,
            'name' => 'Test Bot',
            'token' => ApiToken::hashToken($this->plainToken),
        ]);
    }

    private function authed(): self
    {
        return $this->withHeader('Authorization', "Bearer {$this->plainToken}");
    }

    public function test_snapshot_aggregates_today_sales_for_tenant_only(): void
    {
        // Two sales for our tenant today.
        Sale::factory()->count(2)->create([
            'user_id' => $this->owner->user_id,
            'store_id' => $this->store->id,
            'sales_by' => $this->owner->id,
            'type' => 0,
            'cancelled' => 0,
            'total' => 100,
            'profit' => 30,
            'created_at' => Carbon::today(config('app.timezone'))->addHours(12),
        ]);

        // One sale belonging to another tenant — must NOT appear.
        Sale::factory()->create([
            'user_id' => $this->otherOwner->user_id,
            'store_id' => $this->store->id,
            'sales_by' => $this->otherOwner->id,
            'type' => 0,
            'cancelled' => 0,
            'total' => 999,
            'profit' => 500,
            'created_at' => Carbon::today(config('app.timezone'))->addHours(12),
        ]);

        $response = $this->authed()->getJson('/api/v1/openclaw/snapshot');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'tenant_user_id',
                    'today' => ['sales_count', 'sales_total', 'profit', 'refund_count', 'refund_total'],
                    'yesterday',
                    'month_to_date',
                    'inventory' => ['low_stock_count', 'out_of_stock_count'],
                    'customers' => ['outstanding_credit', 'active_credit_count', 'total_points_balance'],
                ],
            ])
            ->assertJsonPath('data.today.sales_count', 2)
            ->assertJsonPath('data.today.sales_total', 200);
    }

    public function test_sales_summary_endpoint_returns_daily_breakdown(): void
    {
        Sale::factory()->count(3)->create([
            'user_id' => $this->owner->user_id,
            'store_id' => $this->store->id,
            'sales_by' => $this->owner->id,
            'type' => 0,
            'cancelled' => 0,
            'total' => 50,
            'profit' => 10,
            'created_at' => Carbon::today(config('app.timezone'))->addHours(12),
        ]);

        $response = $this->authed()->getJson('/api/v1/openclaw/sales/summary');

        $response->assertStatus(200)
            ->assertJsonPath('data.sales.count', 3)
            ->assertJsonPath('data.sales.total', 150)
            ->assertJsonStructure(['data' => ['daily']]);
    }

    public function test_sales_by_item_aggregates_qty_and_revenue_per_item(): void
    {
        $item = Item::factory()->create(['user_id' => $this->owner->user_id]);
        $sale = Sale::factory()->create([
            'user_id' => $this->owner->user_id,
            'store_id' => $this->store->id,
            'sales_by' => $this->owner->id,
            'type' => 0,
            'cancelled' => 0,
            'total' => 250,
            'profit' => 80,
            'created_at' => Carbon::today(config('app.timezone'))->addHours(12),
        ]);
        SaleLine::factory()->create([
            'sales_id' => $sale->id,
            'item_id' => $item->id,
            'qty' => 5,
            'sub_total' => 250,
            'profit' => 80,
        ]);

        $response = $this->authed()->getJson('/api/v1/openclaw/sales/by-item');

        $response->assertStatus(200);
        $this->assertSame(1, count($response->json('data.items')));
        $this->assertSame($item->id, $response->json('data.items.0.item_id'));
        $this->assertEqualsWithDelta(5.0, $response->json('data.items.0.qty'), 0.001);
        $this->assertEqualsWithDelta(250.0, $response->json('data.items.0.revenue'), 0.001);
    }

    public function test_customers_outstanding_credit_returns_only_tenant_customers(): void
    {
        Customer::factory()->create([
            'user_id' => $this->owner->user_id,
            'name' => 'Tenant Customer',
            'credit_balance' => 500,
        ]);
        Customer::factory()->create([
            'user_id' => $this->otherOwner->user_id,
            'name' => 'Other Tenant Customer',
            'credit_balance' => 9999,
        ]);
        Customer::factory()->create([
            'user_id' => $this->owner->user_id,
            'name' => 'Zero Balance',
            'credit_balance' => 0,
        ]);

        $response = $this->authed()->getJson('/api/v1/openclaw/customers/outstanding-credit');

        $response->assertStatus(200)
            ->assertJsonPath('data.summary.active_credit_count', 1);
        $this->assertEqualsWithDelta(500.0, $response->json('data.summary.outstanding_total'), 0.001);
        $this->assertSame(1, count($response->json('data.customers')));
        $this->assertSame('Tenant Customer', $response->json('data.customers.0.name'));
    }
}
