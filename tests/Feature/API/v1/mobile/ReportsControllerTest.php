<?php

namespace Tests\Feature\API\v1\mobile;

use App\Models\Employees\Role;
use App\Models\InventoryManagement\Purchase;
use App\Models\InventoryManagement\Supplier;
use App\Models\Pos\Sale;
use App\Models\Pos\SaleLine;
use App\Models\Products\Category;
use App\Models\Products\Item;
use App\Models\Products\ItemStore;
use App\Models\Settings\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class ReportsControllerTest extends TestCase
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

    public function test_can_get_inventory_report(): void
    {
        Passport::actingAs($this->user);

        $category = Category::factory()->create(['user_id' => $this->user->user_id]);
        $item = Item::factory()->create([
            'user_id' => $this->user->user_id,
            'category_id' => $category->id,
            'status' => true,
            'cost' => 100,
        ]);
        ItemStore::factory()->create([
            'item_id' => $item->id,
            'store_id' => $this->store->id,
            'stock' => 50,
        ]);

        $response = $this->getJson('/api/v1/mobile/reports/inventory');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'items' => [
                    '*' => [
                        'id',
                        'name',
                        'barcode',
                        'current_stock',
                        'reorder_point',
                        'unit_cost',
                        'stock_value',
                        'category',
                    ],
                ],
                'summary' => [
                    'total_products',
                    'total_stock_value',
                    'low_stock_count',
                    'out_of_stock_count',
                ],
            ],
        ]);
    }

    public function test_inventory_report_filters_by_store(): void
    {
        Passport::actingAs($this->user);

        $item = Item::factory()->create([
            'user_id' => $this->user->user_id,
            'status' => true,
        ]);
        ItemStore::factory()->create([
            'item_id' => $item->id,
            'store_id' => $this->store->id,
            'stock' => 50,
        ]);

        $response = $this->getJson("/api/v1/mobile/reports/inventory?store_id={$this->store->id}");

        $response->assertStatus(200);
        $this->assertNotEmpty($response->json('data.items'));
    }

    public function test_inventory_report_filters_by_category(): void
    {
        Passport::actingAs($this->user);

        $category = Category::factory()->create(['user_id' => $this->user->user_id]);
        $item = Item::factory()->create([
            'user_id' => $this->user->user_id,
            'category_id' => $category->id,
            'status' => true,
        ]);
        ItemStore::factory()->create([
            'item_id' => $item->id,
            'store_id' => $this->store->id,
            'stock' => 50,
        ]);

        $response = $this->getJson("/api/v1/mobile/reports/inventory?category_id={$category->id}");

        $response->assertStatus(200);
    }

    public function test_inventory_report_filters_low_stock(): void
    {
        Passport::actingAs($this->user);

        $item = Item::factory()->create([
            'user_id' => $this->user->user_id,
            'status' => true,
        ]);
        ItemStore::factory()->create([
            'item_id' => $item->id,
            'store_id' => $this->store->id,
            'stock' => 5,
        ]);

        $response = $this->getJson('/api/v1/mobile/reports/inventory?low_stock=true');

        $response->assertStatus(200);
        $items = $response->json('data.items');
        foreach ($items as $responseItem) {
            $this->assertLessThanOrEqual(10, $responseItem['current_stock']);
            $this->assertGreaterThan(0, $responseItem['current_stock']);
        }
    }

    public function test_can_get_low_stock_alerts(): void
    {
        Passport::actingAs($this->user);

        $item = Item::factory()->create([
            'user_id' => $this->user->user_id,
            'status' => true,
        ]);
        ItemStore::factory()->create([
            'item_id' => $item->id,
            'store_id' => $this->store->id,
            'stock' => 5,
        ]);

        $response = $this->getJson('/api/v1/mobile/reports/inventory/low-stock');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'items' => [
                    '*' => [
                        'id',
                        'name',
                        'current_stock',
                        'reorder_point',
                        'suggested_order_quantity',
                    ],
                ],
            ],
        ]);
    }

    public function test_low_stock_respects_limit(): void
    {
        Passport::actingAs($this->user);

        for ($i = 0; $i < 5; $i++) {
            $item = Item::factory()->create([
                'user_id' => $this->user->user_id,
                'status' => true,
            ]);
            ItemStore::factory()->create([
                'item_id' => $item->id,
                'store_id' => $this->store->id,
                'stock' => 5,
            ]);
        }

        $response = $this->getJson('/api/v1/mobile/reports/inventory/low-stock?limit=2');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data.items'));
    }

    public function test_can_get_supplier_report(): void
    {
        Passport::actingAs($this->user);

        $supplier = Supplier::factory()->create([
            'user_id' => $this->user->user_id,
            'status' => true,
        ]);
        Purchase::factory()->create([
            'user_id' => $this->user->user_id,
            'supplier_id' => $supplier->id,
            'store_id' => $this->store->id,
            'total' => 1000,
        ]);

        $response = $this->getJson('/api/v1/mobile/reports/suppliers');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'suppliers' => [
                    '*' => [
                        'id',
                        'name',
                        'total_purchase_value',
                        'order_count',
                        'average_order_value',
                        'last_order_date',
                    ],
                ],
                'summary' => [
                    'total_suppliers',
                    'total_purchase_value',
                ],
            ],
        ]);
    }

    public function test_supplier_report_filters_by_date_range(): void
    {
        Passport::actingAs($this->user);

        $supplier = Supplier::factory()->create([
            'user_id' => $this->user->user_id,
            'status' => true,
        ]);
        Purchase::factory()->create([
            'user_id' => $this->user->user_id,
            'supplier_id' => $supplier->id,
            'store_id' => $this->store->id,
            'total' => 1000,
            'created_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/mobile/reports/suppliers?start_date='.now()->startOfMonth()->toDateString().'&end_date='.now()->endOfMonth()->toDateString());

        $response->assertStatus(200);
    }

    public function test_can_get_category_report(): void
    {
        Passport::actingAs($this->user);

        $category = Category::factory()->create(['user_id' => $this->user->user_id]);
        $item = Item::factory()->create([
            'user_id' => $this->user->user_id,
            'category_id' => $category->id,
            'status' => true,
        ]);

        $sale = Sale::factory()->create([
            'user_id' => $this->user->user_id,
            'store_id' => $this->store->id,
            'sales_by' => $this->user->id,
            'type' => 0,
            'cancelled' => false,
        ]);

        SaleLine::factory()->forSale($sale->id)->forItem($item->id)->create([
            'sub_total' => 500,
            'qty' => 2,
            'unit_qty' => 1,
        ]);

        $response = $this->getJson('/api/v1/mobile/reports/categories');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'categories' => [
                    '*' => [
                        'id',
                        'name',
                        'total_sales',
                        'items_sold',
                        'product_count',
                        'percentage_of_total',
                    ],
                ],
                'summary' => [
                    'total_categories',
                    'total_sales',
                    'total_items_sold',
                    'average_per_category',
                ],
            ],
        ]);
    }

    public function test_can_get_category_items(): void
    {
        Passport::actingAs($this->user);

        $category = Category::factory()->create(['user_id' => $this->user->user_id]);
        $item = Item::factory()->create([
            'user_id' => $this->user->user_id,
            'category_id' => $category->id,
            'status' => true,
        ]);

        $sale = Sale::factory()->create([
            'user_id' => $this->user->user_id,
            'store_id' => $this->store->id,
            'sales_by' => $this->user->id,
            'type' => 0,
            'cancelled' => false,
        ]);

        SaleLine::factory()->forSale($sale->id)->forItem($item->id)->create([
            'sub_total' => 500,
            'qty' => 2,
            'unit_qty' => 1,
        ]);

        $response = $this->getJson("/api/v1/mobile/reports/categories/{$category->id}/items");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'category' => [
                    'id',
                    'name',
                ],
                'items' => [
                    '*' => [
                        'id',
                        'name',
                        'barcode',
                        'total_sales',
                        'items_sold',
                        'percentage_of_category',
                    ],
                ],
                'summary' => [
                    'total_items',
                    'total_sales',
                    'total_quantity_sold',
                ],
            ],
        ]);
    }

    public function test_category_items_returns_404_for_invalid_category(): void
    {
        Passport::actingAs($this->user);

        $response = $this->getJson('/api/v1/mobile/reports/categories/99999/items');

        $response->assertStatus(404);
    }

    public function test_can_get_refund_report(): void
    {
        Passport::actingAs($this->user);

        Sale::factory()->create([
            'user_id' => $this->user->user_id,
            'store_id' => $this->store->id,
            'sales_by' => $this->user->id,
            'type' => 1,
            'cancelled' => false,
            'total' => 100,
        ]);

        $response = $this->getJson('/api/v1/mobile/reports/refunds');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'refunds' => [
                    '*' => [
                        'id',
                        'refund_number',
                        'original_sale_number',
                        'customer_name',
                        'reason',
                        'amount',
                        'item_count',
                        'created_at',
                        'processed_by',
                    ],
                ],
                'pagination' => [
                    'total',
                    'limit',
                    'offset',
                    'has_more',
                ],
            ],
        ]);
    }

    public function test_refund_report_respects_pagination(): void
    {
        Passport::actingAs($this->user);

        Sale::factory()->count(5)->create([
            'user_id' => $this->user->user_id,
            'store_id' => $this->store->id,
            'sales_by' => $this->user->id,
            'type' => 1,
            'cancelled' => false,
        ]);

        $response = $this->getJson('/api/v1/mobile/reports/refunds?limit=2&offset=0');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data.refunds'));
        $this->assertEquals(5, $response->json('data.pagination.total'));
        $this->assertTrue($response->json('data.pagination.has_more'));
    }

    public function test_can_get_refund_summary(): void
    {
        Passport::actingAs($this->user);

        Sale::factory()->create([
            'user_id' => $this->user->user_id,
            'store_id' => $this->store->id,
            'sales_by' => $this->user->id,
            'type' => 0,
            'cancelled' => false,
        ]);

        Sale::factory()->create([
            'user_id' => $this->user->user_id,
            'store_id' => $this->store->id,
            'sales_by' => $this->user->id,
            'type' => 1,
            'cancelled' => false,
            'total' => 100,
        ]);

        $response = $this->getJson('/api/v1/mobile/reports/refunds/summary');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'total_refund_amount',
                'refund_count',
                'refund_rate',
                'average_refund',
                'reasons_breakdown',
            ],
        ]);
    }

    public function test_refund_summary_includes_reasons_breakdown(): void
    {
        Passport::actingAs($this->user);

        Sale::factory()->create([
            'user_id' => $this->user->user_id,
            'store_id' => $this->store->id,
            'sales_by' => $this->user->id,
            'type' => 1,
            'cancelled' => false,
        ]);
        Sale::factory()->create([
            'user_id' => $this->user->user_id,
            'store_id' => $this->store->id,
            'sales_by' => $this->user->id,
            'type' => 1,
            'cancelled' => false,
        ]);

        $response = $this->getJson('/api/v1/mobile/reports/refunds/summary');

        $response->assertStatus(200);
        $reasonsBreakdown = $response->json('data.reasons_breakdown');
        $this->assertNotEmpty($reasonsBreakdown);
    }

    public function test_unauthenticated_user_cannot_access_inventory_report(): void
    {
        $response = $this->getJson('/api/v1/mobile/reports/inventory');

        $response->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_access_low_stock(): void
    {
        $response = $this->getJson('/api/v1/mobile/reports/inventory/low-stock');

        $response->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_access_suppliers(): void
    {
        $response = $this->getJson('/api/v1/mobile/reports/suppliers');

        $response->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_access_categories(): void
    {
        $response = $this->getJson('/api/v1/mobile/reports/categories');

        $response->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_access_refunds(): void
    {
        $response = $this->getJson('/api/v1/mobile/reports/refunds');

        $response->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_access_refund_summary(): void
    {
        $response = $this->getJson('/api/v1/mobile/reports/refunds/summary');

        $response->assertStatus(401);
    }
}
