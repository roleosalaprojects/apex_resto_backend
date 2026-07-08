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

class SeatBillTest extends TestCase
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
     * Open a dine-in order with line A (@100) on seat 1 and line B (@50)
     * on seat 2.
     *
     * @return array{0: int, 1: RestaurantTable, 2: int, 3: int} [orderId, table, lineAId, lineBId]
     */
    private function openSeatedOrder(): array
    {
        $table = RestaurantTable::factory()->create(['user_id' => 1, 'store_id' => $this->store->id]);
        $itemA = Item::factory()->create(['price' => 100, 'category_id' => $this->category->id]);
        $itemB = Item::factory()->create(['price' => 50, 'category_id' => $this->category->id]);
        ItemStore::factory()->create(['item_id' => $itemA->id, 'store_id' => $this->store->id, 'stock' => 100]);
        ItemStore::factory()->create(['item_id' => $itemB->id, 'store_id' => $this->store->id, 'stock' => 100]);

        $create = $this->postJson('/api/v1/restaurant-orders', [
            'order_type' => Order::TYPE_DINE_IN,
            'table_id' => $table->id,
            'pax' => 2,
            'pos_id' => $this->pos->id,
            'lines' => [
                ['item_id' => $itemA->id, 'qty' => 1, 'seat' => 1],
                ['item_id' => $itemB->id, 'qty' => 1, 'seat' => 2],
            ],
        ])->assertStatus(201);

        return [
            $create->json('data.id'),
            $table,
            $create->json('data.lines.0.id'),
            $create->json('data.lines.1.id'),
        ];
    }

    public function test_lines_can_be_seated_on_create(): void
    {
        [, , $lineAId, $lineBId] = $this->openSeatedOrder();

        $this->assertEquals(1, OrderLine::find($lineAId)->seat);
        $this->assertEquals(2, OrderLine::find($lineBId)->seat);
    }

    public function test_settle_seat_bills_only_that_seats_lines_and_leaves_order_open(): void
    {
        [$orderId, $table, $lineAId, $lineBId] = $this->openSeatedOrder();

        $this->postJson("/api/v1/restaurant-orders/{$orderId}/settle-seat", [
            'seats' => [1],
            'payment_type' => Sale::PAYMENT_CASH,
            'cash' => 100,
        ])->assertStatus(200)
            ->assertJsonPath('data.total', 100)
            ->assertJsonPath('data.fully_settled', false);

        $this->assertEquals(1, Sale::count());
        $this->assertNotNull(OrderLine::find($lineAId)->sales_id);
        $this->assertNull(OrderLine::find($lineBId)->sales_id);

        $order = Order::find($orderId);
        $this->assertNull($order->sales_id);
        $this->assertEquals(Order::STATUS_PREPARING, $order->status);
        $this->assertEquals(RestaurantTable::STATUS_OCCUPIED, $table->fresh()->status);
    }

    public function test_settling_all_seats_completes_order_and_frees_table(): void
    {
        [$orderId, $table, $lineAId, $lineBId] = $this->openSeatedOrder();

        $this->postJson("/api/v1/restaurant-orders/{$orderId}/settle-seat", [
            'seats' => [1],
            'payment_type' => Sale::PAYMENT_CASH,
            'cash' => 100,
        ])->assertStatus(200);

        $this->postJson("/api/v1/restaurant-orders/{$orderId}/settle-seat", [
            'seats' => [2],
            'payment_type' => Sale::PAYMENT_CASH,
            'cash' => 50,
        ])->assertStatus(200)
            ->assertJsonPath('data.total', 50)
            ->assertJsonPath('data.fully_settled', true);

        $this->assertEquals(2, Sale::count());
        $this->assertNotEquals(
            OrderLine::find($lineAId)->sales_id,
            OrderLine::find($lineBId)->sales_id,
        );

        $order = Order::find($orderId);
        $this->assertEquals(Order::STATUS_COMPLETED, $order->status);
        $this->assertEquals(RestaurantTable::STATUS_AVAILABLE, $table->fresh()->status);
    }

    public function test_assign_seat_reassigns_line_before_settlement(): void
    {
        [$orderId, , $lineAId, $lineBId] = $this->openSeatedOrder();

        // Move line B from seat 2 to seat 1, then settle seat 1 — both lines bill.
        $this->postJson("/api/v1/restaurant-orders/{$orderId}/lines/{$lineBId}/assign-seat", [
            'seat' => 1,
        ])->assertStatus(200)->assertJsonPath('data.seat', 1);

        $this->postJson("/api/v1/restaurant-orders/{$orderId}/settle-seat", [
            'seats' => [1],
            'payment_type' => Sale::PAYMENT_CASH,
            'cash' => 150,
        ])->assertStatus(200)
            ->assertJsonPath('data.total', 150)
            ->assertJsonPath('data.fully_settled', true);

        $saleId = Sale::value('id');
        $this->assertEquals($saleId, OrderLine::find($lineAId)->sales_id);
        $this->assertEquals($saleId, OrderLine::find($lineBId)->sales_id);
    }

    public function test_settle_seat_rejects_seat_with_no_unsettled_lines(): void
    {
        [$orderId] = $this->openSeatedOrder();

        $this->postJson("/api/v1/restaurant-orders/{$orderId}/settle-seat", [
            'seats' => [1],
            'payment_type' => Sale::PAYMENT_CASH,
            'cash' => 100,
        ])->assertStatus(200);

        // Re-billing seat 1 (now fully settled) must fail.
        $this->postJson("/api/v1/restaurant-orders/{$orderId}/settle-seat", [
            'seats' => [1],
            'payment_type' => Sale::PAYMENT_CASH,
            'cash' => 100,
        ])->assertStatus(422);

        $this->assertEquals(1, Sale::count());
    }

    public function test_cannot_reassign_seat_of_settled_line(): void
    {
        [$orderId, , $lineAId] = $this->openSeatedOrder();

        $this->postJson("/api/v1/restaurant-orders/{$orderId}/settle-seat", [
            'seats' => [1],
            'payment_type' => Sale::PAYMENT_CASH,
            'cash' => 100,
        ])->assertStatus(200);

        $this->postJson("/api/v1/restaurant-orders/{$orderId}/lines/{$lineAId}/assign-seat", [
            'seat' => 3,
        ])->assertStatus(422);

        $this->assertEquals(1, OrderLine::find($lineAId)->seat);
    }
}
