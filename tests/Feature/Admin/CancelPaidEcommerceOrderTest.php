<?php

namespace Tests\Feature\Admin;

use App\Models\Accounting\Bank;
use App\Models\Accounting\BankTransaction;
use App\Models\CustomerRelations\Customer;
use App\Models\Ecommerce\EcommerceOrder;
use App\Models\Employees\Role;
use App\Models\Pos\Sale;
use App\Models\Pos\SaleLine;
use App\Models\Products\Item;
use App\Models\Products\ItemStore;
use App\Models\Reports\AuditLog;
use App\Models\Settings\Store;
use App\Models\User;
use App\Services\CancelEcommerceOrderService;
use Database\Seeders\SmsTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers cancellation of orders in every state — including PAID,
 * PREPARING, and PICKED_UP — and the side-effects we promise: refund
 * Sale row, stock back into ItemStore, optional BankTransaction for
 * bank/e-wallet refunds, audit trail, and the customer-notification
 * SMS that rides on top of the status change.
 *
 * Tests call the service directly (rather than routing HTTP) so the
 * focus stays on the cancel pipeline invariants, not auth wiring.
 */
class CancelPaidEcommerceOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SmsTemplateSeeder::class);
    }

    private function admin(): User
    {
        $role = Role::factory()->admin()->create();

        return User::factory()->create(['role_id' => $role->id]);
    }

    private function customer(): Customer
    {
        return Customer::factory()->create([
            'phone' => '09171234567',
            'phone_verified_at' => now(),
            'sms_notifications_enabled' => true,
        ]);
    }

    /**
     * Seed an order + its paid Sale with one line, stocked at $stockBefore.
     * Lets each test exercise a state transition with predictable side-effects.
     */
    private function paidOrder(int $status, ?float $stockBefore = 25, ?int $bankId = null): array
    {
        $store = Store::factory()->create();
        $item = Item::factory()->create();
        $itemStore = ItemStore::factory()->create([
            'item_id' => $item->id,
            'store_id' => $store->id,
            'stock' => $stockBefore,
        ]);

        $customer = $this->customer();
        $order = EcommerceOrder::factory()->create([
            'customer_id' => $customer->id,
            'status' => $status,
            'total' => 500,
            'qty' => 5,
        ]);

        $sale = Sale::factory()->create([
            'son' => 'WEB-'.$order->reference,
            'total' => 500,
            'cash' => 500,
            'change' => 0,
            'store_id' => $store->id,
            'ecommerce_order_id' => $order->id,
            'pos_id' => null,
            'customer_id' => $customer->id,
            'sales_by' => 1,
            'user_id' => 1,
            'payment_type' => $bankId ? Sale::PAYMENT_EWALLET : Sale::PAYMENT_CASH,
            'bank_id' => $bankId,
            'bank_amount' => $bankId ? 500 : null,
        ]);

        SaleLine::factory()->create([
            'sales_id' => $sale->id,
            'item_id' => $item->id,
            'qty' => 5,
            'unit_qty' => 1,
            'price' => 100,
            'sub_total' => 500,
            'cost' => 70,
        ]);

        return compact('order', 'sale', 'item', 'store', 'itemStore', 'customer');
    }

    public function test_cancel_paid_order_writes_refund_sale_returns_stock_and_flags_cancelled(): void
    {
        $ctx = $this->paidOrder(EcommerceOrder::STATUS_PAID, stockBefore: 25);
        $admin = $this->admin();

        app(CancelEcommerceOrderService::class)->cancel($ctx['order'], $admin->id, 'Customer changed their mind');

        // Refund Sale exists, type=true, linked to original via sale_id,
        // ecommerce_order_id intentionally NULL (link flows via sale_id).
        $refund = Sale::where('sale_id', $ctx['sale']->id)->first();
        $this->assertNotNull($refund);
        $this->assertTrue((bool) $refund->type);
        $this->assertNull($refund->ecommerce_order_id);
        $this->assertSame('R-'.$ctx['sale']->son, $refund->son);

        // Refund SaleLine mirrors the original.
        $this->assertSame(1, SaleLine::where('sales_id', $refund->id)->count());

        // Stock returned via UpdateItemStocksJob (runs inline in test queue).
        $this->assertSame(30.0, (float) $ctx['itemStore']->fresh()->stock,
            'Stock should increment by 5 (original qty) after cancel.');

        // Order flipped to CANCELLED with audit fields stamped.
        $fresh = $ctx['order']->fresh();
        $this->assertSame((int) EcommerceOrder::STATUS_CANCELLED, (int) $fresh->status);
        $this->assertSame($admin->id, $fresh->cancelled_by);
        $this->assertNotNull($fresh->cancelled_at);
        $this->assertStringContainsString('Customer changed their mind', $fresh->note);

        // Status-change row records the transition with the reason,
        // and the SMS observer stamped sms_notified_at.
        $change = $fresh->statusChanges()->latest('id')->first();
        $this->assertSame((int) EcommerceOrder::STATUS_CANCELLED, (int) $change->to_status);
        $this->assertNotNull($change->sms_notified_at);
    }

    public function test_cancel_preparing_order_works_identically(): void
    {
        $ctx = $this->paidOrder(EcommerceOrder::STATUS_PREPARING, stockBefore: 25);
        $admin = $this->admin();

        app(CancelEcommerceOrderService::class)->cancel($ctx['order'], $admin->id);

        $this->assertSame(1, Sale::where('sale_id', $ctx['sale']->id)->count());
        $this->assertSame(30.0, (float) $ctx['itemStore']->fresh()->stock);
        $this->assertTrue($ctx['order']->fresh()->isCancelled());
    }

    public function test_refund_picked_up_order_returns_stock_too(): void
    {
        // Picked-up order — goods have physically left. The UX calls
        // it Refund, but the backend mechanics are identical to a
        // paid/preparing cancel.
        $ctx = $this->paidOrder(EcommerceOrder::STATUS_PICKED_UP, stockBefore: 25);
        $admin = $this->admin();

        app(CancelEcommerceOrderService::class)->cancel($ctx['order'], $admin->id, 'Customer returned the goods');

        $this->assertSame(30.0, (float) $ctx['itemStore']->fresh()->stock);
        $this->assertSame(1, Sale::where('sale_id', $ctx['sale']->id)->count());
        $this->assertStringContainsString('Customer returned the goods', $ctx['order']->fresh()->note);
    }

    public function test_cancelling_a_pending_order_skips_refund_sale(): void
    {
        // No sale exists for pending orders — service should just
        // flip status without touching Sale or ItemStore.
        $customer = $this->customer();
        $order = EcommerceOrder::factory()->create([
            'customer_id' => $customer->id,
            'status' => EcommerceOrder::STATUS_PENDING,
        ]);
        $admin = $this->admin();

        app(CancelEcommerceOrderService::class)->cancel($order, $admin->id);

        $this->assertTrue($order->fresh()->isCancelled());
        $this->assertSame(0, Sale::count(), 'No sale rows should exist when cancelling a pending order.');
    }

    public function test_double_cancel_throws_and_does_not_double_refund(): void
    {
        $ctx = $this->paidOrder(EcommerceOrder::STATUS_PAID, stockBefore: 25);
        $admin = $this->admin();
        $service = app(CancelEcommerceOrderService::class);

        $service->cancel($ctx['order'], $admin->id);

        // Second call must throw.
        try {
            $service->cancel($ctx['order'], $admin->id);
            $this->fail('Re-cancelling an already-cancelled order should throw.');
        } catch (\DomainException $e) {
            $this->assertStringContainsString('already cancelled', strtolower($e->getMessage()));
        }

        // Only one refund Sale + one audit row ever — idempotency on retry.
        $this->assertSame(1, Sale::where('sale_id', $ctx['sale']->id)->count());
        $this->assertSame(30.0, (float) $ctx['itemStore']->fresh()->stock);
        $this->assertSame(1, AuditLog::where('auditable_type', EcommerceOrder::class)
            ->where('auditable_id', $ctx['order']->id)
            ->where('event', 'order_cancelled')
            ->count(),
            'A rejected double-cancel must not write a second audit row.');
    }

    public function test_bank_paid_order_writes_negative_bank_transaction(): void
    {
        $bank = Bank::create([
            'bank_name' => 'Test Bank',
            'account_name' => 'Apex Test',
            'account_number' => '0000-1111-2222',
            'account_type' => Bank::TYPE_EWALLET,
            'opening_balance' => 5000,
            'balance' => 5000,
        ]);
        $ctx = $this->paidOrder(EcommerceOrder::STATUS_PAID, stockBefore: 25, bankId: $bank->id);
        $admin = $this->admin();

        app(CancelEcommerceOrderService::class)->cancel($ctx['order'], $admin->id);

        $tx = BankTransaction::where('bank_id', $bank->id)
            ->where('type', BankTransaction::TYPE_WITHDRAWAL)
            ->first();
        $this->assertNotNull($tx, 'Bank/e-wallet refunds must write a withdrawal BankTransaction.');
        $this->assertSame(500.0, (float) $tx->amount);
        $this->assertSame(4500.0, (float) $bank->fresh()->balance,
            'Bank balance should decrement by the refund amount.');
    }

    public function test_audit_log_captures_refund_details_for_paid_order(): void
    {
        $bank = Bank::create([
            'bank_name' => 'Test Bank',
            'account_name' => 'Apex Test',
            'account_number' => '0000-1111-2222',
            'account_type' => Bank::TYPE_EWALLET,
            'opening_balance' => 5000,
            'balance' => 5000,
        ]);
        $ctx = $this->paidOrder(EcommerceOrder::STATUS_PAID, stockBefore: 25, bankId: $bank->id);
        $admin = $this->admin();

        app(CancelEcommerceOrderService::class)->cancel($ctx['order'], $admin->id, 'Customer changed mind');

        $audit = AuditLog::where('auditable_type', EcommerceOrder::class)
            ->where('auditable_id', $ctx['order']->id)
            ->where('event', 'order_cancelled')
            ->first();

        $this->assertNotNull($audit, 'An audit row must be written for every successful cancellation.');
        $this->assertSame($admin->id, $audit->user_id);

        $payload = $audit->new_values;
        $this->assertSame((int) EcommerceOrder::STATUS_PAID, $payload['from_status']);
        $this->assertSame((int) EcommerceOrder::STATUS_CANCELLED, $payload['to_status']);
        $this->assertSame('Customer changed mind', $payload['reason']);
        $this->assertSame('R-'.$ctx['sale']->son, $payload['refund_sale_son']);
        $this->assertSame($ctx['sale']->id, $payload['original_sale_id']);
        $this->assertSame(500.0, (float) $payload['refund_total']);
        $this->assertSame(Sale::PAYMENT_EWALLET, $payload['payment_type']);
        $this->assertSame($bank->id, $payload['bank_refund']['bank_id']);
        $this->assertSame(500.0, (float) $payload['bank_refund']['amount']);
        $this->assertSame(1, $payload['lines_returned_to_stock']);
    }

    public function test_audit_log_for_pending_order_has_no_refund_fields(): void
    {
        // Pending orders have no sale, so the audit row records the
        // status flip but leaves refund_sale_id + bank_refund null.
        $customer = $this->customer();
        $order = EcommerceOrder::factory()->create([
            'customer_id' => $customer->id,
            'status' => EcommerceOrder::STATUS_PENDING,
        ]);
        $admin = $this->admin();

        app(CancelEcommerceOrderService::class)->cancel($order, $admin->id, 'Test cancel');

        $audit = AuditLog::where('auditable_id', $order->id)->where('event', 'order_cancelled')->first();
        $this->assertNotNull($audit);
        $payload = $audit->new_values;
        $this->assertNull($payload['refund_sale_id']);
        $this->assertNull($payload['original_sale_id']);
        $this->assertNull($payload['bank_refund']);
        $this->assertSame(0, $payload['lines_returned_to_stock']);
        $this->assertSame('Test cancel', $payload['reason']);
    }

    public function test_cancel_route_rejects_non_sales_admin(): void
    {
        // An admin without the `sls` role flag (e.g. settings-only or
        // POS-only roles) must not be able to refund orders. The HTTP
        // surface is the only place this can leak from, so test via
        // the route — not the service.
        $ctx = $this->paidOrder(EcommerceOrder::STATUS_PAID, stockBefore: 25);
        $nonSalesRole = Role::factory()->create(['sls' => false]);
        $nonSalesAdmin = User::factory()->create(['role_id' => $nonSalesRole->id]);

        $this->actingAs($nonSalesAdmin)
            ->post(route('ecommerce-orders.cancel', $ctx['order']), ['reason' => 'should not work'])
            ->assertForbidden();

        // Side-effects must not have happened.
        $this->assertSame(0, Sale::where('sale_id', $ctx['sale']->id)->count());
        $this->assertSame(25.0, (float) $ctx['itemStore']->fresh()->stock);
        $this->assertFalse($ctx['order']->fresh()->isCancelled());
    }

    public function test_cancel_route_allows_sales_admin(): void
    {
        $ctx = $this->paidOrder(EcommerceOrder::STATUS_PAID, stockBefore: 25);
        $admin = $this->admin(); // admin() factory state grants `sls`

        $this->actingAs($admin)
            ->post(route('ecommerce-orders.cancel', $ctx['order']), ['reason' => 'OK'])
            ->assertRedirect();

        $this->assertTrue($ctx['order']->fresh()->isCancelled());
        $this->assertSame(30.0, (float) $ctx['itemStore']->fresh()->stock);
    }

    public function test_cash_paid_order_skips_bank_transaction(): void
    {
        $ctx = $this->paidOrder(EcommerceOrder::STATUS_PAID, stockBefore: 25, bankId: null);
        $admin = $this->admin();

        app(CancelEcommerceOrderService::class)->cancel($ctx['order'], $admin->id);

        $this->assertSame(0, BankTransaction::count(),
            'Cash-paid refunds must NOT touch bank_transactions (no bank was involved).');
        // But stock + status should still be reversed.
        $this->assertSame(30.0, (float) $ctx['itemStore']->fresh()->stock);
        $this->assertTrue($ctx['order']->fresh()->isCancelled());
    }
}
