<?php

namespace Tests\Feature\API\v1\mobile;

use App\Models\Accounting\Bank;
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
use Illuminate\Http\UploadedFile;
use Laravel\Passport\Passport;
use Tests\TestCase;

class MobileEcommerceOrdersTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Customer $customer;

    protected Store $store;

    protected Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::factory()->admin()->create();
        $this->user = User::factory()->create(['role_id' => $role->id]);
        $this->user->forceFill(['user_id' => $this->user->id])->save();

        $this->customer = Customer::factory()->create([
            'user_id' => $this->user->user_id,
            'points' => 0.01,
        ]);

        $this->store = Store::factory()->create(['user_id' => $this->user->user_id]);

        $this->item = Item::factory()->create([
            'price' => 100,
            'cost' => 60,
            'category_id' => Category::factory()->create()->id,
            'user_id' => $this->user->user_id,
            'creditable_to_points' => 1,
            'status' => true,
        ]);
        ItemStore::create([
            'item_id' => $this->item->id,
            'store_id' => $this->store->id,
            'stock' => 50,
        ]);
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

    public function test_pending_feed_returns_count_and_orders(): void
    {
        $this->makeOrder();
        $this->makeOrder(EcommerceOrder::STATUS_PAID);

        Passport::actingAs($this->user);

        $response = $this->getJson('/api/v1/mobile/ecommerce-orders/pending');

        $response->assertStatus(200)
            ->assertJsonPath('data.count', 1);
    }

    public function test_show_returns_status_history_and_labels(): void
    {
        $order = $this->makeOrder(EcommerceOrder::STATUS_VERIFIED);
        $order->logStatusChange(null, EcommerceOrder::STATUS_PENDING, null);
        $order->logStatusChange(EcommerceOrder::STATUS_PENDING, EcommerceOrder::STATUS_VERIFIED, $this->user->id);

        Passport::actingAs($this->user);

        $response = $this->getJson("/api/v1/mobile/ecommerce-orders/{$order->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.status_label', 'Verified')
            ->assertJsonPath('data.status_badge_variant', 'primary')
            ->assertJsonCount(2, 'data.status_history');
    }

    public function test_verify_advances_pending_order(): void
    {
        $order = $this->makeOrder();
        Passport::actingAs($this->user);

        $this->postJson("/api/v1/mobile/ecommerce-orders/{$order->id}/verify")
            ->assertStatus(200);

        $this->assertSame(EcommerceOrder::STATUS_VERIFIED, (int) $order->fresh()->status);
        $this->assertDatabaseHas('ecommerce_order_status_changes', [
            'ecommerce_order_id' => $order->id,
            'to_status' => EcommerceOrder::STATUS_VERIFIED,
            'changed_by' => $this->user->id,
        ]);
    }

    public function test_record_cash_payment_advances_to_paid(): void
    {
        $order = $this->makeOrder();
        Passport::actingAs($this->user);

        $this->postJson("/api/v1/mobile/ecommerce-orders/{$order->id}/record-payment", [
            'payment_method' => 'cash',
            'store_id' => $this->store->id,
        ])->assertStatus(200);

        $this->assertSame(EcommerceOrder::STATUS_PAID, (int) $order->fresh()->status);
        $this->assertNotNull($order->fresh()->sale);
    }

    public function test_record_bank_transfer_with_proof_photo(): void
    {
        $order = $this->makeOrder();
        $bank = Bank::create([
            'bank_name' => 'BPI', 'account_name' => 'Main',
            'account_number' => '1234', 'account_type' => 1,
            'opening_balance' => 0, 'balance' => 0,
        ]);
        Passport::actingAs($this->user);

        $proof = UploadedFile::fake()->image('screenshot.png');

        $response = $this->postJson("/api/v1/mobile/ecommerce-orders/{$order->id}/record-payment", [
            'payment_method' => 'bank_transfer',
            'store_id' => $this->store->id,
            'bank_id' => $bank->id,
            'bank_amount' => 200,
            'reference_number' => 'TXN-001',
            'proofs' => [$proof],
        ]);

        $response->assertStatus(200);
        $sale = $order->fresh()->sale;
        $this->assertSame(Sale::PAYMENT_BANK_TRANSFER, (int) $sale->payment_type);
        $this->assertSame(1, $sale->paymentProofs()->count());
        @unlink(public_path($sale->paymentProofs()->first()->path));
    }

    public function test_mark_picked_up_with_proof_photo(): void
    {
        $order = $this->makeOrder(EcommerceOrder::STATUS_PREPARING);
        Passport::actingAs($this->user);

        $proof = UploadedFile::fake()->image('handover.jpg');

        $response = $this->postJson("/api/v1/mobile/ecommerce-orders/{$order->id}/mark-picked-up", [
            'proofs' => [$proof],
        ]);

        $response->assertStatus(200);
        $this->assertSame(EcommerceOrder::STATUS_PICKED_UP, (int) $order->fresh()->status);
        $this->assertSame(1, $order->fresh()->pickupProofs()->count());
        @unlink(public_path($order->fresh()->pickupProofs()->first()->path));
    }

    public function test_mark_preparing_refuses_non_paid_order(): void
    {
        $order = $this->makeOrder(EcommerceOrder::STATUS_VERIFIED);
        Passport::actingAs($this->user);

        $this->postJson("/api/v1/mobile/ecommerce-orders/{$order->id}/mark-preparing")
            ->assertStatus(422);
    }

    public function test_show_returns_payment_intent_label_and_int(): void
    {
        $order = $this->makeOrder();
        $order->update(['payment_intent' => EcommerceOrder::PAYMENT_INTENT_GCASH]);
        Passport::actingAs($this->user);

        $this->getJson("/api/v1/mobile/ecommerce-orders/{$order->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.payment_intent_label', 'GCash / E-Wallet')
            ->assertJsonPath('data.intended_sale_payment_type', \App\Models\Pos\Sale::PAYMENT_EWALLET);
    }
}
