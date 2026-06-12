<?php

namespace Tests\Feature\API\v1\pos;

use App\Models\CustomerRelations\Customer;
use App\Models\Employees\Role;
use App\Models\Pos\Sale;
use App\Models\Products\Category;
use App\Models\Products\Item;
use App\Models\Products\ItemStore;
use App\Models\Settings\Pos;
use App\Models\Settings\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class SaleControllerTest extends TestCase
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
        $this->user = User::factory()->create([
            'role_id' => $role->id,
            'user_id' => 1,
        ]);

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

        $this->category = Category::factory()->create();
    }

    public function test_store_creates_sale_and_returns_correct_structure(): void
    {
        Passport::actingAs($this->user);

        $item = $this->createItemWithStock(['price' => 150, 'cost' => 75]);

        $payload = $this->buildSalePayload(null, [
            ['product' => $item->toArray(), 'qty' => 3, 'price' => 150, 'discount' => 0],
        ]);

        $response = $this->postJson('/api/v1/sales', $payload);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'saleOrder' => [
                        'id',
                        'counter',
                        'son',
                        'total',
                        'cash',
                        'change',
                        'type',
                        'lines',
                        'pos',
                        'store',
                        'sold_by',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('sales', [
            'pos_id' => $this->pos->id,
            'total' => 450,
        ]);
    }

    public function test_store_creates_sale_lines_correctly(): void
    {
        Passport::actingAs($this->user);

        $item1 = $this->createItemWithStock(['price' => 100, 'cost' => 50]);
        $item2 = $this->createItemWithStock(['price' => 200, 'cost' => 80]);

        $payload = $this->buildSalePayload(null, [
            ['product' => $item1->toArray(), 'qty' => 2, 'price' => 100, 'discount' => 0],
            ['product' => $item2->toArray(), 'qty' => 1, 'price' => 200, 'discount' => 10],
        ]);

        $response = $this->postJson('/api/v1/sales', $payload);

        $response->assertStatus(200);

        $sale = Sale::latest()->first();

        $this->assertCount(2, $sale->lines);
        $this->assertEquals($item1->id, $sale->lines[0]->item_id);
        $this->assertEquals(2, $sale->lines[0]->qty);
        $this->assertEquals($item2->id, $sale->lines[1]->item_id);
        $this->assertEquals(10, $sale->lines[1]->discount);
    }

    public function test_store_calculates_customer_points_correctly(): void
    {
        Passport::actingAs($this->user);

        $customer = Customer::factory()->create([
            'points' => 0.02,
            'accumulated_points' => 0,
        ]);

        $item = $this->createItemWithStock([
            'price' => 500,
            'cost' => 250,
            'creditable_to_points' => 1,
        ]);

        $payload = $this->buildSalePayload($customer, [
            ['product' => $item->toArray(), 'qty' => 2, 'price' => 500, 'discount' => 0],
        ]);

        $response = $this->postJson('/api/v1/sales', $payload);

        $response->assertStatus(200);

        $customer->refresh();
        $this->assertEquals(20.00, $customer->accumulated_points);

        $sale = Sale::latest()->first();
        $this->assertEquals(20.00, $sale->acquired_points);
    }

    public function test_store_loads_all_relationships_via_load(): void
    {
        Passport::actingAs($this->user);

        $customer = Customer::factory()->create([
            'points' => 0,
            'accumulated_points' => 0,
        ]);

        $item = $this->createItemWithStock(['price' => 100, 'cost' => 50]);

        $payload = $this->buildSalePayload($customer, [
            ['product' => $item->toArray(), 'qty' => 1, 'price' => 100, 'discount' => 0],
        ]);

        $response = $this->postJson('/api/v1/sales', $payload);

        $response->assertStatus(200);

        $saleOrder = $response->json('data.saleOrder');
        $this->assertArrayHasKey('lines', $saleOrder);
        $this->assertArrayHasKey('pos', $saleOrder);
        $this->assertArrayHasKey('customer', $saleOrder);
        $this->assertArrayHasKey('store', $saleOrder);
        $this->assertArrayHasKey('sold_by', $saleOrder);
        $this->assertArrayHasKey('bank', $saleOrder);
    }

    public function test_store_increments_counter_for_subsequent_sales(): void
    {
        Passport::actingAs($this->user);

        $item = $this->createItemWithStock(['price' => 100, 'cost' => 50]);

        $payload = $this->buildSalePayload(null, [
            ['product' => $item->toArray(), 'qty' => 1, 'price' => 100, 'discount' => 0],
        ]);

        $this->postJson('/api/v1/sales', $payload)->assertStatus(200);
        $firstSale = Sale::orderBy('id', 'desc')->first();

        $this->postJson('/api/v1/sales', $payload)->assertStatus(200);
        $secondSale = Sale::orderBy('id', 'desc')->first();

        $this->assertEquals($firstSale->counter + 1, $secondSale->counter);
    }

    public function test_store_wraps_operations_in_transaction(): void
    {
        Passport::actingAs($this->user);

        $customer = Customer::factory()->create([
            'points' => 0.01,
            'accumulated_points' => 50,
        ]);

        $item = $this->createItemWithStock([
            'price' => 100,
            'cost' => 50,
            'creditable_to_points' => 1,
        ]);

        $payload = $this->buildSalePayload($customer, [
            ['product' => $item->toArray(), 'qty' => 1, 'price' => 100, 'discount' => 0],
        ]);

        $response = $this->postJson('/api/v1/sales', $payload);

        $response->assertStatus(200);

        $sale = Sale::latest()->first();
        $this->assertNotNull($sale);
        $this->assertCount(1, $sale->lines);

        $customer->refresh();
        $this->assertEquals(51.00, $customer->accumulated_points);
    }

    public function test_pos_sale_with_ecommerce_order_advances_to_picked_up(): void
    {
        Passport::actingAs($this->user);

        $customer = \App\Models\CustomerRelations\Customer::factory()->create([
            'user_id' => $this->user->user_id,
        ]);

        $order = \App\Models\Ecommerce\EcommerceOrder::create([
            'reference' => 'ECO-POSTEST1',
            'customer_id' => $customer->id,
            'total' => 100,
            'qty' => 1,
            'status' => \App\Models\Ecommerce\EcommerceOrder::STATUS_VERIFIED,
            'is_wholesale' => false,
        ]);

        $item = $this->createItemWithStock(['price' => 100, 'cost' => 50]);

        $payload = $this->buildSalePayload($customer, [
            ['product' => $item->toArray(), 'qty' => 1, 'price' => 100, 'discount' => 0],
        ]);
        $payload['ecommerce_order_id'] = $order->id;

        $this->postJson('/api/v1/sales', $payload)->assertStatus(200);

        $order->refresh();

        // POS sale: customer is at the counter — paying and collecting
        // happen in one action. Should be PICKED_UP, not just PAID.
        $this->assertSame(
            \App\Models\Ecommerce\EcommerceOrder::STATUS_PICKED_UP,
            (int) $order->status,
        );

        // Both transitions recorded so the timeline shows the full path.
        $this->assertDatabaseHas('ecommerce_order_status_changes', [
            'ecommerce_order_id' => $order->id,
            'from_status' => \App\Models\Ecommerce\EcommerceOrder::STATUS_VERIFIED,
            'to_status' => \App\Models\Ecommerce\EcommerceOrder::STATUS_PAID,
        ]);
        $this->assertDatabaseHas('ecommerce_order_status_changes', [
            'ecommerce_order_id' => $order->id,
            'from_status' => \App\Models\Ecommerce\EcommerceOrder::STATUS_PAID,
            'to_status' => \App\Models\Ecommerce\EcommerceOrder::STATUS_PICKED_UP,
        ]);
    }

    protected function createItemWithStock(array $attributes = []): Item
    {
        $item = Item::factory()->create(array_merge([
            'category_id' => $this->category->id,
        ], $attributes));

        ItemStore::factory()->create([
            'item_id' => $item->id,
            'store_id' => $this->store->id,
            'stock' => 100,
        ]);

        return $item;
    }

    protected function buildSalePayload(?Customer $customer, array $lines, int $pointsUsed = 0): array
    {
        $total = 0;
        $formattedLines = [];

        foreach ($lines as $line) {
            $lineTotal = $line['qty'] * ($line['price'] - ($line['discount'] ?? 0));
            $total += $lineTotal;

            $formattedLines[] = [
                'product' => $line['product'],
                'qty' => $line['qty'],
                'price' => $line['price'],
                'discount' => $line['discount'] ?? 0,
                'unit' => 'PCS',
                'unit_id' => -1,
                'unit_qty' => 1,
                'cost' => $line['product']['cost'],
                'profit' => ($line['price'] - $line['product']['cost']) * $line['qty'],
                'vatable' => 0,
                'vat' => 0,
                'vat_exempt' => $lineTotal,
                'zero_rated' => 0,
                'sc_discount' => 0,
                'pwd_discount' => 0,
                'sp_discount' => 0,
                'naac_discount' => 0,
                'vat_special_discounts' => 0,
            ];
        }

        return [
            'pos_id' => $this->pos->id,
            'type' => false,
            'sale_id' => null,
            'ecommerce_order_id' => null,
            'details' => [
                'total' => $total,
                'cash' => $total + 100,
                'change' => 100,
                'customer_id' => $customer?->id,
                'points' => 0,
                'points_used' => $pointsUsed,
                'payment_type' => 1,
                'reference_number' => '',
                'bank_amount' => 0,
                'bank_id' => null,
                'profit' => 0,
                'vatable' => 0,
                'vat' => 0,
                'vat_exempt' => $total,
                'zero_rated' => 0,
                'sc_discount' => 0,
                'pwd_discount' => 0,
                'sp_discount' => 0,
                'naac_discount' => 0,
                'vat_special_discounts' => 0,
                'special_discount_type' => 0,
                'special_discount_name' => '',
                'special_discount_id' => '',
                'special_discount_tin' => '',
                'voucher_id' => null,
                'voucher_code' => null,
                'voucher_discount' => 0,
            ],
            'line' => $formattedLines,
        ];
    }
}
