<?php

namespace Tests\Feature\API\v1\mobile;

use App\Models\Employees\Role;
use App\Models\InventoryManagement\Adjustment;
use App\Models\InventoryManagement\AdjustmentLine;
use App\Models\InventoryManagement\Count;
use App\Models\InventoryManagement\CountLine;
use App\Models\InventoryManagement\Transfer;
use App\Models\InventoryManagement\TransferLine;
use App\Models\Products\Category;
use App\Models\Products\Item;
use App\Models\Products\ItemStore;
use App\Models\Settings\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class InventoryControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Role $role;

    protected Store $store;

    protected Store $store2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->role = Role::factory()->admin()->create();
        $this->user = User::factory()->create([
            'role_id' => $this->role->id,
            'user_id' => 1,
        ]);
        $this->store = Store::factory()->create(['user_id' => 1]);
        $this->store2 = Store::factory()->create(['user_id' => 1]);
    }

    // ==================== STOCK ADJUSTMENTS ====================

    public function test_can_get_adjustments_list(): void
    {
        Passport::actingAs($this->user);

        $item = Item::factory()->create(['user_id' => $this->user->user_id]);

        $adjustment = Adjustment::create([
            'so' => 1,
            'store_id' => $this->store->id,
            'reason' => 'damage',
            'note' => 'Test adjustment',
            'total' => 1,
            'received' => 0,
            'status' => 0,
            'created_by' => $this->user->id,
            'user_id' => $this->user->user_id,
        ]);

        AdjustmentLine::create([
            'adjustment_id' => $adjustment->id,
            'item_id' => $item->id,
            'qty' => 5,
            'received' => 0,
            'unit_qty' => 1,
        ]);

        $response = $this->getJson('/api/v1/mobile/inventory/adjustments');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'adjustments' => [
                    '*' => [
                        'id',
                        'adjustment_number',
                        'store',
                        'type',
                        'total_items',
                        'status',
                        'note',
                        'created_at',
                        'created_by',
                    ],
                ],
                'total',
            ],
        ]);
    }

    public function test_adjustments_list_filters_by_store(): void
    {
        Passport::actingAs($this->user);

        Adjustment::create([
            'so' => 1,
            'store_id' => $this->store->id,
            'reason' => 'damage',
            'total' => 1,
            'received' => 0,
            'status' => 0,
            'created_by' => $this->user->id,
            'user_id' => $this->user->user_id,
        ]);

        Adjustment::create([
            'so' => 2,
            'store_id' => $this->store2->id,
            'reason' => 'theft',
            'total' => 1,
            'received' => 0,
            'status' => 0,
            'created_by' => $this->user->id,
            'user_id' => $this->user->user_id,
        ]);

        $response = $this->getJson("/api/v1/mobile/inventory/adjustments?store_id={$this->store->id}");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.adjustments'));
    }

    public function test_adjustments_list_filters_by_type(): void
    {
        Passport::actingAs($this->user);

        Adjustment::create([
            'so' => 1,
            'store_id' => $this->store->id,
            'reason' => 'damage',
            'total' => 1,
            'received' => 0,
            'status' => 0,
            'created_by' => $this->user->id,
            'user_id' => $this->user->user_id,
        ]);

        Adjustment::create([
            'so' => 2,
            'store_id' => $this->store->id,
            'reason' => 'theft',
            'total' => 1,
            'received' => 0,
            'status' => 0,
            'created_by' => $this->user->id,
            'user_id' => $this->user->user_id,
        ]);

        $response = $this->getJson('/api/v1/mobile/inventory/adjustments?type=damage');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.adjustments'));
        $this->assertEquals('damage', $response->json('data.adjustments.0.type'));
    }

    public function test_adjustments_list_filters_by_date_range(): void
    {
        Passport::actingAs($this->user);

        Adjustment::create([
            'so' => 1,
            'store_id' => $this->store->id,
            'reason' => 'damage',
            'total' => 1,
            'received' => 0,
            'status' => 0,
            'created_by' => $this->user->id,
            'user_id' => $this->user->user_id,
            'created_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/mobile/inventory/adjustments?start_date='.now()->toDateString().'&end_date='.now()->toDateString());

        $response->assertStatus(200);
    }

    public function test_can_create_adjustment(): void
    {
        Passport::actingAs($this->user);

        $item = Item::factory()->create(['user_id' => $this->user->user_id]);

        $response = $this->postJson('/api/v1/mobile/inventory/adjustments', [
            'store_id' => $this->store->id,
            'type' => 'damage',
            'status' => true,
            'note' => 'Damaged during transport',
            'items' => [
                ['product_id' => $item->id, 'quantity' => -5],
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'id',
                'adjustment_number',
            ],
        ]);

        $this->assertDatabaseHas('adjustments', [
            'store_id' => $this->store->id,
            'reason' => 'damage',
        ]);
    }

    public function test_create_adjustment_validates_required_fields(): void
    {
        Passport::actingAs($this->user);

        $response = $this->postJson('/api/v1/mobile/inventory/adjustments', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['store_id', 'type', 'items']);
    }

    public function test_create_adjustment_validates_type(): void
    {
        Passport::actingAs($this->user);

        $item = Item::factory()->create(['user_id' => $this->user->user_id]);

        $response = $this->postJson('/api/v1/mobile/inventory/adjustments', [
            'store_id' => $this->store->id,
            'type' => 'invalid_type',
            'items' => [
                ['product_id' => $item->id, 'quantity' => -5],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['type']);
    }

    public function test_can_get_adjustment_reasons(): void
    {
        Passport::actingAs($this->user);

        $response = $this->getJson('/api/v1/mobile/inventory/adjustment-reasons');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                '*' => ['value', 'label'],
            ],
        ]);

        $data = $response->json('data');
        $values = array_column($data, 'value');
        $this->assertContains('damage', $values);
        $this->assertContains('theft', $values);
        $this->assertContains('correction', $values);
    }

    // ==================== STOCK TRANSFERS ====================

    public function test_can_get_transfers_list(): void
    {
        Passport::actingAs($this->user);

        $item = Item::factory()->create(['user_id' => $this->user->user_id]);

        $transfer = Transfer::create([
            'to' => 1,
            'source_store' => $this->store->id,
            'destination_store' => $this->store2->id,
            'note' => 'Test transfer',
            'total' => 1,
            'received' => 0,
            'status' => 0,
            'created_by' => $this->user->id,
            'user_id' => $this->user->user_id,
        ]);

        TransferLine::create([
            'transfer_id' => $transfer->id,
            'item_id' => $item->id,
            'qty' => 10,
            'received' => 0,
            'unit_qty' => 1,
        ]);

        $response = $this->getJson('/api/v1/mobile/inventory/transfers');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'transfers' => [
                    '*' => [
                        'id',
                        'transfer_number',
                        'from_store',
                        'to_store',
                        'status',
                        'item_count',
                        'note',
                        'created_at',
                        'created_by',
                    ],
                ],
                'total',
            ],
        ]);
    }

    public function test_transfers_list_filters_by_from_store(): void
    {
        Passport::actingAs($this->user);

        Transfer::create([
            'to' => 1,
            'source_store' => $this->store->id,
            'destination_store' => $this->store2->id,
            'total' => 1,
            'received' => 0,
            'status' => 0,
            'created_by' => $this->user->id,
            'user_id' => $this->user->user_id,
        ]);

        $response = $this->getJson("/api/v1/mobile/inventory/transfers?from_store_id={$this->store->id}");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.transfers'));
    }

    public function test_transfers_list_filters_by_status(): void
    {
        Passport::actingAs($this->user);

        Transfer::create([
            'to' => 1,
            'source_store' => $this->store->id,
            'destination_store' => $this->store2->id,
            'total' => 1,
            'received' => 0,
            'status' => 0,
            'created_by' => $this->user->id,
            'user_id' => $this->user->user_id,
        ]);

        Transfer::create([
            'to' => 2,
            'source_store' => $this->store->id,
            'destination_store' => $this->store2->id,
            'total' => 1,
            'received' => 0,
            'status' => 1,
            'created_by' => $this->user->id,
            'user_id' => $this->user->user_id,
        ]);

        $response = $this->getJson('/api/v1/mobile/inventory/transfers?status=pending');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.transfers'));
        $this->assertEquals('pending', $response->json('data.transfers.0.status'));
    }

    public function test_can_create_transfer(): void
    {
        Passport::actingAs($this->user);

        $item = Item::factory()->create(['user_id' => $this->user->user_id]);

        $response = $this->postJson('/api/v1/mobile/inventory/transfers', [
            'from_store_id' => $this->store->id,
            'to_store_id' => $this->store2->id,
            'note' => 'Stock rebalancing',
            'items' => [
                ['product_id' => $item->id, 'quantity' => 10],
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'id',
                'transfer_number',
            ],
        ]);

        $this->assertDatabaseHas('transfers', [
            'source_store' => $this->store->id,
            'destination_store' => $this->store2->id,
        ]);
    }

    public function test_create_transfer_validates_different_stores(): void
    {
        Passport::actingAs($this->user);

        $item = Item::factory()->create(['user_id' => $this->user->user_id]);

        $response = $this->postJson('/api/v1/mobile/inventory/transfers', [
            'from_store_id' => $this->store->id,
            'to_store_id' => $this->store->id,
            'items' => [
                ['product_id' => $item->id, 'quantity' => 10],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['to_store_id']);
    }

    public function test_create_transfer_validates_required_fields(): void
    {
        Passport::actingAs($this->user);

        $response = $this->postJson('/api/v1/mobile/inventory/transfers', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['from_store_id', 'to_store_id', 'items']);
    }

    public function test_can_show_transfer_details(): void
    {
        Passport::actingAs($this->user);

        $item = Item::factory()->create(['user_id' => $this->user->user_id]);

        $transfer = Transfer::create([
            'to' => 1,
            'source_store' => $this->store->id,
            'destination_store' => $this->store2->id,
            'note' => 'Test transfer',
            'total' => 1,
            'received' => 0,
            'status' => 0,
            'created_by' => $this->user->id,
            'user_id' => $this->user->user_id,
        ]);

        TransferLine::create([
            'transfer_id' => $transfer->id,
            'item_id' => $item->id,
            'qty' => 10,
            'received' => 0,
            'unit_qty' => 1,
        ]);

        $response = $this->getJson("/api/v1/mobile/inventory/transfers/{$transfer->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'id',
                'transfer_number',
                'from_store',
                'to_store',
                'status',
                'items',
                'note',
                'created_at',
                'created_by',
            ],
        ]);
    }

    public function test_show_transfer_returns_404_for_other_users_transfer(): void
    {
        Passport::actingAs($this->user);

        $transfer = Transfer::create([
            'to' => 1,
            'source_store' => $this->store->id,
            'destination_store' => $this->store2->id,
            'total' => 1,
            'received' => 0,
            'status' => 0,
            'created_by' => $this->user->id,
            'user_id' => 9999,
        ]);

        $response = $this->getJson("/api/v1/mobile/inventory/transfers/{$transfer->id}");

        $response->assertStatus(404);
    }

    public function test_can_update_transfer_status_to_approved(): void
    {
        Passport::actingAs($this->user);

        $transfer = Transfer::create([
            'to' => 1,
            'source_store' => $this->store->id,
            'destination_store' => $this->store2->id,
            'total' => 1,
            'received' => 0,
            'status' => 0,
            'created_by' => $this->user->id,
            'user_id' => $this->user->user_id,
        ]);

        $response = $this->patchJson("/api/v1/mobile/inventory/transfers/{$transfer->id}", [
            'status' => 'approved',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('transfers', [
            'id' => $transfer->id,
            'status' => 1,
        ]);
    }

    public function test_can_update_transfer_status_to_in_transit(): void
    {
        Passport::actingAs($this->user);

        $transfer = Transfer::create([
            'to' => 1,
            'source_store' => $this->store->id,
            'destination_store' => $this->store2->id,
            'total' => 1,
            'received' => 0,
            'status' => 1,
            'created_by' => $this->user->id,
            'user_id' => $this->user->user_id,
        ]);

        $response = $this->patchJson("/api/v1/mobile/inventory/transfers/{$transfer->id}", [
            'status' => 'in_transit',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('transfers', [
            'id' => $transfer->id,
            'status' => 2,
        ]);
    }

    public function test_can_update_transfer_status_to_completed(): void
    {
        Passport::actingAs($this->user);

        $transfer = Transfer::create([
            'to' => 1,
            'source_store' => $this->store->id,
            'destination_store' => $this->store2->id,
            'total' => 1,
            'received' => 0,
            'status' => 2,
            'created_by' => $this->user->id,
            'user_id' => $this->user->user_id,
        ]);

        $response = $this->patchJson("/api/v1/mobile/inventory/transfers/{$transfer->id}", [
            'status' => 'completed',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('transfers', [
            'id' => $transfer->id,
            'status' => 3,
        ]);
    }

    public function test_cannot_transition_transfer_to_invalid_status(): void
    {
        Passport::actingAs($this->user);

        $transfer = Transfer::create([
            'to' => 1,
            'source_store' => $this->store->id,
            'destination_store' => $this->store2->id,
            'total' => 1,
            'received' => 0,
            'status' => 0,
            'created_by' => $this->user->id,
            'user_id' => $this->user->user_id,
        ]);

        $response = $this->patchJson("/api/v1/mobile/inventory/transfers/{$transfer->id}", [
            'status' => 'completed',
        ]);

        $response->assertStatus(422);
    }

    // ==================== INVENTORY COUNTS ====================

    public function test_can_get_counts_list(): void
    {
        Passport::actingAs($this->user);

        Count::create([
            'ic' => 1,
            'store_id' => $this->store->id,
            'total' => 10,
            'status' => 0,
            'created_by' => $this->user->id,
            'user_id' => $this->user->user_id,
        ]);

        $response = $this->getJson('/api/v1/mobile/inventory/counts');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'counts' => [
                    '*' => [
                        'id',
                        'count_number',
                        'store',
                        'status',
                        'item_count',
                        'counted_items',
                        'created_at',
                        'created_by',
                    ],
                ],
                'total',
            ],
        ]);
    }

    public function test_counts_list_filters_by_store(): void
    {
        Passport::actingAs($this->user);

        Count::create([
            'ic' => 1,
            'store_id' => $this->store->id,
            'total' => 10,
            'status' => 0,
            'created_by' => $this->user->id,
            'user_id' => $this->user->user_id,
        ]);

        Count::create([
            'ic' => 2,
            'store_id' => $this->store2->id,
            'total' => 5,
            'status' => 0,
            'created_by' => $this->user->id,
            'user_id' => $this->user->user_id,
        ]);

        $response = $this->getJson("/api/v1/mobile/inventory/counts?store_id={$this->store->id}");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.counts'));
    }

    public function test_counts_list_filters_by_status(): void
    {
        Passport::actingAs($this->user);

        Count::create([
            'ic' => 1,
            'store_id' => $this->store->id,
            'total' => 10,
            'status' => 0,
            'created_by' => $this->user->id,
            'user_id' => $this->user->user_id,
        ]);

        Count::create([
            'ic' => 2,
            'store_id' => $this->store->id,
            'total' => 5,
            'status' => 2,
            'created_by' => $this->user->id,
            'user_id' => $this->user->user_id,
        ]);

        $response = $this->getJson('/api/v1/mobile/inventory/counts?status=0');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data.counts'));
        $this->assertEquals('draft', $response->json('data.counts.0.status'));
    }

    public function test_can_create_count(): void
    {
        Passport::actingAs($this->user);

        Item::factory()->count(5)->create([
            'user_id' => $this->user->user_id,
            'status' => true,
        ]);

        $response = $this->postJson('/api/v1/mobile/inventory/counts', [
            'store_id' => $this->store->id,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'id',
                'count_number',
            ],
        ]);

        $this->assertDatabaseHas('counts', [
            'store_id' => $this->store->id,
            'total' => 5,
        ]);
    }

    public function test_create_count_filters_by_category(): void
    {
        Passport::actingAs($this->user);

        $category = Category::factory()->create(['user_id' => $this->user->user_id]);

        Item::factory()->count(3)->create([
            'user_id' => $this->user->user_id,
            'category_id' => $category->id,
            'status' => true,
        ]);

        Item::factory()->count(2)->create([
            'user_id' => $this->user->user_id,
            'status' => true,
        ]);

        $response = $this->postJson('/api/v1/mobile/inventory/counts', [
            'store_id' => $this->store->id,
            'category_id' => $category->id,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('counts', [
            'store_id' => $this->store->id,
            'total' => 3,
        ]);
    }

    public function test_create_count_validates_required_fields(): void
    {
        Passport::actingAs($this->user);

        $response = $this->postJson('/api/v1/mobile/inventory/counts', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['store_id']);
    }

    public function test_can_show_count_details(): void
    {
        Passport::actingAs($this->user);

        $item = Item::factory()->create(['user_id' => $this->user->user_id]);

        $count = Count::create([
            'ic' => 1,
            'store_id' => $this->store->id,
            'total' => 1,
            'status' => 0,
            'created_by' => $this->user->id,
            'user_id' => $this->user->user_id,
        ]);

        CountLine::create([
            'count_id' => $count->id,
            'item_id' => $item->id,
        ]);

        $response = $this->getJson("/api/v1/mobile/inventory/counts/{$count->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'id',
                'count_number',
                'store',
                'status',
                'items',
                'created_at',
                'created_by',
            ],
        ]);
    }

    public function test_show_count_returns_404_for_other_users_count(): void
    {
        Passport::actingAs($this->user);

        $count = Count::create([
            'ic' => 1,
            'store_id' => $this->store->id,
            'total' => 1,
            'status' => 0,
            'created_by' => $this->user->id,
            'user_id' => 9999,
        ]);

        $response = $this->getJson("/api/v1/mobile/inventory/counts/{$count->id}");

        $response->assertStatus(404);
    }

    public function test_can_add_item_to_count(): void
    {
        Passport::actingAs($this->user);

        $item = Item::factory()->create(['user_id' => $this->user->user_id]);

        $count = Count::create([
            'ic' => 1,
            'store_id' => $this->store->id,
            'total' => 10,
            'status' => 0,
            'created_by' => $this->user->id,
            'user_id' => $this->user->user_id,
        ]);

        $response = $this->patchJson("/api/v1/mobile/inventory/counts/{$count->id}/items", [
            'item_id' => $item->id,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('count_lines', [
            'count_id' => $count->id,
            'item_id' => $item->id,
        ]);
    }

    public function test_adding_item_changes_count_status_to_in_progress(): void
    {
        Passport::actingAs($this->user);

        $item = Item::factory()->create(['user_id' => $this->user->user_id]);

        $count = Count::create([
            'ic' => 1,
            'store_id' => $this->store->id,
            'total' => 10,
            'status' => 0,
            'created_by' => $this->user->id,
            'user_id' => $this->user->user_id,
        ]);

        $this->patchJson("/api/v1/mobile/inventory/counts/{$count->id}/items", [
            'item_id' => $item->id,
        ]);

        $this->assertDatabaseHas('counts', [
            'id' => $count->id,
            'status' => 1,
        ]);
    }

    public function test_cannot_add_duplicate_item_to_count(): void
    {
        Passport::actingAs($this->user);

        $item = Item::factory()->create(['user_id' => $this->user->user_id]);

        $count = Count::create([
            'ic' => 1,
            'store_id' => $this->store->id,
            'total' => 10,
            'status' => 0,
            'created_by' => $this->user->id,
            'user_id' => $this->user->user_id,
        ]);

        CountLine::create([
            'count_id' => $count->id,
            'item_id' => $item->id,
        ]);

        $response = $this->patchJson("/api/v1/mobile/inventory/counts/{$count->id}/items", [
            'item_id' => $item->id,
        ]);

        $response->assertStatus(422);
    }

    public function test_cannot_add_item_to_completed_count(): void
    {
        Passport::actingAs($this->user);

        $item = Item::factory()->create(['user_id' => $this->user->user_id]);

        $count = Count::create([
            'ic' => 1,
            'store_id' => $this->store->id,
            'total' => 10,
            'status' => 2,
            'created_by' => $this->user->id,
            'user_id' => $this->user->user_id,
        ]);

        $response = $this->patchJson("/api/v1/mobile/inventory/counts/{$count->id}/items", [
            'item_id' => $item->id,
        ]);

        $response->assertStatus(422);
    }

    public function test_can_finalize_count(): void
    {
        Passport::actingAs($this->user);

        $item = Item::factory()->create(['user_id' => $this->user->user_id]);

        $count = Count::create([
            'ic' => 1,
            'store_id' => $this->store->id,
            'total' => 10,
            'status' => 1,
            'created_by' => $this->user->id,
            'user_id' => $this->user->user_id,
        ]);

        CountLine::create([
            'count_id' => $count->id,
            'item_id' => $item->id,
            'counted_qty' => 10,
        ]);

        $response = $this->postJson("/api/v1/mobile/inventory/counts/{$count->id}/finalize");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => ['items_counted'],
        ]);
        $this->assertDatabaseHas('counts', [
            'id' => $count->id,
            'status' => 2,
        ]);
    }

    public function test_cannot_finalize_already_completed_count(): void
    {
        Passport::actingAs($this->user);

        $count = Count::create([
            'ic' => 1,
            'store_id' => $this->store->id,
            'total' => 10,
            'status' => 2,
            'created_by' => $this->user->id,
            'user_id' => $this->user->user_id,
        ]);

        $response = $this->postJson("/api/v1/mobile/inventory/counts/{$count->id}/finalize");

        $response->assertStatus(422);
    }

    // ==================== LOW STOCK ALERTS ====================

    public function test_can_get_low_stock_items(): void
    {
        Passport::actingAs($this->user);

        $item = Item::factory()->create([
            'user_id' => $this->user->user_id,
            'status' => true,
            'cost' => 100,
        ]);

        ItemStore::factory()->create([
            'item_id' => $item->id,
            'store_id' => $this->store->id,
            'stock' => 5,
        ]);

        $response = $this->getJson('/api/v1/mobile/inventory/low-stock');

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
                        'suggested_order_quantity',
                        'store_id',
                        'category',
                        'unit_cost',
                    ],
                ],
                'summary' => [
                    'out_of_stock_count',
                    'critical_count',
                    'low_stock_count',
                ],
            ],
        ]);
    }

    public function test_low_stock_filters_by_store(): void
    {
        Passport::actingAs($this->user);

        $item1 = Item::factory()->create([
            'user_id' => $this->user->user_id,
            'status' => true,
        ]);
        $item2 = Item::factory()->create([
            'user_id' => $this->user->user_id,
            'status' => true,
        ]);

        ItemStore::factory()->create([
            'item_id' => $item1->id,
            'store_id' => $this->store->id,
            'stock' => 5,
        ]);
        ItemStore::factory()->create([
            'item_id' => $item2->id,
            'store_id' => $this->store2->id,
            'stock' => 5,
        ]);

        $response = $this->getJson("/api/v1/mobile/inventory/low-stock?store_id={$this->store->id}");

        $response->assertStatus(200);
        $items = $response->json('data.items');
        foreach ($items as $responseItem) {
            $this->assertEquals($this->store->id, $responseItem['store_id']);
        }
    }

    public function test_low_stock_filters_by_category(): void
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
            'stock' => 5,
        ]);

        $response = $this->getJson("/api/v1/mobile/inventory/low-stock?category_id={$category->id}");

        $response->assertStatus(200);
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

        $response = $this->getJson('/api/v1/mobile/inventory/low-stock?limit=2');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data.items'));
    }

    public function test_can_get_count_sheet(): void
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

        $response = $this->getJson("/api/v1/mobile/inventory/count-sheet?store_id={$this->store->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'items' => [
                    '*' => [
                        'product_id',
                        'name',
                        'barcode',
                        'category',
                        'system_quantity',
                    ],
                ],
            ],
        ]);
    }

    public function test_count_sheet_validates_store_id(): void
    {
        Passport::actingAs($this->user);

        $response = $this->getJson('/api/v1/mobile/inventory/count-sheet');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['store_id']);
    }

    public function test_count_sheet_filters_by_category(): void
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

        $response = $this->getJson("/api/v1/mobile/inventory/count-sheet?store_id={$this->store->id}&category_id={$category->id}");

        $response->assertStatus(200);
    }

    // ==================== AUTHENTICATION TESTS ====================

    public function test_unauthenticated_user_cannot_access_adjustments(): void
    {
        $response = $this->getJson('/api/v1/mobile/inventory/adjustments');
        $response->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_access_transfers(): void
    {
        $response = $this->getJson('/api/v1/mobile/inventory/transfers');
        $response->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_access_counts(): void
    {
        $response = $this->getJson('/api/v1/mobile/inventory/counts');
        $response->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_access_low_stock(): void
    {
        $response = $this->getJson('/api/v1/mobile/inventory/low-stock');
        $response->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_access_count_sheet(): void
    {
        $response = $this->getJson('/api/v1/mobile/inventory/count-sheet?store_id=1');
        $response->assertStatus(401);
    }
}
