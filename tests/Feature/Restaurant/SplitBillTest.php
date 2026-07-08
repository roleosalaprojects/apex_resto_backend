<?php

namespace Tests\Feature\Restaurant;

use App\Models\Employees\Role;
use App\Models\Pos\Order;
use App\Models\Pos\OrderLine;
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

class SplitBillTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Store $store;

    protected Pos $pos;

    protected Category $category;

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
        $this->category = Category::factory()->create(['status' => true]);

        Passport::actingAs($this->user);
    }

    /**
     * Open a two-line dine-in order (item A @100, item B @50) on a table.
     *
     * @return array{0: int, 1: RestaurantTable, 2: int, 3: int} [orderId, table, lineAId, lineBId]
     */
    private function openTwoLineOrder(): array
    {
        $table = RestaurantTable::factory()->create(['user_id' => 1, 'store_id' => $this->store->id]);
        $itemA = Item::factory()->create(['price' => 100, 'category_id' => $this->category->id]);
        $itemB = Item::factory()->create(['price' => 50, 'category_id' => $this->category->id]);
        ItemStore::factory()->create(['item_id' => $itemA->id, 'store_id' => $this->store->id, 'stock' => 100]);
        ItemStore::factory()->create(['item_id' => $itemB->id, 'store_id' => $this->store->id, 'stock' => 100]);

        $create = $this->postJson('/api/v1/restaurant-orders', [
            'order_type' => Order::TYPE_DINE_IN,
            'table_id' => $table->id,
            'pos_id' => $this->pos->id,
            'lines' => [
                ['item_id' => $itemA->id, 'qty' => 1],
                ['item_id' => $itemB->id, 'qty' => 1],
            ],
        ])->assertStatus(201);

        return [
            $create->json('data.id'),
            $table,
            $create->json('data.lines.0.id'),
            $create->json('data.lines.1.id'),
        ];
    }

    public function test_split_settle_bills_subset_and_leaves_order_open(): void
    {
        [$orderId, $table, $lineAId, $lineBId] = $this->openTwoLineOrder();

        $response = $this->postJson("/api/v1/restaurant-orders/{$orderId}/split-settle", [
            'line_ids' => [$lineAId],
            'payment_type' => Sale::PAYMENT_CASH,
            'cash' => 100,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.total', 100)
            ->assertJsonPath('data.fully_settled', false);

        // One receipt covering only line A.
        $this->assertEquals(1, Sale::count());
        $saleId = $response->json('data.sale_id');
        $this->assertEquals($saleId, OrderLine::find($lineAId)->sales_id);
        $this->assertNull(OrderLine::find($lineBId)->sales_id);

        // Order stays open; table stays occupied.
        $order = Order::find($orderId);
        $this->assertNull($order->sales_id);
        $this->assertEquals(Order::STATUS_PREPARING, $order->status);
        $this->assertEquals(RestaurantTable::STATUS_OCCUPIED, $table->fresh()->status);
    }

    public function test_settling_remaining_lines_completes_order_and_frees_table(): void
    {
        [$orderId, $table, $lineAId, $lineBId] = $this->openTwoLineOrder();

        $this->postJson("/api/v1/restaurant-orders/{$orderId}/split-settle", [
            'line_ids' => [$lineAId],
            'payment_type' => Sale::PAYMENT_CASH,
            'cash' => 100,
        ])->assertStatus(200);

        // Pay the rest with the full-settle endpoint — it bills whatever remains.
        $this->postJson("/api/v1/restaurant-orders/{$orderId}/settle", [
            'payment_type' => Sale::PAYMENT_CASH,
            'cash' => 50,
        ])->assertStatus(200)->assertJsonPath('data.total', 50);

        // Two distinct receipts; every line linked to one of them.
        $this->assertEquals(2, Sale::count());
        $this->assertNotNull(OrderLine::find($lineAId)->sales_id);
        $this->assertNotNull(OrderLine::find($lineBId)->sales_id);
        $this->assertNotEquals(
            OrderLine::find($lineAId)->sales_id,
            OrderLine::find($lineBId)->sales_id,
        );

        $order = Order::find($orderId);
        $this->assertEquals(Order::STATUS_COMPLETED, $order->status);
        $this->assertNotNull($order->sales_id);
        $this->assertEquals(RestaurantTable::STATUS_AVAILABLE, $table->fresh()->status);
    }

    public function test_split_settle_can_pay_every_line_at_once_and_completes_order(): void
    {
        [$orderId, $table, $lineAId, $lineBId] = $this->openTwoLineOrder();

        $this->postJson("/api/v1/restaurant-orders/{$orderId}/split-settle", [
            'line_ids' => [$lineAId, $lineBId],
            'payment_type' => Sale::PAYMENT_CASH,
            'cash' => 150,
        ])->assertStatus(200)
            ->assertJsonPath('data.total', 150)
            ->assertJsonPath('data.fully_settled', true);

        $order = Order::find($orderId);
        $this->assertEquals(Order::STATUS_COMPLETED, $order->status);
        $this->assertEquals(RestaurantTable::STATUS_AVAILABLE, $table->fresh()->status);
    }

    public function test_split_settle_rejects_an_already_settled_line(): void
    {
        [$orderId, , $lineAId] = $this->openTwoLineOrder();

        $this->postJson("/api/v1/restaurant-orders/{$orderId}/split-settle", [
            'line_ids' => [$lineAId],
            'payment_type' => Sale::PAYMENT_CASH,
            'cash' => 100,
        ])->assertStatus(200);

        // Re-billing the same line must fail without creating a second sale.
        $this->postJson("/api/v1/restaurant-orders/{$orderId}/split-settle", [
            'line_ids' => [$lineAId],
            'payment_type' => Sale::PAYMENT_CASH,
            'cash' => 100,
        ])->assertStatus(422);

        $this->assertEquals(1, Sale::count());
    }

    public function test_split_settle_rejects_a_voided_line(): void
    {
        [$orderId, , $lineAId, $lineBId] = $this->openTwoLineOrder();

        $this->postJson("/api/v1/restaurant-orders/{$orderId}/lines/{$lineBId}/void", [
            'reason' => 'Customer changed mind',
        ])->assertStatus(200);

        $this->postJson("/api/v1/restaurant-orders/{$orderId}/split-settle", [
            'line_ids' => [$lineBId],
            'payment_type' => Sale::PAYMENT_CASH,
            'cash' => 50,
        ])->assertStatus(422);

        $this->assertEquals(0, Sale::count());
    }
}
