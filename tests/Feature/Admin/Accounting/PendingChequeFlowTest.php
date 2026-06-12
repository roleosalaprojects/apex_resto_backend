<?php

namespace Tests\Feature\Admin\Accounting;

use App\Models\Accounting\Bank;
use App\Models\CustomerRelations\Customer;
use App\Models\Employees\Role;
use App\Models\Pos\Sale;
use App\Models\Settings\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PendingChequeFlowTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Store $store;

    protected Bank $bank;

    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::factory()->admin()->create();
        $this->admin = User::factory()->create([
            'role_id' => $role->id,
            'user_id' => 1,
        ]);

        $this->store = Store::factory()->create(['user_id' => $this->admin->user_id]);

        $this->bank = Bank::create([
            'bank_name' => 'BPI',
            'account_name' => 'Quick Baskets',
            'account_number' => '1234567890',
            'account_type' => 1,
            'opening_balance' => 5000,
            'balance' => 5000,
        ]);

        $this->customer = Customer::factory()->create([
            'user_id' => $this->admin->user_id,
            'credit_balance' => 0,
        ]);
    }

    private function makePendingCheque(float $total = 500): Sale
    {
        return Sale::create([
            'counter' => 0,
            'son' => 'WEB-ECO-CHEQ'.random_int(10000, 99999),
            'payment_type' => Sale::PAYMENT_CHEQUE,
            'cheque_status' => Sale::CHEQUE_PENDING,
            'reference_number' => 'CHQ-001',
            'bank_amount' => $total,
            'bank_id' => $this->bank->id,
            'total' => $total,
            'cash' => 0,
            'change' => 0,
            'type' => false,
            'sales_by' => $this->admin->id,
            'pos_id' => null,
            'store_id' => $this->store->id,
            'user_id' => $this->admin->user_id,
            'profit' => 100,
            'vatable' => 0, 'vat' => 0, 'vat_exempt' => 0, 'zero_rated' => 0, 'non_vat' => 0,
            'discount' => 0, 'cancelled' => false, 'sale_id' => 0, 'sale_type' => false,
            'sc_discount' => 0, 'pwd_discount' => 0, 'sp_discount' => 0,
            'naac_discount' => 0, 'vat_special_discounts' => 0, 'special_discount_type' => 0,
            'customer_id' => $this->customer->id,
            'acquired_points' => 0, 'points_used' => 0,
        ]);
    }

    public function test_mark_cleared_creates_bank_transaction_and_bumps_balance(): void
    {
        $this->actingAs($this->admin);

        $sale = $this->makePendingCheque(500);

        $this->post(route('pending-cheques.clear', $sale), [
            'cleared_date' => now()->toDateString(),
            'clearing_reference' => 'BPI-DEPOSIT-001',
        ])->assertRedirect();

        $this->assertSame(Sale::CHEQUE_CLEARED, $sale->fresh()->cheque_status);
        $this->assertSame(5500.00, (float) $this->bank->fresh()->balance);

        $this->assertDatabaseHas('bank_transactions', [
            'bank_id' => $this->bank->id,
            'amount' => 500,
            'reference_number' => 'BPI-DEPOSIT-001',
        ]);
    }

    public function test_mark_cleared_writes_audit_log(): void
    {
        $this->actingAs($this->admin);

        $sale = $this->makePendingCheque();

        $this->post(route('pending-cheques.clear', $sale), [
            'cleared_date' => now()->toDateString(),
        ])->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Sale::class,
            'auditable_id' => $sale->id,
            'event' => 'cheque_cleared',
            'user_id' => $this->admin->id,
        ]);
    }

    public function test_mark_bounced_charges_customer_credit(): void
    {
        $this->actingAs($this->admin);

        $sale = $this->makePendingCheque(750);
        $balanceBefore = (float) $this->bank->fresh()->balance;

        $this->post(route('pending-cheques.bounce', $sale), [
            'bounce_note' => 'Insufficient funds',
        ])->assertRedirect();

        $this->assertSame(Sale::CHEQUE_BOUNCED, $sale->fresh()->cheque_status);

        // Bank balance UNCHANGED — the cheque never cleared so no money moved.
        $this->assertSame($balanceBefore, (float) $this->bank->fresh()->balance);

        // Customer charged via credit ledger.
        $this->assertSame(750.00, (float) $this->customer->fresh()->credit_balance);
        $this->assertDatabaseHas('customer_credit_transactions', [
            'customer_id' => $this->customer->id,
            'type' => 'charge',
            'amount' => 750,
            'reference_type' => 'cheque_bounce',
            'reference_id' => $sale->id,
        ]);
    }

    public function test_mark_bounced_writes_audit_log(): void
    {
        $this->actingAs($this->admin);

        $sale = $this->makePendingCheque();

        $this->post(route('pending-cheques.bounce', $sale))->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => Sale::class,
            'auditable_id' => $sale->id,
            'event' => 'cheque_bounced',
            'user_id' => $this->admin->id,
        ]);
    }

    public function test_cleared_cheque_cannot_be_re_cleared(): void
    {
        $this->actingAs($this->admin);

        $sale = $this->makePendingCheque();
        $sale->update(['cheque_status' => Sale::CHEQUE_CLEARED]);

        $this->post(route('pending-cheques.clear', $sale), [
            'cleared_date' => now()->toDateString(),
        ])->assertSessionHasErrors('sale');
    }

    public function test_bounced_cheque_cannot_be_cleared(): void
    {
        $this->actingAs($this->admin);

        $sale = $this->makePendingCheque();
        $sale->update(['cheque_status' => Sale::CHEQUE_BOUNCED]);

        $this->post(route('pending-cheques.clear', $sale), [
            'cleared_date' => now()->toDateString(),
        ])->assertSessionHasErrors('sale');
    }
}
