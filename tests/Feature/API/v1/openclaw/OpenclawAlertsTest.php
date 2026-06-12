<?php

namespace Tests\Feature\API\v1\openclaw;

use App\Models\Accounting\Bank;
use App\Models\ApiToken;
use App\Models\Employees\Role;
use App\Models\Products\Item;
use App\Models\Products\ItemStore;
use App\Models\Settings\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpenclawAlertsTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Store $store;

    protected string $readToken;

    protected string $writeToken;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::factory()->admin()->create();
        $this->owner = User::factory()->create(['role_id' => $role->id]);
        $this->owner->forceFill(['user_id' => $this->owner->id])->save();
        $this->store = Store::factory()->create(['user_id' => $this->owner->user_id]);

        $this->readToken = $this->mintToken(['openclaw:read']);
        $this->writeToken = $this->mintToken([
            'openclaw:read',
            'openclaw:items:write',
            'openclaw:banks:write',
        ]);
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

    private function makeItem(int $stock, ?int $threshold = null): Item
    {
        $item = Item::factory()->create([
            'user_id' => $this->owner->user_id,
            'low_stock_threshold' => $threshold,
            'status' => true,
        ]);
        ItemStore::factory()->create([
            'item_id' => $item->id,
            'store_id' => $this->store->id,
            'stock' => $stock,
        ]);

        return $item;
    }

    public function test_low_stock_uses_per_item_threshold_when_set(): void
    {
        // System default is 10. Override on a high-volume product to 100.
        $bigSeller = $this->makeItem(stock: 50, threshold: 100);  // below custom threshold
        $regular = $this->makeItem(stock: 9, threshold: null);    // below default threshold
        $healthy = $this->makeItem(stock: 200, threshold: null);  // not low

        $response = $this->withHeader('Authorization', "Bearer {$this->readToken}")
            ->getJson('/api/v1/openclaw/inventory/low-stock');

        $response->assertStatus(200)->assertJsonPath('data.summary.low_stock_count', 2);

        $itemIds = collect($response->json('data.items'))->pluck('item_id')->all();
        $this->assertContains($bigSeller->id, $itemIds);
        $this->assertContains($regular->id, $itemIds);
        $this->assertNotContains($healthy->id, $itemIds);

        // Each item reports its effective threshold.
        $rows = collect($response->json('data.items'))->keyBy('item_id');
        $this->assertSame(100, $rows[$bigSeller->id]['effective_threshold']);
        $this->assertSame(10, $rows[$regular->id]['effective_threshold']);
        $this->assertSame(100, $rows[$bigSeller->id]['low_stock_threshold']);
        $this->assertNull($rows[$regular->id]['low_stock_threshold']);
    }

    public function test_patch_item_alert_sets_threshold(): void
    {
        $item = $this->makeItem(stock: 50);

        $response = $this->withHeader('Authorization', "Bearer {$this->writeToken}")
            ->patchJson("/api/v1/openclaw/items/{$item->id}/alert", ['low_stock_threshold' => 200]);

        $response->assertStatus(200)
            ->assertJsonPath('data.item.low_stock_threshold', 200)
            ->assertJsonPath('data.item.effective_threshold', 200);
        $this->assertSame(200, (int) $item->fresh()->low_stock_threshold);
    }

    public function test_patch_item_alert_clears_threshold_when_null(): void
    {
        $item = $this->makeItem(stock: 50, threshold: 80);

        $this->withHeader('Authorization', "Bearer {$this->writeToken}")
            ->patchJson("/api/v1/openclaw/items/{$item->id}/alert", ['low_stock_threshold' => null])
            ->assertStatus(200)
            ->assertJsonPath('data.item.low_stock_threshold', null)
            ->assertJsonPath('data.item.effective_threshold', 10);

        $this->assertNull($item->fresh()->low_stock_threshold);
    }

    public function test_patch_item_alert_requires_items_write_ability(): void
    {
        $item = $this->makeItem(stock: 50);

        $this->withHeader('Authorization', "Bearer {$this->readToken}")
            ->patchJson("/api/v1/openclaw/items/{$item->id}/alert", ['low_stock_threshold' => 200])
            ->assertStatus(403);
    }

    public function test_patch_item_alert_validates_payload(): void
    {
        $item = $this->makeItem(stock: 50);

        $this->withHeader('Authorization', "Bearer {$this->writeToken}")
            ->patchJson("/api/v1/openclaw/items/{$item->id}/alert", ['low_stock_threshold' => -5])
            ->assertStatus(422)
            ->assertJsonValidationErrors('low_stock_threshold');
    }

    public function test_patch_bank_alert_sets_threshold(): void
    {
        $bank = Bank::create([
            'bank_name' => 'GCash', 'account_name' => 'Wallet', 'account_number' => '1',
            'account_type' => Bank::TYPE_EWALLET, 'opening_balance' => 0, 'balance' => 3000,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->writeToken}")
            ->patchJson("/api/v1/openclaw/banks/{$bank->id}/alert", ['low_balance_threshold' => 5000]);

        $response->assertStatus(200)
            ->assertJsonPath('data.bank.below_alert', true);
        $this->assertEqualsWithDelta(5000.0, (float) $bank->fresh()->low_balance_threshold, 0.001);
    }

    public function test_patch_bank_alert_below_alert_is_false_when_balance_above_threshold(): void
    {
        $bank = Bank::create([
            'bank_name' => 'BPI', 'account_name' => 'Main', 'account_number' => '1',
            'account_type' => Bank::TYPE_CHECKING, 'opening_balance' => 0, 'balance' => 200000,
        ]);

        $this->withHeader('Authorization', "Bearer {$this->writeToken}")
            ->patchJson("/api/v1/openclaw/banks/{$bank->id}/alert", ['low_balance_threshold' => 50000])
            ->assertStatus(200)
            ->assertJsonPath('data.bank.below_alert', false);
    }

    public function test_balances_endpoint_includes_threshold_and_below_alert(): void
    {
        $low = Bank::create([
            'bank_name' => 'GCash', 'account_name' => 'Wallet', 'account_number' => '1',
            'account_type' => Bank::TYPE_EWALLET, 'opening_balance' => 0, 'balance' => 3000,
            'low_balance_threshold' => 5000,
        ]);
        $healthy = Bank::create([
            'bank_name' => 'BPI', 'account_name' => 'Main', 'account_number' => '2',
            'account_type' => Bank::TYPE_CHECKING, 'opening_balance' => 0, 'balance' => 200000,
            'low_balance_threshold' => 50000,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->readToken}")
            ->getJson('/api/v1/openclaw/banks/balances');

        $response->assertStatus(200);
        $rows = collect($response->json('data.accounts'))->keyBy('id');
        $this->assertTrue($rows[$low->id]['below_alert']);
        $this->assertFalse($rows[$healthy->id]['below_alert']);
        $this->assertEqualsWithDelta(5000.0, $rows[$low->id]['low_balance_threshold'], 0.001);
    }

    public function test_patch_bank_alert_requires_banks_write_ability(): void
    {
        $bank = Bank::create([
            'bank_name' => 'BPI', 'account_name' => 'Main', 'account_number' => '1',
            'account_type' => Bank::TYPE_CHECKING, 'opening_balance' => 0, 'balance' => 100,
        ]);

        $this->withHeader('Authorization', "Bearer {$this->readToken}")
            ->patchJson("/api/v1/openclaw/banks/{$bank->id}/alert", ['low_balance_threshold' => 5000])
            ->assertStatus(403);
    }

    public function test_patch_item_alert_404s_for_other_tenant_item(): void
    {
        $otherRole = Role::factory()->admin()->create();
        $otherOwner = User::factory()->create(['role_id' => $otherRole->id]);
        $otherOwner->forceFill(['user_id' => $otherOwner->id])->save();

        $foreignItem = Item::factory()->create([
            'user_id' => $otherOwner->user_id,
            'status' => true,
        ]);

        $this->withHeader('Authorization', "Bearer {$this->writeToken}")
            ->patchJson("/api/v1/openclaw/items/{$foreignItem->id}/alert", ['low_stock_threshold' => 200])
            ->assertStatus(404);
    }
}
