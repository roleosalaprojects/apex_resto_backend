<?php

namespace Tests\Feature\API\v1\openclaw;

use App\Models\ApiToken;
use App\Models\Employees\Role;
use App\Models\InventoryManagement\Purchase;
use App\Models\InventoryManagement\PurchaseLine;
use App\Models\InventoryManagement\Supplier;
use App\Models\Products\Item;
use App\Models\Products\ItemStore;
use App\Models\Settings\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpenclawPurchaseReceiveTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Store $store;

    protected Supplier $supplier;

    protected string $receiveToken;

    protected string $readToken;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::factory()->admin()->create();
        $this->owner = User::factory()->create(['role_id' => $role->id]);
        $this->owner->forceFill(['user_id' => $this->owner->id])->save();

        $this->store = Store::factory()->create(['user_id' => $this->owner->user_id]);
        $this->supplier = Supplier::factory()->create(['user_id' => $this->owner->user_id]);

        $this->receiveToken = $this->mintToken(['openclaw:read', 'openclaw:purchases:receive']);
        $this->readToken = $this->mintToken(['openclaw:read']);
    }

    private function mintToken(array $abilities): string
    {
        $plain = ApiToken::generatePlainToken();
        ApiToken::create([
            'user_id' => $this->owner->user_id,
            'name' => 'Test',
            'token' => ApiToken::hashToken($plain),
            'abilities' => $abilities,
        ]);

        return $plain;
    }

    /**
     * Approved PO with one line of qty=10, no received yet. Returns
     * [Purchase, PurchaseLine, ItemStore (starting stock 0)].
     */
    private function makeApprovedPOWithLine(float $orderedQty = 10): array
    {
        $purchase = Purchase::factory()->create([
            'user_id' => $this->owner->user_id,
            'supplier_id' => $this->supplier->id,
            'store_id' => $this->store->id,
            'approval_status' => Purchase::APPROVAL_APPROVED,
            'received' => 0,
        ]);

        $item = Item::factory()->create(['user_id' => $this->owner->user_id, 'status' => true]);

        $line = PurchaseLine::create([
            'purchase_id' => $purchase->id,
            'item_id' => $item->id,
            'unit_id' => 0,
            'qty' => $orderedQty,
            'received' => 0,
            'cost' => 50,
            'sub_total' => $orderedQty * 50,
        ]);

        $itemStore = ItemStore::factory()->create([
            'item_id' => $item->id,
            'store_id' => $this->store->id,
            'stock' => 0,
        ]);

        return [$purchase, $line, $itemStore];
    }

    public function test_receive_partial_increments_line_and_store_stock(): void
    {
        [$po, $line, $itemStore] = $this->makeApprovedPOWithLine(orderedQty: 10);

        $response = $this->withHeader('Authorization', "Bearer {$this->receiveToken}")
            ->postJson("/api/v1/openclaw/purchases/{$po->id}/receive", [
                'lines' => [
                    ['purchase_line_id' => $line->id, 'qty' => 3],
                ],
            ]);

        $response->assertStatus(200);
        $this->assertEqualsWithDelta(3.0, (float) $response->json('data.received_this_call'), 0.001);

        $this->assertEqualsWithDelta(3.0, (float) $line->fresh()->received, 0.001);
        $this->assertEqualsWithDelta(3.0, (float) $itemStore->fresh()->stock, 0.001);
        $this->assertEqualsWithDelta(3.0, (float) $po->fresh()->received, 0.001);
    }

    public function test_receive_subsequent_calls_accumulate(): void
    {
        [$po, $line, $itemStore] = $this->makeApprovedPOWithLine(orderedQty: 10);

        $this->withHeader('Authorization', "Bearer {$this->receiveToken}")
            ->postJson("/api/v1/openclaw/purchases/{$po->id}/receive", [
                'lines' => [['purchase_line_id' => $line->id, 'qty' => 3]],
            ])
            ->assertStatus(200);

        $this->withHeader('Authorization', "Bearer {$this->receiveToken}")
            ->postJson("/api/v1/openclaw/purchases/{$po->id}/receive", [
                'lines' => [['purchase_line_id' => $line->id, 'qty' => 4]],
            ])
            ->assertStatus(200);

        $this->assertEqualsWithDelta(7.0, (float) $line->fresh()->received, 0.001);
        $this->assertEqualsWithDelta(7.0, (float) $itemStore->fresh()->stock, 0.001);
    }

    public function test_receive_rejects_overage_per_line(): void
    {
        [$po, $line] = $this->makeApprovedPOWithLine(orderedQty: 5);

        $this->withHeader('Authorization', "Bearer {$this->receiveToken}")
            ->postJson("/api/v1/openclaw/purchases/{$po->id}/receive", [
                'lines' => [['purchase_line_id' => $line->id, 'qty' => 10]],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('lines.0.qty');

        $this->assertEqualsWithDelta(0.0, (float) $line->fresh()->received, 0.001);
    }

    public function test_receive_requires_approval(): void
    {
        [$po, $line] = $this->makeApprovedPOWithLine();
        $po->update(['approval_status' => Purchase::APPROVAL_PENDING]);

        $this->withHeader('Authorization', "Bearer {$this->receiveToken}")
            ->postJson("/api/v1/openclaw/purchases/{$po->id}/receive", [
                'lines' => [['purchase_line_id' => $line->id, 'qty' => 1]],
            ])
            ->assertStatus(409);
    }

    public function test_receive_requires_purchases_receive_ability(): void
    {
        [$po, $line] = $this->makeApprovedPOWithLine();

        $this->withHeader('Authorization', "Bearer {$this->readToken}")
            ->postJson("/api/v1/openclaw/purchases/{$po->id}/receive", [
                'lines' => [['purchase_line_id' => $line->id, 'qty' => 1]],
            ])
            ->assertStatus(403);
    }

    public function test_receive_rejects_line_from_other_po(): void
    {
        [$po, $myLine] = $this->makeApprovedPOWithLine();
        [, $foreignLine] = $this->makeApprovedPOWithLine();

        $this->withHeader('Authorization', "Bearer {$this->receiveToken}")
            ->postJson("/api/v1/openclaw/purchases/{$po->id}/receive", [
                'lines' => [['purchase_line_id' => $foreignLine->id, 'qty' => 1]],
            ])
            ->assertStatus(422);
    }
}
