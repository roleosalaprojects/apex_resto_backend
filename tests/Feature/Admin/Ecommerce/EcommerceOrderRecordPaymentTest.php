<?php

namespace Tests\Feature\Admin\Ecommerce;

use App\Models\Accounting\Bank;
use App\Models\CustomerRelations\Customer;
use App\Models\Ecommerce\EcommerceOrder;
use App\Models\Ecommerce\EcommerceOrderLine;
use App\Models\Employees\Role;
use App\Models\Pos\Sale;
use App\Models\Pos\SalePaymentProof;
use App\Models\Products\Category;
use App\Models\Products\Item;
use App\Models\Products\ItemStore;
use App\Models\Settings\Store;
use App\Models\User;
use App\Services\ReceiptStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class EcommerceOrderRecordPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Store $store;

    protected Customer $customer;

    protected EcommerceOrder $order;

    protected Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::factory()->admin()->create();
        $this->admin = User::factory()->create([
            'role_id' => $role->id,
            'user_id' => 1,
        ]);

        $this->store = Store::factory()->create(['user_id' => $this->admin->user_id]);

        $this->customer = Customer::factory()->create([
            'user_id' => $this->admin->user_id,
            'points' => 0.01,
        ]);

        $category = Category::factory()->create();
        $this->item = Item::factory()->create([
            'price' => 100,
            'cost' => 60,
            'category_id' => $category->id,
            'user_id' => $this->admin->user_id,
            'creditable_to_points' => 1,
            'status' => true,
        ]);
        ItemStore::create([
            'item_id' => $this->item->id,
            'store_id' => $this->store->id,
            'stock' => 50,
        ]);

        $this->order = EcommerceOrder::create([
            'reference' => 'ECO-TEST'.random_int(10000, 99999),
            'customer_id' => $this->customer->id,
            'total' => 200,
            'qty' => 2,
            'status' => 0,
            'is_wholesale' => false,
        ]);
        EcommerceOrderLine::create([
            'ecommerce_order_id' => $this->order->id,
            'item_id' => $this->item->id,
            'item_name' => $this->item->name,
            'qty' => 2,
            'price' => 100,
            'sub_total' => 200,
        ]);
    }

    public function test_records_cash_payment_creates_sale_with_pos_id_null(): void
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('ecommerce-orders.record-payment', $this->order), [
            'payment_type' => Sale::PAYMENT_CASH,
            'store_id' => $this->store->id,
        ]);

        $response->assertRedirect(route('ecommerce-orders.show', $this->order->id));

        $this->assertDatabaseHas('sales', [
            'ecommerce_order_id' => $this->order->id,
            'pos_id' => null,
            'payment_type' => Sale::PAYMENT_CASH,
            'cheque_status' => null,
            'total' => 200,
        ]);
    }

    public function test_records_bank_transfer_creates_sale_and_bank_transaction(): void
    {
        $this->actingAs($this->admin);

        $bank = Bank::create([
            'bank_name' => 'BPI',
            'account_name' => 'Quick Baskets',
            'account_number' => '1234567890',
            'account_type' => 1,
            'opening_balance' => 1000,
            'balance' => 1000,
        ]);

        $response = $this->post(route('ecommerce-orders.record-payment', $this->order), [
            'payment_type' => Sale::PAYMENT_BANK_TRANSFER,
            'store_id' => $this->store->id,
            'bank_id' => $bank->id,
            'bank_amount' => 200,
            'reference_number' => 'BT-12345',
        ]);

        $response->assertRedirect();

        $sale = Sale::where('ecommerce_order_id', $this->order->id)->first();
        $this->assertNotNull($sale);
        $this->assertNull($sale->pos_id);
        $this->assertSame(Sale::PAYMENT_BANK_TRANSFER, (int) $sale->payment_type);

        // ProcessEWalletPaymentJob runs synchronously in tests
        $this->assertDatabaseHas('bank_transactions', [
            'bank_id' => $bank->id,
            'amount' => 200,
        ]);
        $this->assertSame(1200.00, (float) $bank->fresh()->balance);
    }

    public function test_records_cheque_payment_creates_pending_sale_no_bank_transaction(): void
    {
        $this->actingAs($this->admin);

        $bank = Bank::create([
            'bank_name' => 'BDO',
            'account_name' => 'Drawee',
            'account_number' => '999',
            'account_type' => 1,
            'opening_balance' => 500,
            'balance' => 500,
        ]);

        $this->post(route('ecommerce-orders.record-payment', $this->order), [
            'payment_type' => Sale::PAYMENT_CHEQUE,
            'store_id' => $this->store->id,
            'bank_id' => $bank->id,
            'bank_amount' => 200,
            'reference_number' => 'CHEQ-001',
        ])->assertRedirect();

        $sale = Sale::where('ecommerce_order_id', $this->order->id)->first();
        $this->assertNotNull($sale);
        $this->assertSame(Sale::CHEQUE_PENDING, $sale->cheque_status);

        // Pending cheque does NOT touch bank.balance
        $this->assertSame(500.00, (float) $bank->fresh()->balance);
        $this->assertDatabaseMissing('bank_transactions', ['bank_id' => $bank->id]);
    }

    public function test_refuses_when_order_already_paid(): void
    {
        $this->actingAs($this->admin);

        // First record creates the sale
        $this->post(route('ecommerce-orders.record-payment', $this->order), [
            'payment_type' => Sale::PAYMENT_CASH,
            'store_id' => $this->store->id,
        ])->assertRedirect();

        // Second attempt should fail
        $this->post(route('ecommerce-orders.record-payment', $this->order), [
            'payment_type' => Sale::PAYMENT_CASH,
            'store_id' => $this->store->id,
        ])->assertSessionHasErrors('order');

        $this->assertSame(1, Sale::where('ecommerce_order_id', $this->order->id)->count());
    }

    public function test_refuses_when_order_cancelled(): void
    {
        $this->actingAs($this->admin);

        $this->order->update(['status' => 2]);

        $this->post(route('ecommerce-orders.record-payment', $this->order), [
            'payment_type' => Sale::PAYMENT_CASH,
            'store_id' => $this->store->id,
        ])->assertSessionHasErrors('order');

        $this->assertDatabaseMissing('sales', ['ecommerce_order_id' => $this->order->id]);
    }

    public function test_validates_bank_required_for_non_cash_payment(): void
    {
        $this->actingAs($this->admin);

        $this->post(route('ecommerce-orders.record-payment', $this->order), [
            'payment_type' => Sale::PAYMENT_EWALLET,
            'store_id' => $this->store->id,
            // bank_id, reference_number, bank_amount intentionally missing
        ])->assertSessionHasErrors(['bank_id', 'reference_number', 'bank_amount']);
    }

    public function test_unauthorized_user_cannot_record_payment(): void
    {
        $role = Role::factory()->create(['sls' => false]);
        $other = User::factory()->create([
            'role_id' => $role->id,
            'user_id' => $this->admin->user_id,
        ]);

        $this->actingAs($other);

        $this->post(route('ecommerce-orders.record-payment', $this->order), [
            'payment_type' => Sale::PAYMENT_CASH,
            'store_id' => $this->store->id,
        ])->assertForbidden();
    }

    public function test_audit_logs_written_for_payment_recorded(): void
    {
        $this->actingAs($this->admin);

        $this->post(route('ecommerce-orders.record-payment', $this->order), [
            'payment_type' => Sale::PAYMENT_CASH,
            'store_id' => $this->store->id,
        ])->assertRedirect();

        $sale = Sale::where('ecommerce_order_id', $this->order->id)->first();

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => EcommerceOrder::class,
            'auditable_id' => $this->order->id,
            'event' => 'payment_recorded',
            'user_id' => $this->admin->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Sale::class,
            'auditable_id' => $sale->id,
            'event' => 'created_via_admin',
            'user_id' => $this->admin->id,
        ]);
    }

    public function test_records_payment_with_proof_photos(): void
    {
        $this->actingAs($this->admin);

        $proof1 = UploadedFile::fake()->image('gcash.png', 200, 200);
        $proof2 = UploadedFile::fake()->image('deposit.jpg', 200, 200);

        $this->post(route('ecommerce-orders.record-payment', $this->order), [
            'payment_type' => Sale::PAYMENT_CASH,
            'store_id' => $this->store->id,
            'proofs' => [$proof1, $proof2],
        ])->assertRedirect();

        $sale = Sale::where('ecommerce_order_id', $this->order->id)->first();

        $proofs = SalePaymentProof::where('sale_id', $sale->id)->get();
        $this->assertCount(2, $proofs);
        $this->assertSame($this->admin->id, (int) $proofs[0]->uploaded_by);

        foreach ($proofs as $proof) {
            $this->assertStringStartsWith(ReceiptStorage::DIR_SALE_PAYMENT_PROOFS, $proof->path);
            $this->assertFileExists(public_path($proof->path));

            // Cleanup so the test doesn't litter public/img/.
            @unlink(public_path($proof->path));
        }
    }

    public function test_proof_uploads_are_optional(): void
    {
        $this->actingAs($this->admin);

        $this->post(route('ecommerce-orders.record-payment', $this->order), [
            'payment_type' => Sale::PAYMENT_CASH,
            'store_id' => $this->store->id,
            // proofs deliberately absent
        ])->assertRedirect();

        $sale = Sale::where('ecommerce_order_id', $this->order->id)->first();
        $this->assertSame(0, SalePaymentProof::where('sale_id', $sale->id)->count());
    }

    public function test_rejects_non_image_proof_upload(): void
    {
        $this->actingAs($this->admin);

        $badFile = UploadedFile::fake()->create('contract.pdf', 100, 'application/pdf');

        $this->post(route('ecommerce-orders.record-payment', $this->order), [
            'payment_type' => Sale::PAYMENT_CASH,
            'store_id' => $this->store->id,
            'proofs' => [$badFile],
        ])->assertSessionHasErrors('proofs.0');

        $this->assertDatabaseMissing('sales', ['ecommerce_order_id' => $this->order->id]);
    }

    public function test_rejects_more_than_five_proof_photos(): void
    {
        $this->actingAs($this->admin);

        $photos = array_map(
            fn (int $i) => UploadedFile::fake()->image("proof-{$i}.png"),
            range(1, 6),
        );

        $this->post(route('ecommerce-orders.record-payment', $this->order), [
            'payment_type' => Sale::PAYMENT_CASH,
            'store_id' => $this->store->id,
            'proofs' => $photos,
        ])->assertSessionHasErrors('proofs');

        $this->assertDatabaseMissing('sales', ['ecommerce_order_id' => $this->order->id]);
    }

    public function test_recording_payment_advances_order_status_to_paid(): void
    {
        $this->actingAs($this->admin);

        $this->post(route('ecommerce-orders.record-payment', $this->order), [
            'payment_type' => Sale::PAYMENT_CASH,
            'store_id' => $this->store->id,
        ])->assertRedirect();

        $this->assertSame(EcommerceOrder::STATUS_PAID, (int) $this->order->fresh()->status);
        $this->assertSame($this->admin->id, (int) $this->order->fresh()->verified_by);
        $this->assertNotNull($this->order->fresh()->verified_at);
    }

    public function test_mark_preparing_advances_paid_order(): void
    {
        $this->actingAs($this->admin);

        $this->order->update(['status' => EcommerceOrder::STATUS_PAID]);

        $this->post(route('ecommerce-orders.mark-preparing', $this->order))->assertRedirect();

        $this->assertSame(EcommerceOrder::STATUS_PREPARING, (int) $this->order->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => EcommerceOrder::class,
            'auditable_id' => $this->order->id,
            'event' => 'marked_preparing',
        ]);
    }

    public function test_mark_preparing_refuses_non_paid_order(): void
    {
        $this->actingAs($this->admin);

        // Order is still pending (status=0) from setUp.
        $this->post(route('ecommerce-orders.mark-preparing', $this->order))
            ->assertSessionHas('error');

        $this->assertSame(EcommerceOrder::STATUS_PENDING, (int) $this->order->fresh()->status);
    }

    public function test_mark_picked_up_advances_preparing_order(): void
    {
        $this->actingAs($this->admin);

        $this->order->update(['status' => EcommerceOrder::STATUS_PREPARING]);

        $this->post(route('ecommerce-orders.mark-picked-up', $this->order))->assertRedirect();

        $this->assertSame(EcommerceOrder::STATUS_PICKED_UP, (int) $this->order->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => EcommerceOrder::class,
            'auditable_id' => $this->order->id,
            'event' => 'marked_picked_up',
        ]);
    }

    public function test_mark_picked_up_refuses_non_preparing_order(): void
    {
        $this->actingAs($this->admin);

        $this->order->update(['status' => EcommerceOrder::STATUS_PAID]);

        $this->post(route('ecommerce-orders.mark-picked-up', $this->order))
            ->assertSessionHas('error');

        $this->assertSame(EcommerceOrder::STATUS_PAID, (int) $this->order->fresh()->status);
    }

    public function test_recording_payment_writes_status_change_log(): void
    {
        $this->actingAs($this->admin);

        $this->post(route('ecommerce-orders.record-payment', $this->order), [
            'payment_type' => Sale::PAYMENT_CASH,
            'store_id' => $this->store->id,
        ])->assertRedirect();

        $change = \App\Models\Ecommerce\EcommerceOrderStatusChange::where('ecommerce_order_id', $this->order->id)
            ->where('to_status', EcommerceOrder::STATUS_PAID)
            ->first();

        $this->assertNotNull($change);
        $this->assertSame(EcommerceOrder::STATUS_PENDING, (int) $change->from_status);
        $this->assertSame($this->admin->id, (int) $change->changed_by);
        $this->assertNotNull($change->note);
    }

    public function test_mark_preparing_writes_status_change_log(): void
    {
        $this->actingAs($this->admin);

        $this->order->update(['status' => EcommerceOrder::STATUS_PAID]);

        $this->post(route('ecommerce-orders.mark-preparing', $this->order))->assertRedirect();

        $this->assertDatabaseHas('ecommerce_order_status_changes', [
            'ecommerce_order_id' => $this->order->id,
            'from_status' => EcommerceOrder::STATUS_PAID,
            'to_status' => EcommerceOrder::STATUS_PREPARING,
            'changed_by' => $this->admin->id,
        ]);
    }

    public function test_mark_picked_up_writes_status_change_log(): void
    {
        $this->actingAs($this->admin);

        $this->order->update(['status' => EcommerceOrder::STATUS_PREPARING]);

        $this->post(route('ecommerce-orders.mark-picked-up', $this->order))->assertRedirect();

        $this->assertDatabaseHas('ecommerce_order_status_changes', [
            'ecommerce_order_id' => $this->order->id,
            'from_status' => EcommerceOrder::STATUS_PREPARING,
            'to_status' => EcommerceOrder::STATUS_PICKED_UP,
            'changed_by' => $this->admin->id,
        ]);
    }

    public function test_mark_picked_up_persists_proof_photos(): void
    {
        $this->actingAs($this->admin);

        $this->order->update(['status' => EcommerceOrder::STATUS_PREPARING]);

        $proof1 = UploadedFile::fake()->image('handover.png', 200, 200);
        $proof2 = UploadedFile::fake()->image('signed-receipt.jpg', 200, 200);

        $this->post(route('ecommerce-orders.mark-picked-up', $this->order), [
            'proofs' => [$proof1, $proof2],
        ])->assertRedirect();

        $proofs = \App\Models\Ecommerce\EcommerceOrderPickupProof::where('ecommerce_order_id', $this->order->id)->get();
        $this->assertCount(2, $proofs);
        $this->assertSame($this->admin->id, (int) $proofs[0]->uploaded_by);

        foreach ($proofs as $proof) {
            $this->assertStringStartsWith(\App\Services\ReceiptStorage::DIR_ORDER_PICKUP_PROOFS, $proof->path);
            $this->assertFileExists(public_path($proof->path));
            @unlink(public_path($proof->path));
        }
    }

    public function test_mark_picked_up_proof_uploads_are_optional(): void
    {
        $this->actingAs($this->admin);

        $this->order->update(['status' => EcommerceOrder::STATUS_PREPARING]);

        $this->post(route('ecommerce-orders.mark-picked-up', $this->order))->assertRedirect();

        $this->assertSame(0, \App\Models\Ecommerce\EcommerceOrderPickupProof::where('ecommerce_order_id', $this->order->id)->count());
    }

    public function test_mark_picked_up_rejects_non_image_proof(): void
    {
        $this->actingAs($this->admin);

        $this->order->update(['status' => EcommerceOrder::STATUS_PREPARING]);
        $bad = UploadedFile::fake()->create('paperwork.pdf', 100, 'application/pdf');

        $this->post(route('ecommerce-orders.mark-picked-up', $this->order), [
            'proofs' => [$bad],
        ])->assertSessionHasErrors('proofs.0');

        // Status should NOT have advanced when validation failed.
        $this->assertSame(EcommerceOrder::STATUS_PREPARING, (int) $this->order->fresh()->status);
    }
}
