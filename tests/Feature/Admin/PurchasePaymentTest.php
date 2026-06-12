<?php

namespace Tests\Feature\Admin;

use App\Models\Accounting\Bank;
use App\Models\Employees\Role;
use App\Models\InventoryManagement\Purchase;
use App\Models\InventoryManagement\PurchasePayment;
use App\Models\InventoryManagement\Supplier;
use App\Models\Settings\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchasePaymentTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Role $role;

    protected Store $store;

    protected Supplier $supplier;

    protected Bank $bank;

    protected function setUp(): void
    {
        parent::setUp();

        $this->role = Role::factory()->admin()->create();
        $this->user = User::factory()->create([
            'role_id' => $this->role->id,
            'user_id' => 1,
        ]);
        $this->store = Store::factory()->create(['user_id' => 1]);
        $this->supplier = Supplier::factory()->create(['user_id' => 1]);
        $this->bank = Bank::create([
            'bank_name' => 'Test Bank',
            'account_name' => 'Test Account',
            'account_number' => '1234567890',
            'account_type' => Bank::TYPE_SAVINGS,
            'opening_balance' => 100000,
            'balance' => 100000,
        ]);
    }

    public function test_purchase_with_payments_has_correct_attributes(): void
    {
        $purchase = Purchase::factory()->create([
            'user_id' => 1,
            'supplier_id' => $this->supplier->id,
            'store_id' => $this->store->id,
            'created_by' => $this->user->id,
            'total' => 10000,
            'amount_paid' => 0,
            'payment_status' => Purchase::PAYMENT_UNPAID,
            'approval_status' => Purchase::APPROVAL_APPROVED,
        ]);

        // Verify the purchase has the correct properties for payment
        $this->assertTrue($purchase->isApproved());
        $this->assertFalse($purchase->isFullyPaid());
        $this->assertTrue($purchase->canAcceptPayment());
        $this->assertEquals(10000, $purchase->remaining_balance);
    }

    public function test_can_record_payment_for_approved_purchase(): void
    {
        $purchase = Purchase::factory()->create([
            'user_id' => 1,
            'supplier_id' => $this->supplier->id,
            'store_id' => $this->store->id,
            'created_by' => $this->user->id,
            'total' => 10000,
            'amount_paid' => 0,
            'payment_status' => Purchase::PAYMENT_UNPAID,
            'approval_status' => Purchase::APPROVAL_APPROVED,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson(route('purchase.record-payment', $purchase->id), [
                'bank_id' => $this->bank->id,
                'amount' => 5000,
                'payment_date' => now()->format('Y-m-d'),
                'payment_method' => PurchasePayment::METHOD_CASH,
            ]);

        // §C1 — recordPayment returns 201 Created across all three
        // API surfaces (admin / OpenClaw / mobile) since it inserts a
        // PurchasePayment + BankTransaction row.
        $response->assertStatus(201);
        $response->assertJson([
            'success' => true,
        ]);

        // Verify purchase was updated
        $purchase->refresh();
        $this->assertEquals(5000, $purchase->amount_paid);
        $this->assertEquals(Purchase::PAYMENT_PARTIAL, $purchase->payment_status);

        // Verify bank balance decreased
        $this->bank->refresh();
        $this->assertEquals(95000, $this->bank->balance);

        // Verify payment record was created
        $this->assertDatabaseHas('purchase_payments', [
            'purchase_id' => $purchase->id,
            'amount' => 5000,
        ]);
    }

    public function test_can_fully_pay_purchase(): void
    {
        $purchase = Purchase::factory()->create([
            'user_id' => 1,
            'supplier_id' => $this->supplier->id,
            'store_id' => $this->store->id,
            'created_by' => $this->user->id,
            'total' => 10000,
            'amount_paid' => 0,
            'payment_status' => Purchase::PAYMENT_UNPAID,
            'approval_status' => Purchase::APPROVAL_APPROVED,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson(route('purchase.record-payment', $purchase->id), [
                'bank_id' => $this->bank->id,
                'amount' => 10000,
                'payment_date' => now()->format('Y-m-d'),
                'payment_method' => PurchasePayment::METHOD_BANK_TRANSFER,
            ]);

        $response->assertStatus(201);

        $purchase->refresh();
        $this->assertEquals(10000, $purchase->amount_paid);
        $this->assertEquals(Purchase::PAYMENT_PAID, $purchase->payment_status);
    }

    public function test_cannot_pay_more_than_remaining_balance(): void
    {
        $purchase = Purchase::factory()->create([
            'user_id' => 1,
            'supplier_id' => $this->supplier->id,
            'store_id' => $this->store->id,
            'created_by' => $this->user->id,
            'total' => 10000,
            'amount_paid' => 5000,
            'payment_status' => Purchase::PAYMENT_PARTIAL,
            'approval_status' => Purchase::APPROVAL_APPROVED,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson(route('purchase.record-payment', $purchase->id), [
                'bank_id' => $this->bank->id,
                'amount' => 10000, // More than remaining balance of 5000
                'payment_date' => now()->format('Y-m-d'),
                'payment_method' => PurchasePayment::METHOD_CASH,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['amount']);
    }

    public function test_cannot_pay_more_than_bank_balance(): void
    {
        // Create bank with low balance
        $lowBalanceBank = Bank::create([
            'bank_name' => 'Low Balance Bank',
            'account_name' => 'Low Account',
            'account_number' => '0987654321',
            'account_type' => Bank::TYPE_SAVINGS,
            'opening_balance' => 1000,
            'balance' => 1000,
        ]);

        $purchase = Purchase::factory()->create([
            'user_id' => 1,
            'supplier_id' => $this->supplier->id,
            'store_id' => $this->store->id,
            'created_by' => $this->user->id,
            'total' => 10000,
            'amount_paid' => 0,
            'payment_status' => Purchase::PAYMENT_UNPAID,
            'approval_status' => Purchase::APPROVAL_APPROVED,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson(route('purchase.record-payment', $purchase->id), [
                'bank_id' => $lowBalanceBank->id,
                'amount' => 5000, // More than bank balance of 1000
                'payment_date' => now()->format('Y-m-d'),
                'payment_method' => PurchasePayment::METHOD_CASH,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['bank_id']);
    }

    public function test_cannot_pay_unapproved_purchase(): void
    {
        $purchase = Purchase::factory()->create([
            'user_id' => 1,
            'supplier_id' => $this->supplier->id,
            'store_id' => $this->store->id,
            'created_by' => $this->user->id,
            'total' => 10000,
            'amount_paid' => 0,
            'payment_status' => Purchase::PAYMENT_UNPAID,
            'approval_status' => Purchase::APPROVAL_PENDING, // Not approved
        ]);

        $response = $this->actingAs($this->user)
            ->postJson(route('purchase.record-payment', $purchase->id), [
                'bank_id' => $this->bank->id,
                'amount' => 5000,
                'payment_date' => now()->format('Y-m-d'),
                'payment_method' => PurchasePayment::METHOD_CASH,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['purchase']);
    }

    public function test_check_number_required_for_check_payments(): void
    {
        $purchase = Purchase::factory()->create([
            'user_id' => 1,
            'supplier_id' => $this->supplier->id,
            'store_id' => $this->store->id,
            'created_by' => $this->user->id,
            'total' => 10000,
            'amount_paid' => 0,
            'payment_status' => Purchase::PAYMENT_UNPAID,
            'approval_status' => Purchase::APPROVAL_APPROVED,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson(route('purchase.record-payment', $purchase->id), [
                'bank_id' => $this->bank->id,
                'amount' => 5000,
                'payment_date' => now()->format('Y-m-d'),
                'payment_method' => PurchasePayment::METHOD_CHECK,
                // Missing check_number
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['check_number']);
    }

    public function test_can_pay_with_check_when_check_number_provided(): void
    {
        $purchase = Purchase::factory()->create([
            'user_id' => 1,
            'supplier_id' => $this->supplier->id,
            'store_id' => $this->store->id,
            'created_by' => $this->user->id,
            'total' => 10000,
            'amount_paid' => 0,
            'payment_status' => Purchase::PAYMENT_UNPAID,
            'approval_status' => Purchase::APPROVAL_APPROVED,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson(route('purchase.record-payment', $purchase->id), [
                'bank_id' => $this->bank->id,
                'amount' => 5000,
                'payment_date' => now()->format('Y-m-d'),
                'payment_method' => PurchasePayment::METHOD_CHECK,
                'check_number' => 'CHK-123456',
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('purchase_payments', [
            'purchase_id' => $purchase->id,
            'check_number' => 'CHK-123456',
            'payment_method' => PurchasePayment::METHOD_CHECK,
        ]);
    }

    public function test_can_get_payment_history(): void
    {
        $purchase = Purchase::factory()->create([
            'user_id' => 1,
            'supplier_id' => $this->supplier->id,
            'store_id' => $this->store->id,
            'created_by' => $this->user->id,
            'total' => 10000,
            'amount_paid' => 0,
            'payment_status' => Purchase::PAYMENT_UNPAID,
            'approval_status' => Purchase::APPROVAL_APPROVED,
        ]);

        // Record a payment through the controller to create proper records
        $this->actingAs($this->user)
            ->postJson(route('purchase.record-payment', $purchase->id), [
                'bank_id' => $this->bank->id,
                'amount' => 5000,
                'payment_date' => now()->format('Y-m-d'),
                'payment_method' => PurchasePayment::METHOD_CASH,
            ]);

        $response = $this->actingAs($this->user)
            ->getJson(route('purchase.payments', $purchase->id));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'reference_number',
                    'payment_date',
                    'payment_method',
                    'bank',
                    'amount',
                ],
            ],
        ]);

        $this->assertCount(1, $response->json('data'));
    }

    public function test_fully_paid_purchase_cannot_accept_more_payments(): void
    {
        $purchase = Purchase::factory()->create([
            'user_id' => 1,
            'supplier_id' => $this->supplier->id,
            'store_id' => $this->store->id,
            'created_by' => $this->user->id,
            'total' => 10000,
            'amount_paid' => 10000,
            'payment_status' => Purchase::PAYMENT_PAID,
            'approval_status' => Purchase::APPROVAL_APPROVED,
        ]);

        // Verify the purchase cannot accept more payments
        $this->assertTrue($purchase->isFullyPaid());
        $this->assertFalse($purchase->canAcceptPayment());
        $this->assertEquals(0, $purchase->remaining_balance);

        // Attempt to record a payment should fail
        $response = $this->actingAs($this->user)
            ->postJson(route('purchase.record-payment', $purchase->id), [
                'bank_id' => $this->bank->id,
                'amount' => 1000,
                'payment_date' => now()->format('Y-m-d'),
                'payment_method' => PurchasePayment::METHOD_CASH,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['purchase']);
    }
}
