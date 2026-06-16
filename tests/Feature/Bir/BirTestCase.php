<?php

namespace Tests\Feature\Bir;

use App\Models\Employees\Role;
use App\Models\Products\Category;
use App\Models\Products\Item;
use App\Models\Products\ItemStore;
use App\Models\Settings\Pos;
use App\Models\Settings\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Shared scaffolding for BIR feature tests: a tenant admin, a store, a POS
 * terminal, a category, plus a POS sale payload builder.
 */
abstract class BirTestCase extends TestCase
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
    }

    protected function createItemWithStock(array $attributes = []): Item
    {
        $item = Item::factory()->create(array_merge([
            'category_id' => $this->category->id,
        ], $attributes));

        ItemStore::factory()->create([
            'item_id' => $item->id,
            'store_id' => $this->store->id,
            'stock' => 1000,
        ]);

        return $item;
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines  [{item, qty, price, discount?}]
     * @return array<string, mixed>
     */
    protected function buildSalePayload(array $lines, int $paymentType = 1): array
    {
        $total = 0;
        $formattedLines = [];

        foreach ($lines as $line) {
            $lineTotal = $line['qty'] * ($line['price'] - ($line['discount'] ?? 0));
            $total += $lineTotal;

            $formattedLines[] = [
                'product' => $line['item']->toArray(),
                'qty' => $line['qty'],
                'price' => $line['price'],
                'discount' => $line['discount'] ?? 0,
                'unit' => 'PCS',
                'unit_id' => -1,
                'unit_qty' => 1,
                'cost' => $line['item']->cost,
                'profit' => ($line['price'] - $line['item']->cost) * $line['qty'],
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
                'customer_id' => null,
                'points' => 0,
                'points_used' => 0,
                'payment_type' => $paymentType,
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
