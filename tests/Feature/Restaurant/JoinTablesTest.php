<?php

namespace Tests\Feature\Restaurant;

use App\Models\Employees\Role;
use App\Models\Pos\Order;
use App\Models\Pos\Sale;
use App\Models\Products\Category;
use App\Models\Products\Item;
use App\Models\Products\ItemStore;
use App\Models\Restaurant\RestaurantTable;
use App\Models\Settings\Pos;
use App\Models\Settings\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class JoinTablesTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Store $store;

    protected Pos $pos;

    protected Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::factory()->admin()->create();
        $this->user = User::factory()->create(['role_id' => $role->id, 'user_id' => 1]);
        $this->store = Store::factory()->create();
        $this->pos = Pos::create([
            'name' => 'Test POS',
            'store_id' => $this->store->id,
            'status' => true,
            'mac' => '00:00:00:00:00:00',
            'number' => 1,
            'user_id' => $this->user->id,
            'reset_counter' => 1,
        ]);
        $category = Category::factory()->create(['status' => true]);
        $this->item = Item::factory()->create([
            'price' => 100,
            'category_id' => $category->id,
        ]);
        ItemStore::factory()->create([
            'item_id' => $this->item->id,
            'store_id' => $this->store->id,
            'stock' => 100,
        ]);

        Passport::actingAs($this->user);
    }

    private function makeTables(int $count): array
    {
        return RestaurantTable::factory()
            ->count($count)
            ->create(['user_id' => 1, 'store_id' => $this->store->id])
            ->all();
    }

    private function openOrder(array $payload): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/v1/restaurant-orders', array_merge([
            'order_type' => Order::TYPE_DINE_IN,
            'pos_id' => $this->pos->id,
            'lines' => [['item_id' => $this->item->id, 'qty' => 1]],
        ], $payload));
    }

    public function test_opening_with_table_ids_seats_the_whole_party(): void
    {
        [$a, $b, $c] = $this->makeTables(3);

        $response = $this->openOrder(['table_ids' => [$a->id, $b->id, $c->id]])
            ->assertStatus(201);

        // First table becomes the primary; all three occupied + joined.
        $this->assertEquals($a->id, $response->json('data.table_id'));
        $this->assertCount(3, $response->json('data.tables'));
        foreach ([$a, $b, $c] as $table) {
            $this->assertEquals(
                RestaurantTable::STATUS_OCCUPIED,
                $table->fresh()->status,
            );
        }
    }

    public function test_join_tables_on_an_open_order(): void
    {
        [$a, $b] = $this->makeTables(2);
        $orderId = $this->openOrder(['table_id' => $a->id])->json('data.id');

        $this->postJson("/api/v1/restaurant-orders/{$orderId}/join-tables", [
            'table_ids' => [$b->id],
        ])->assertStatus(200);

        $this->assertEquals(RestaurantTable::STATUS_OCCUPIED, $b->fresh()->status);
        $this->assertCount(2, Order::find($orderId)->tables);
    }

    public function test_cannot_join_an_occupied_table(): void
    {
        [$a, $b] = $this->makeTables(2);
        $b->update(['status' => RestaurantTable::STATUS_OCCUPIED]);

        $orderId = $this->openOrder(['table_id' => $a->id])->json('data.id');

        $this->postJson("/api/v1/restaurant-orders/{$orderId}/join-tables", [
            'table_ids' => [$b->id],
        ])->assertStatus(422);

        $this->assertCount(1, Order::find($orderId)->tables);
    }

    public function test_release_returns_a_joined_table_but_never_the_primary(): void
    {
        [$a, $b] = $this->makeTables(2);
        $orderId = $this->openOrder(['table_ids' => [$a->id, $b->id]])
            ->json('data.id');

        $this->postJson("/api/v1/restaurant-orders/{$orderId}/release-table", [
            'table_id' => $b->id,
        ])->assertStatus(200);
        $this->assertEquals(RestaurantTable::STATUS_AVAILABLE, $b->fresh()->status);

        $this->postJson("/api/v1/restaurant-orders/{$orderId}/release-table", [
            'table_id' => $a->id,
        ])->assertStatus(422);
        $this->assertEquals(RestaurantTable::STATUS_OCCUPIED, $a->fresh()->status);
    }

    public function test_settling_frees_every_joined_table(): void
    {
        [$a, $b] = $this->makeTables(2);
        $orderId = $this->openOrder(['table_ids' => [$a->id, $b->id]])
            ->json('data.id');

        $this->postJson("/api/v1/restaurant-orders/{$orderId}/settle", [
            'payment_type' => Sale::PAYMENT_CASH,
            'cash' => 100,
        ])->assertStatus(200);

        $this->assertEquals(RestaurantTable::STATUS_AVAILABLE, $a->fresh()->status);
        $this->assertEquals(RestaurantTable::STATUS_AVAILABLE, $b->fresh()->status);
    }

    public function test_cancel_frees_every_joined_table(): void
    {
        [$a, $b] = $this->makeTables(2);
        $orderId = $this->openOrder(['table_ids' => [$a->id, $b->id]])
            ->json('data.id');

        $this->postJson("/api/v1/restaurant-orders/{$orderId}/cancel")
            ->assertStatus(200);

        $this->assertEquals(RestaurantTable::STATUS_AVAILABLE, $a->fresh()->status);
        $this->assertEquals(RestaurantTable::STATUS_AVAILABLE, $b->fresh()->status);
    }

    public function test_table_map_reports_the_order_on_every_joined_table(): void
    {
        [$a, $b] = $this->makeTables(2);
        $this->openOrder(['table_ids' => [$a->id, $b->id]]);

        $tables = collect(
            $this->getJson('/api/v1/tables')->assertStatus(200)->json('data'),
        );

        $tableA = $tables->firstWhere('id', $a->id);
        $tableB = $tables->firstWhere('id', $b->id);
        $this->assertNotNull($tableA['open_order']);
        $this->assertNotNull($tableB['open_order']);
        $this->assertEquals(
            $tableA['open_order']['id'],
            $tableB['open_order']['id'],
        );
        $this->assertTrue($tableA['open_order']['is_primary_table']);
        $this->assertFalse($tableB['open_order']['is_primary_table']);
    }

    public function test_transfer_swaps_the_primary_and_keeps_joined_tables(): void
    {
        [$a, $b, $c] = $this->makeTables(3);
        $orderId = $this->openOrder(['table_ids' => [$a->id, $b->id]])
            ->json('data.id');

        $this->postJson("/api/v1/restaurant-orders/{$orderId}/transfer-table", [
            'table_id' => $c->id,
        ])->assertStatus(200);

        $order = Order::find($orderId);
        $this->assertEquals($c->id, $order->table_id);
        $this->assertEqualsCanonicalizing(
            [$b->id, $c->id],
            $order->tables->pluck('id')->all(),
        );
        $this->assertEquals(RestaurantTable::STATUS_AVAILABLE, $a->fresh()->status);
        $this->assertEquals(RestaurantTable::STATUS_OCCUPIED, $b->fresh()->status);
        $this->assertEquals(RestaurantTable::STATUS_OCCUPIED, $c->fresh()->status);
    }
}
