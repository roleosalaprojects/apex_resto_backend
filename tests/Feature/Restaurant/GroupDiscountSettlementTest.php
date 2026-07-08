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

class GroupDiscountSettlementTest extends TestCase
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
     * Open a two-line dine-in order of VATable items (item A @100 on seat 1,
     * item B @50 on seat 2) — 150 gross, VAT-inclusive.
     *
     * @param  array<string, mixed>  $orderAttributes  extra order header fields (pax, sc_count, ...)
     * @return array{0: int, 1: int, 2: int} [orderId, lineAId, lineBId]
     */
    private function openTwoLineOrder(array $orderAttributes = []): array
    {
        $table = RestaurantTable::factory()->create(['user_id' => 1, 'store_id' => $this->store->id]);
        $itemA = Item::factory()->create(['price' => 100, 'vatable' => 1, 'category_id' => $this->category->id]);
        $itemB = Item::factory()->create(['price' => 50, 'vatable' => 1, 'category_id' => $this->category->id]);
        ItemStore::factory()->create(['item_id' => $itemA->id, 'store_id' => $this->store->id, 'stock' => 100]);
        ItemStore::factory()->create(['item_id' => $itemB->id, 'store_id' => $this->store->id, 'stock' => 100]);

        $create = $this->postJson('/api/v1/restaurant-orders', array_merge([
            'order_type' => Order::TYPE_DINE_IN,
            'table_id' => $table->id,
            'pos_id' => $this->pos->id,
            'lines' => [
                ['item_id' => $itemA->id, 'qty' => 1, 'seat' => 1],
                ['item_id' => $itemB->id, 'qty' => 1, 'seat' => 2],
            ],
        ], $orderAttributes))->assertStatus(201);

        return [
            $create->json('data.id'),
            $create->json('data.lines.0.id'),
            $create->json('data.lines.1.id'),
        ];
    }

    public function test_declaring_beneficiaries_computes_the_group_discount(): void
    {
        [$orderId] = $this->openTwoLineOrder();

        // 1 senior of 3 diners on a 150 VAT-inclusive bill (RMC 38-2012):
        // share 50 → VAT-exempt 44.64, VAT removed 5.36, discount 8.93.
        $this->postJson("/api/v1/restaurant-orders/{$orderId}/settle", [
            'payment_type' => Sale::PAYMENT_CASH,
            'cash' => 140,
            'pax' => 3,
            'sc_count' => 1,
            'special_discount_name' => 'Juan Dela Cruz',
            'special_discount_id' => 'OSCA-1234',
        ])->assertStatus(200)->assertJsonPath('data.total', 135.71);

        $sale = Sale::firstOrFail();
        $this->assertEquals(135.71, (float) $sale->total);
        $this->assertEquals(8.93, (float) $sale->sc_discount);
        $this->assertEquals(0.0, (float) $sale->pwd_discount);
        $this->assertEquals(5.36, (float) $sale->vat_special_discounts);
        $this->assertEquals(1, (int) $sale->special_discount_type);
        $this->assertEquals('Juan Dela Cruz', $sale->special_discount_name);
        $this->assertEquals('OSCA-1234', $sale->special_discount_id);
        $this->assertEquals(3, (int) $sale->pax);
        $this->assertEquals(1, (int) $sale->sc_count);

        // Beneficiary share leaves the VAT base and lands in vat_exempt.
        $this->assertEquals(89.29, (float) $sale->vatable);
        $this->assertEquals(10.71, (float) $sale->vat);
        $this->assertEquals(44.64, (float) $sale->vat_exempt);

        // Change is computed against the discounted amount due.
        $this->assertEquals(4.29, (float) $sale->change);
    }

    public function test_per_seat_settlement_discounts_the_seniors_own_receipt_exactly(): void
    {
        [$orderId] = $this->openTwoLineOrder();

        // The senior on seat 1 pays only their own 100 line: pax 1, sc 1 →
        // the whole receipt is their share (VAT-exempt + 20% off).
        $this->postJson("/api/v1/restaurant-orders/{$orderId}/settle-seat", [
            'seats' => [1],
            'payment_type' => Sale::PAYMENT_CASH,
            'cash' => 71.43,
            'pax' => 1,
            'sc_count' => 1,
        ])->assertStatus(200)->assertJsonPath('data.total', 71.43);

        $sale = Sale::firstOrFail();
        $this->assertEquals(17.86, (float) $sale->sc_discount);
        $this->assertEquals(10.71, (float) $sale->vat_special_discounts);
        $this->assertEquals(0.0, (float) $sale->vatable);
        $this->assertEquals(0.0, (float) $sale->vat);
        $this->assertEquals(89.29, (float) $sale->vat_exempt);
        $this->assertEquals(1, (int) $sale->pax);
        $this->assertEquals(1, (int) $sale->sc_count);
    }

    public function test_no_discount_applies_without_a_payload_declaration(): void
    {
        [$orderId] = $this->openTwoLineOrder(['pax' => 4, 'sc_count' => 2]);

        $this->postJson("/api/v1/restaurant-orders/{$orderId}/settle", [
            'payment_type' => Sale::PAYMENT_CASH,
            'cash' => 150,
        ])->assertStatus(200)->assertJsonPath('data.total', 150);

        // Whole-order receipt still inherits the header counts for
        // reporting, but no discount is computed from them.
        $sale = Sale::firstOrFail();
        $this->assertEquals(4, (int) $sale->pax);
        $this->assertEquals(2, (int) $sale->sc_count);
        $this->assertEquals(0.0, (float) $sale->sc_discount);
        $this->assertEquals(0, (int) $sale->special_discount_type);
    }

    public function test_partial_receipts_do_not_copy_the_order_headcounts(): void
    {
        [$orderId, $lineAId] = $this->openTwoLineOrder(['pax' => 4, 'sc_count' => 2]);

        $this->postJson("/api/v1/restaurant-orders/{$orderId}/split-settle", [
            'line_ids' => [$lineAId],
            'payment_type' => Sale::PAYMENT_CASH,
            'cash' => 100,
        ])->assertStatus(200);

        $splitSale = Sale::firstOrFail();
        $this->assertNull($splitSale->pax);
        $this->assertNull($splitSale->sc_count);
        $this->assertNull($splitSale->pwd_count);

        // Settling the remainder is also a partial receipt — prior lines
        // were billed elsewhere, so the header counts stay off it too.
        $this->postJson("/api/v1/restaurant-orders/{$orderId}/settle", [
            'payment_type' => Sale::PAYMENT_CASH,
            'cash' => 50,
        ])->assertStatus(200);

        $remainderSale = Sale::orderByDesc('id')->firstOrFail();
        $this->assertNull($remainderSale->pax);
        $this->assertNull($remainderSale->sc_count);
    }

    public function test_multi_tender_validates_against_the_discounted_amount_due(): void
    {
        [$orderId] = $this->openTwoLineOrder();

        $this->postJson("/api/v1/restaurant-orders/{$orderId}/settle", [
            'pax' => 3,
            'sc_count' => 1,
            'payments' => [
                ['payment_type' => Sale::PAYMENT_CASH, 'amount' => 100],
                ['payment_type' => Sale::PAYMENT_CARD, 'amount' => 35.71],
            ],
        ])->assertStatus(200)->assertJsonPath('data.total', 135.71);

        $sale = Sale::firstOrFail();
        $this->assertEquals(Sale::PAYMENT_MULTI, (int) $sale->payment_type);
        $this->assertEquals(135.71, (float) $sale->payments()->sum('amount'));
        $this->assertEquals(8.93, (float) $sale->sc_discount);
        $this->assertEquals(0.0, (float) $sale->change);
    }

    public function test_mixed_sc_and_pwd_split_the_discount_by_headcount(): void
    {
        [$orderId] = $this->openTwoLineOrder();

        // 1 senior + 1 PWD of 4 diners on 150: share 75 → discount 13.39.
        $this->postJson("/api/v1/restaurant-orders/{$orderId}/settle", [
            'payment_type' => Sale::PAYMENT_CASH,
            'cash' => 130,
            'pax' => 4,
            'sc_count' => 1,
            'pwd_count' => 1,
        ])->assertStatus(200)->assertJsonPath('data.total', 128.57);

        $sale = Sale::firstOrFail();
        $this->assertEquals(6.70, (float) $sale->sc_discount);
        $this->assertEquals(6.69, (float) $sale->pwd_discount);
        $this->assertEquals(8.04, (float) $sale->vat_special_discounts);
        $this->assertEquals(1, (int) $sale->special_discount_type);
    }

    public function test_beneficiaries_cannot_exceed_pax(): void
    {
        [$orderId] = $this->openTwoLineOrder();

        $this->postJson("/api/v1/restaurant-orders/{$orderId}/settle", [
            'payment_type' => Sale::PAYMENT_CASH,
            'pax' => 2,
            'sc_count' => 2,
            'pwd_count' => 1,
        ])->assertStatus(422);

        $this->assertEquals(0, Sale::count());
    }

    public function test_beneficiaries_require_pax(): void
    {
        [$orderId] = $this->openTwoLineOrder();

        $this->postJson("/api/v1/restaurant-orders/{$orderId}/settle", [
            'payment_type' => Sale::PAYMENT_CASH,
            'sc_count' => 1,
        ])->assertStatus(422);

        $this->assertEquals(0, Sale::count());
    }

    public function test_x_reading_reports_the_group_discount_buckets(): void
    {
        [$orderId] = $this->openTwoLineOrder();

        $this->postJson("/api/v1/restaurant-orders/{$orderId}/settle", [
            'payment_type' => Sale::PAYMENT_CASH,
            'cash' => 140,
            'pax' => 3,
            'sc_count' => 1,
        ])->assertStatus(200);

        $reading = $this->getJson('/api/v1/xreadings/apex/generate/'.$this->pos->id)
            ->assertStatus(200)
            ->json('data.reading.0');

        $this->assertEquals(8.93, (float) $reading['sc_discount']);
        $this->assertEquals(5.36, (float) $reading['vat_special_discounts']);
        $this->assertEquals(5.36, (float) $reading['sc_vat_adjustment']);
        $this->assertEquals(1, (int) $reading['sc_transactions']);
        $this->assertEquals(135.71, (float) $reading['net_sales']);
    }
}
