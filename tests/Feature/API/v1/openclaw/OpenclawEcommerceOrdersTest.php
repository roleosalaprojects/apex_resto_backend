<?php

namespace Tests\Feature\API\v1\openclaw;

use App\Models\Accounting\Bank;
use App\Models\ApiToken;
use App\Models\CustomerRelations\Customer;
use App\Models\Ecommerce\EcommerceOrder;
use App\Models\Ecommerce\EcommerceOrderLine;
use App\Models\Employees\Role;
use App\Models\Pos\Sale;
use App\Models\Products\Category;
use App\Models\Products\Item;
use App\Models\Products\ItemStore;
use App\Models\Settings\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpenclawEcommerceOrdersTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected string $plainToken;

    protected Customer $customer;

    protected Store $store;

    protected Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::factory()->admin()->create();
        $this->owner = User::factory()->create(['role_id' => $role->id]);
        $this->owner->forceFill(['user_id' => $this->owner->id])->save();

        $this->plainToken = ApiToken::generatePlainToken();
        ApiToken::create([
            'user_id' => $this->owner->user_id,
            'name' => 'Test Bot',
            'token' => ApiToken::hashToken($this->plainToken),
            'abilities' => [
                'openclaw:read',
                'openclaw:ecommerce-orders:verify',
                'openclaw:ecommerce-orders:cancel',
                'openclaw:ecommerce-orders:record-payment',
                'openclaw:ecommerce-orders:mark-preparing',
                'openclaw:ecommerce-orders:mark-picked-up',
            ],
        ]);

        $this->customer = Customer::factory()->create([
            'user_id' => $this->owner->user_id,
            'points' => 0.01,
        ]);

        $this->store = Store::factory()->create(['user_id' => $this->owner->user_id]);

        $this->item = Item::factory()->create([
            'price' => 100,
            'cost' => 60,
            'category_id' => Category::factory()->create()->id,
            'user_id' => $this->owner->user_id,
            'creditable_to_points' => 1,
            'status' => true,
        ]);
        ItemStore::create([
            'item_id' => $this->item->id,
            'store_id' => $this->store->id,
            'stock' => 50,
        ]);
    }

    private function authed(): self
    {
        return $this->withHeader('Authorization', "Bearer {$this->plainToken}");
    }

    private function makeOrder(int $status = EcommerceOrder::STATUS_PENDING): EcommerceOrder
    {
        $order = EcommerceOrder::create([
            'reference' => 'ECO-'.strtoupper(substr(md5(uniqid('', true)), 0, 8)),
            'customer_id' => $this->customer->id,
            'total' => 200,
            'qty' => 2,
            'status' => $status,
            'is_wholesale' => false,
        ]);
        EcommerceOrderLine::create([
            'ecommerce_order_id' => $order->id,
            'item_id' => $this->item->id,
            'item_name' => $this->item->name,
            'qty' => 2,
            'price' => 100,
            'sub_total' => 200,
        ]);

        return $order;
    }

    public function test_index_lists_tenant_orders(): void
    {
        $mine = $this->makeOrder();

        // Another tenant — should be excluded.
        $other = User::factory()->create(['role_id' => Role::factory()->create()->id]);
        $other->forceFill(['user_id' => $other->id])->save();
        $foreignCustomer = Customer::factory()->create(['user_id' => $other->user_id]);
        EcommerceOrder::create([
            'reference' => 'ECO-OTHERTEN',
            'customer_id' => $foreignCustomer->id,
            'total' => 99,
            'qty' => 1,
            'status' => EcommerceOrder::STATUS_PENDING,
            'is_wholesale' => false,
        ]);

        $response = $this->authed()->getJson('/api/v1/openclaw/ecommerce-orders');

        $response->assertStatus(200);
        $references = collect($response->json('data.orders'))->pluck('reference')->all();
        $this->assertContains($mine->reference, $references);
        $this->assertNotContains('ECO-OTHERTEN', $references);
    }

    public function test_pending_returns_only_pending_orders(): void
    {
        $pending = $this->makeOrder();
        $this->makeOrder(EcommerceOrder::STATUS_PAID);

        $response = $this->authed()->getJson('/api/v1/openclaw/ecommerce-orders/pending');

        $response->assertStatus(200);
        $response->assertJsonPath('data.count', 1);
        $this->assertSame($pending->reference, $response->json('data.orders.0.reference'));
    }

    public function test_show_includes_lines_status_history_and_sale_summary(): void
    {
        $order = $this->makeOrder(EcommerceOrder::STATUS_VERIFIED);
        $order->logStatusChange(null, EcommerceOrder::STATUS_PENDING, null);
        $order->logStatusChange(EcommerceOrder::STATUS_PENDING, EcommerceOrder::STATUS_VERIFIED, $this->owner->id);

        $response = $this->authed()->getJson("/api/v1/openclaw/ecommerce-orders/{$order->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.order.reference', $order->reference);
        $response->assertJsonCount(1, 'data.order.lines');
        $response->assertJsonCount(2, 'data.order.status_history');
    }

    public function test_verify_pending_order(): void
    {
        $order = $this->makeOrder();

        $response = $this->authed()->postJson("/api/v1/openclaw/ecommerce-orders/{$order->id}/verify");

        $response->assertStatus(200);
        $this->assertSame(EcommerceOrder::STATUS_VERIFIED, (int) $order->fresh()->status);
        $this->assertDatabaseHas('ecommerce_order_status_changes', [
            'ecommerce_order_id' => $order->id,
            'from_status' => EcommerceOrder::STATUS_PENDING,
            'to_status' => EcommerceOrder::STATUS_VERIFIED,
            'changed_by' => $this->owner->id,
        ]);
    }

    public function test_verify_refuses_non_pending_order(): void
    {
        $order = $this->makeOrder(EcommerceOrder::STATUS_PAID);

        $this->authed()->postJson("/api/v1/openclaw/ecommerce-orders/{$order->id}/verify")
            ->assertStatus(409);
    }

    public function test_cancel_pending_order_with_reason(): void
    {
        $order = $this->makeOrder();

        $response = $this->authed()->postJson("/api/v1/openclaw/ecommerce-orders/{$order->id}/cancel", [
            'reason' => 'Customer changed their mind',
        ]);

        $response->assertStatus(200);
        $this->assertSame(EcommerceOrder::STATUS_CANCELLED, (int) $order->fresh()->status);
        $this->assertDatabaseHas('ecommerce_order_status_changes', [
            'ecommerce_order_id' => $order->id,
            'to_status' => EcommerceOrder::STATUS_CANCELLED,
            'note' => 'Customer changed their mind',
        ]);
    }

    public function test_record_cash_payment_advances_to_paid(): void
    {
        $order = $this->makeOrder();

        $response = $this->authed()->postJson("/api/v1/openclaw/ecommerce-orders/{$order->id}/record-payment", [
            'payment_method' => 'cash',
            'store_id' => $this->store->id,
        ]);

        $response->assertStatus(200);
        $this->assertSame(EcommerceOrder::STATUS_PAID, (int) $order->fresh()->status);
        $this->assertNotNull($order->fresh()->sale);
        $this->assertNull($order->fresh()->sale->pos_id);
    }

    public function test_record_bank_transfer_requires_bank_fields(): void
    {
        $order = $this->makeOrder();

        $this->authed()->postJson("/api/v1/openclaw/ecommerce-orders/{$order->id}/record-payment", [
            'payment_method' => 'bank_transfer',
            'store_id' => $this->store->id,
            // bank_id, bank_amount, reference_number deliberately missing
        ])->assertStatus(422);
    }

    public function test_record_bank_transfer_happy_path(): void
    {
        $order = $this->makeOrder();
        $bank = Bank::create([
            'bank_name' => 'BPI', 'account_name' => 'Main',
            'account_number' => '1234', 'account_type' => 1,
            'opening_balance' => 1000, 'balance' => 1000,
        ]);

        $response = $this->authed()->postJson("/api/v1/openclaw/ecommerce-orders/{$order->id}/record-payment", [
            'payment_method' => 'bank_transfer',
            'store_id' => $this->store->id,
            'bank_id' => $bank->id,
            'bank_amount' => 200,
            'reference_number' => 'TXN-001',
        ]);

        $response->assertStatus(200);
        $this->assertSame(Sale::PAYMENT_BANK_TRANSFER, (int) $order->fresh()->sale->payment_type);
        $this->assertSame(1200.00, (float) $bank->fresh()->balance);
    }

    public function test_mark_preparing_advances_paid_order(): void
    {
        $order = $this->makeOrder(EcommerceOrder::STATUS_PAID);

        $response = $this->authed()->postJson("/api/v1/openclaw/ecommerce-orders/{$order->id}/mark-preparing");

        $response->assertStatus(200);
        $this->assertSame(EcommerceOrder::STATUS_PREPARING, (int) $order->fresh()->status);
    }

    public function test_mark_picked_up_advances_preparing_order(): void
    {
        $order = $this->makeOrder(EcommerceOrder::STATUS_PREPARING);

        $response = $this->authed()->postJson("/api/v1/openclaw/ecommerce-orders/{$order->id}/mark-picked-up");

        $response->assertStatus(200);
        $this->assertSame(EcommerceOrder::STATUS_PICKED_UP, (int) $order->fresh()->status);
    }

    public function test_credit_payment_method_is_rejected(): void
    {
        $order = $this->makeOrder();

        $this->authed()->postJson("/api/v1/openclaw/ecommerce-orders/{$order->id}/record-payment", [
            'payment_method' => 'credit',
            'store_id' => $this->store->id,
        ])->assertStatus(422);
    }

    public function test_token_without_ability_cannot_verify(): void
    {
        // Recreate the token with only read access.
        ApiToken::query()->delete();
        $readOnly = ApiToken::generatePlainToken();
        ApiToken::create([
            'user_id' => $this->owner->user_id,
            'name' => 'Read-Only Bot',
            'token' => ApiToken::hashToken($readOnly),
            'abilities' => ['openclaw:read'],
        ]);

        $order = $this->makeOrder();

        $this->withHeader('Authorization', "Bearer {$readOnly}")
            ->postJson("/api/v1/openclaw/ecommerce-orders/{$order->id}/verify")
            ->assertStatus(403);
    }
}
