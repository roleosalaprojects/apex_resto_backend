<?php

namespace Tests\Feature\API\v1\pos;

use App\Models\CustomerRelations\Customer;
use App\Models\Employees\Role;
use App\Models\Products\Category;
use App\Models\Products\Item;
use App\Models\Products\ItemStore;
use App\Models\Settings\Pos;
use App\Models\Settings\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class SalePointsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Role $role;

    protected Store $store;

    protected Pos $pos;

    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->role = Role::factory()->admin()->create();
        $this->user = User::factory()->create([
            'role_id' => $this->role->id,
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

    public function test_sale_calculates_points_for_creditable_items(): void
    {
        Passport::actingAs($this->user);

        $customer = Customer::factory()->create([
            'points' => 0.01,
            'accumulated_points' => 0,
        ]);

        $creditableItem = $this->createItemWithStock([
            'price' => 100,
            'cost' => 50,
            'creditable_to_points' => 1,
        ]);

        $saleData = $this->buildSalePayload($customer, [
            [
                'product' => $creditableItem->toArray(),
                'qty' => 2,
                'price' => 100,
                'discount' => 0,
            ],
        ]);

        $response = $this->postJson('/api/v1/sales', $saleData);

        $response->assertStatus(200);

        $customer->refresh();
        $this->assertEquals(2.00, $customer->accumulated_points);
    }

    public function test_sale_does_not_add_points_for_non_creditable_items(): void
    {
        Passport::actingAs($this->user);

        $customer = Customer::factory()->create([
            'points' => 0.01,
            'accumulated_points' => 0,
        ]);

        $nonCreditableItem = $this->createItemWithStock([
            'price' => 100,
            'cost' => 50,
            'creditable_to_points' => 0,
        ]);

        $saleData = $this->buildSalePayload($customer, [
            [
                'product' => $nonCreditableItem->toArray(),
                'qty' => 2,
                'price' => 100,
                'discount' => 0,
            ],
        ]);

        $response = $this->postJson('/api/v1/sales', $saleData);

        $response->assertStatus(200);

        $customer->refresh();
        $this->assertEquals(0, $customer->accumulated_points);
    }

    public function test_sale_calculates_points_with_mixed_items(): void
    {
        Passport::actingAs($this->user);

        $customer = Customer::factory()->create([
            'points' => 0.05,
            'accumulated_points' => 10,
        ]);

        $creditableItem = $this->createItemWithStock([
            'price' => 200,
            'cost' => 100,
            'creditable_to_points' => 1,
        ]);

        $nonCreditableItem = $this->createItemWithStock([
            'price' => 300,
            'cost' => 150,
            'creditable_to_points' => 0,
        ]);

        $saleData = $this->buildSalePayload($customer, [
            [
                'product' => $creditableItem->toArray(),
                'qty' => 1,
                'price' => 200,
                'discount' => 0,
            ],
            [
                'product' => $nonCreditableItem->toArray(),
                'qty' => 1,
                'price' => 300,
                'discount' => 0,
            ],
        ]);

        $response = $this->postJson('/api/v1/sales', $saleData);

        $response->assertStatus(200);

        $customer->refresh();
        $this->assertEquals(20.00, $customer->accumulated_points);
    }

    public function test_sale_deducts_points_when_using_points(): void
    {
        Passport::actingAs($this->user);

        $customer = Customer::factory()->create([
            'points' => 0.01,
            'accumulated_points' => 100,
        ]);

        $item = $this->createItemWithStock([
            'price' => 100,
            'cost' => 50,
            'creditable_to_points' => 1,
        ]);

        $saleData = $this->buildSalePayload($customer, [
            [
                'product' => $item->toArray(),
                'qty' => 1,
                'price' => 100,
                'discount' => 0,
            ],
        ], pointsUsed: 50);

        $response = $this->postJson('/api/v1/sales', $saleData);

        $response->assertStatus(200);

        $customer->refresh();
        $this->assertEquals(50, $customer->accumulated_points);
    }

    public function test_sale_without_customer_does_not_affect_points(): void
    {
        Passport::actingAs($this->user);

        $item = $this->createItemWithStock([
            'price' => 100,
            'cost' => 50,
            'creditable_to_points' => 1,
        ]);

        $saleData = $this->buildSalePayload(null, [
            [
                'product' => $item->toArray(),
                'qty' => 1,
                'price' => 100,
                'discount' => 0,
            ],
        ]);

        $response = $this->postJson('/api/v1/sales', $saleData);

        $response->assertStatus(200);
    }

    public function test_sale_applies_discount_before_points_calculation(): void
    {
        Passport::actingAs($this->user);

        $customer = Customer::factory()->create([
            'points' => 0.1,
            'accumulated_points' => 0,
        ]);

        $item = $this->createItemWithStock([
            'price' => 100,
            'cost' => 50,
            'creditable_to_points' => 1,
        ]);

        $saleData = $this->buildSalePayload($customer, [
            [
                'product' => $item->toArray(),
                'qty' => 1,
                'price' => 100,
                'discount' => 20,
            ],
        ]);

        $response = $this->postJson('/api/v1/sales', $saleData);

        $response->assertStatus(200);

        $customer->refresh();
        $this->assertEquals(8.00, $customer->accumulated_points);
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
