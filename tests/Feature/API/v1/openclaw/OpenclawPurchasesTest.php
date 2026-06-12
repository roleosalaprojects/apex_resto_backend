<?php

namespace Tests\Feature\API\v1\openclaw;

use App\Models\Accounting\Bank;
use App\Models\Accounting\BankTransaction;
use App\Models\Accounting\Expense;
use App\Models\Accounting\ExpenseCategory;
use App\Models\ApiToken;
use App\Models\Employees\Role;
use App\Models\InventoryManagement\Purchase;
use App\Models\InventoryManagement\PurchasePayment;
use App\Models\InventoryManagement\Supplier;
use App\Models\Settings\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpenclawPurchasesTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected User $otherOwner;

    protected Supplier $supplier;

    protected Store $store;

    protected Bank $bank;

    protected string $approveToken;

    protected string $payToken;

    protected string $readToken;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::factory()->admin()->create();

        $this->owner = User::factory()->create(['role_id' => $role->id]);
        $this->owner->forceFill(['user_id' => $this->owner->id])->save();

        $this->otherOwner = User::factory()->create(['role_id' => $role->id]);
        $this->otherOwner->forceFill(['user_id' => $this->otherOwner->id])->save();

        $this->supplier = Supplier::factory()->create(['user_id' => $this->owner->user_id, 'name' => 'SK Rice Trading']);
        $this->store = Store::factory()->create(['user_id' => $this->owner->user_id]);
        $this->bank = Bank::create([
            'bank_name' => 'BDO',
            'account_name' => 'Leteres',
            'account_number' => '041078001670',
            'account_type' => Bank::TYPE_CHECKING,
            'opening_balance' => 1000000,
            'balance' => 1000000,
        ]);

        $this->approveToken = $this->mintToken(['openclaw:read', 'openclaw:purchases:approve']);
        $this->payToken = $this->mintToken(['openclaw:read', 'openclaw:purchases:approve', 'openclaw:purchases:pay']);
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

    private function makePO(array $overrides = []): Purchase
    {
        return Purchase::factory()->create(array_merge([
            'user_id' => $this->owner->user_id,
            'supplier_id' => $this->supplier->id,
            'store_id' => $this->store->id,
            'total' => 459500,
            'amount_paid' => 0,
            'approval_status' => Purchase::APPROVAL_PENDING,
            'payment_status' => Purchase::PAYMENT_UNPAID,
            'created_by' => $this->owner->id,
        ], $overrides));
    }

    // -------- list / show --------

    public function test_index_lists_pos_for_tenant_only(): void
    {
        $mine = $this->makePO();
        $foreign = Purchase::factory()->create([
            'user_id' => $this->otherOwner->user_id,
            'supplier_id' => $this->supplier->id,
            'store_id' => $this->store->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->readToken}")
            ->getJson('/api/v1/openclaw/purchases');

        $response->assertStatus(200);
        $ids = collect($response->json('data.purchase_orders'))->pluck('id')->all();
        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($foreign->id, $ids);
    }

    public function test_pending_approvals_returns_only_pending(): void
    {
        $pending = $this->makePO();
        $approved = $this->makePO(['approval_status' => Purchase::APPROVAL_APPROVED]);
        $draft = $this->makePO(['approval_status' => Purchase::APPROVAL_DRAFT]);

        $response = $this->withHeader('Authorization', "Bearer {$this->readToken}")
            ->getJson('/api/v1/openclaw/purchases/pending-approvals');

        $response->assertStatus(200)->assertJsonPath('data.count', 1);
        $ids = collect($response->json('data.purchase_orders'))->pluck('id')->all();
        $this->assertContains($pending->id, $ids);
        $this->assertNotContains($approved->id, $ids);
        $this->assertNotContains($draft->id, $ids);
    }

    public function test_show_404s_for_other_tenant_po(): void
    {
        $foreign = Purchase::factory()->create([
            'user_id' => $this->otherOwner->user_id,
            'supplier_id' => $this->supplier->id,
            'store_id' => $this->store->id,
        ]);

        $this->withHeader('Authorization', "Bearer {$this->readToken}")
            ->getJson("/api/v1/openclaw/purchases/{$foreign->id}")
            ->assertStatus(404);
    }

    // -------- approve / reject --------

    public function test_approve_pending_po_succeeds_and_records_approval_row(): void
    {
        $po = $this->makePO();

        $response = $this->withHeader('Authorization', "Bearer {$this->approveToken}")
            ->postJson("/api/v1/openclaw/purchases/{$po->id}/approve");

        $response->assertStatus(200)
            ->assertJsonPath('data.purchase_order.approval_status', Purchase::APPROVAL_APPROVED)
            ->assertJsonPath('data.purchase_order.approval_status_name', 'Approved');

        $this->assertSame(Purchase::APPROVAL_APPROVED, $po->fresh()->approval_status);
        $this->assertSame(1, $po->approvals()->where('status', 'approved')->count());
    }

    public function test_approve_works_even_when_owner_created_the_po_no_self_approval_block(): void
    {
        // Mobile API blocks self-approval. The OpenClaw bot acts on behalf of
        // the owner and should not be subject to that rule.
        $po = $this->makePO(['created_by' => $this->owner->id]);

        $this->withHeader('Authorization', "Bearer {$this->approveToken}")
            ->postJson("/api/v1/openclaw/purchases/{$po->id}/approve")
            ->assertStatus(200);
    }

    public function test_approve_returns_409_when_not_pending(): void
    {
        $po = $this->makePO(['approval_status' => Purchase::APPROVAL_APPROVED]);

        $this->withHeader('Authorization', "Bearer {$this->approveToken}")
            ->postJson("/api/v1/openclaw/purchases/{$po->id}/approve")
            ->assertStatus(409);
    }

    public function test_approve_requires_purchases_approve_ability(): void
    {
        $po = $this->makePO();

        $this->withHeader('Authorization', "Bearer {$this->readToken}")
            ->postJson("/api/v1/openclaw/purchases/{$po->id}/approve")
            ->assertStatus(403)
            ->assertJsonPath('message', 'This token is missing the required ability: openclaw:purchases:approve.');
    }

    public function test_reject_requires_a_comment(): void
    {
        $po = $this->makePO();

        $this->withHeader('Authorization', "Bearer {$this->approveToken}")
            ->postJson("/api/v1/openclaw/purchases/{$po->id}/reject", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('rejection_comment');
    }

    public function test_reject_with_comment_marks_rejected(): void
    {
        $po = $this->makePO();

        $this->withHeader('Authorization', "Bearer {$this->approveToken}")
            ->postJson("/api/v1/openclaw/purchases/{$po->id}/reject", [
                'rejection_comment' => 'Wrong supplier on the order',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.purchase_order.approval_status_name', 'Rejected');

        $this->assertSame(Purchase::APPROVAL_REJECTED, $po->fresh()->approval_status);
    }

    public function test_approve_404s_for_other_tenant_po(): void
    {
        $foreign = Purchase::factory()->create([
            'user_id' => $this->otherOwner->user_id,
            'supplier_id' => $this->supplier->id,
            'store_id' => $this->store->id,
            'approval_status' => Purchase::APPROVAL_PENDING,
        ]);

        $this->withHeader('Authorization', "Bearer {$this->approveToken}")
            ->postJson("/api/v1/openclaw/purchases/{$foreign->id}/approve")
            ->assertStatus(404);
    }

    // -------- pay --------

    public function test_pay_records_partial_payment_and_updates_bank_balance(): void
    {
        $po = $this->makePO([
            'total' => 459500,
            'amount_paid' => 0,
            'approval_status' => Purchase::APPROVAL_APPROVED,
            'payment_status' => Purchase::PAYMENT_UNPAID,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->payToken}")
            ->postJson("/api/v1/openclaw/purchases/{$po->id}/pay", [
                'amount' => 200000,
                'bank_id' => $this->bank->id,
                'payment_method' => 'check',
                'check_number' => '137286',
                'notes' => 'Partial payment',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.payment.payment_method_name', 'Check')
            ->assertJsonPath('data.payment.check_number', '137286');

        $po->refresh();
        $this->assertEqualsWithDelta(200000.0, (float) $po->amount_paid, 0.001);
        $this->assertSame(Purchase::PAYMENT_PARTIAL, $po->payment_status);

        $this->assertEqualsWithDelta(800000.0, (float) $this->bank->fresh()->balance, 0.001);

        // A payment row + bank_transaction row exist and are linked.
        $payment = PurchasePayment::query()->where('purchase_id', $po->id)->first();
        $this->assertNotNull($payment->bank_transaction_id);
        $this->assertEqualsWithDelta(200000.0, (float) BankTransaction::find($payment->bank_transaction_id)->amount, 0.001);
    }

    public function test_pay_full_amount_marks_po_paid(): void
    {
        $po = $this->makePO([
            'total' => 459500,
            'amount_paid' => 0,
            'approval_status' => Purchase::APPROVAL_APPROVED,
        ]);

        $this->withHeader('Authorization', "Bearer {$this->payToken}")
            ->postJson("/api/v1/openclaw/purchases/{$po->id}/pay", [
                'amount' => 459500,
                'bank_id' => $this->bank->id,
                'payment_method' => 2,
                'check_number' => '137286',
            ])
            ->assertStatus(201);

        $this->assertSame(Purchase::PAYMENT_PAID, $po->fresh()->payment_status);
    }

    public function test_pay_rejects_when_amount_exceeds_remaining(): void
    {
        $po = $this->makePO([
            'total' => 100,
            'amount_paid' => 0,
            'approval_status' => Purchase::APPROVAL_APPROVED,
        ]);

        $this->withHeader('Authorization', "Bearer {$this->payToken}")
            ->postJson("/api/v1/openclaw/purchases/{$po->id}/pay", [
                'amount' => 101,
                'bank_id' => $this->bank->id,
                'payment_method' => 'cash',
            ])
            ->assertStatus(422);

        // Bank balance untouched.
        $this->assertEqualsWithDelta(1000000.0, (float) $this->bank->fresh()->balance, 0.001);
    }

    public function test_pay_rejects_when_po_is_not_approved(): void
    {
        $po = $this->makePO(['approval_status' => Purchase::APPROVAL_PENDING]);

        $this->withHeader('Authorization', "Bearer {$this->payToken}")
            ->postJson("/api/v1/openclaw/purchases/{$po->id}/pay", [
                'amount' => 100,
                'bank_id' => $this->bank->id,
                'payment_method' => 'cash',
            ])
            ->assertStatus(409);
    }

    public function test_pay_requires_purchases_pay_ability(): void
    {
        $po = $this->makePO(['approval_status' => Purchase::APPROVAL_APPROVED]);

        $this->withHeader('Authorization', "Bearer {$this->approveToken}")
            ->postJson("/api/v1/openclaw/purchases/{$po->id}/pay", [
                'amount' => 100,
                'bank_id' => $this->bank->id,
                'payment_method' => 'cash',
            ])
            ->assertStatus(403)
            ->assertJsonPath('message', 'This token is missing the required ability: openclaw:purchases:pay.');
    }

    public function test_payments_endpoint_lists_payment_history(): void
    {
        $po = $this->makePO([
            'total' => 100,
            'amount_paid' => 0,
            'approval_status' => Purchase::APPROVAL_APPROVED,
        ]);

        $this->withHeader('Authorization', "Bearer {$this->payToken}")
            ->postJson("/api/v1/openclaw/purchases/{$po->id}/pay", [
                'amount' => 60,
                'bank_id' => $this->bank->id,
                'payment_method' => 'cash',
            ])
            ->assertStatus(201);

        $response = $this->withHeader('Authorization', "Bearer {$this->readToken}")
            ->getJson("/api/v1/openclaw/purchases/{$po->id}/payments");

        $response->assertStatus(200);
        $this->assertEqualsWithDelta(60.0, $response->json('data.totals.amount_paid'), 0.001);
        $this->assertEqualsWithDelta(40.0, $response->json('data.totals.remaining_balance'), 0.001);
        $this->assertSame(1, count($response->json('data.payments')));
    }

    // -------- void payment --------

    /**
     * Mint a write token that can both pay and void payments.
     */
    private function voidPaymentToken(): string
    {
        return $this->mintToken([
            'openclaw:read',
            'openclaw:purchases:approve',
            'openclaw:purchases:pay',
            'openclaw:purchases:void-payment',
        ]);
    }

    public function test_void_payment_unlinks_from_po_and_preserves_bank_transaction(): void
    {
        $po = $this->makePO([
            'total' => 1000,
            'amount_paid' => 0,
            'approval_status' => Purchase::APPROVAL_APPROVED,
        ]);

        $this->withHeader('Authorization', "Bearer {$this->payToken}")
            ->postJson("/api/v1/openclaw/purchases/{$po->id}/pay", [
                'amount' => 1000,
                'bank_id' => $this->bank->id,
                'payment_method' => 'cash',
            ])
            ->assertStatus(201);

        $payment = PurchasePayment::query()->where('purchase_id', $po->id)->first();
        $bankTxId = $payment->bank_transaction_id;
        $balanceAfterPayment = (float) $this->bank->fresh()->balance;

        $response = $this->withHeader('Authorization', "Bearer {$this->voidPaymentToken()}")
            ->postJson("/api/v1/openclaw/purchases/{$po->id}/payments/{$payment->id}/void", [
                'reason' => 'Wrong PO',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.already_voided', false)
            ->assertJsonPath('data.purchase.payment_status', Purchase::PAYMENT_UNPAID);
        $this->assertEqualsWithDelta(0.0, (float) $response->json('data.purchase.amount_paid'), 0.001);

        // Payment row is soft-deleted, bank_transaction is intact, balance unchanged.
        $this->assertSoftDeleted('purchase_payments', ['id' => $payment->id]);
        $this->assertDatabaseHas('bank_transactions', ['id' => $bankTxId]);
        $this->assertEqualsWithDelta($balanceAfterPayment, (float) $this->bank->fresh()->balance, 0.001, 'Bank balance must not move when voiding the payment.');
    }

    public function test_void_payment_is_idempotent_on_already_voided_row(): void
    {
        $po = $this->makePO([
            'total' => 100,
            'amount_paid' => 0,
            'approval_status' => Purchase::APPROVAL_APPROVED,
        ]);
        $this->withHeader('Authorization', "Bearer {$this->payToken}")
            ->postJson("/api/v1/openclaw/purchases/{$po->id}/pay", [
                'amount' => 100,
                'bank_id' => $this->bank->id,
                'payment_method' => 'cash',
            ])
            ->assertStatus(201);

        $payment = PurchasePayment::query()->where('purchase_id', $po->id)->first();
        $token = $this->voidPaymentToken();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/openclaw/purchases/{$po->id}/payments/{$payment->id}/void")
            ->assertStatus(200)
            ->assertJsonPath('data.already_voided', false);

        // Second call must succeed (idempotent) with already_voided=true.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/openclaw/purchases/{$po->id}/payments/{$payment->id}/void")
            ->assertStatus(200)
            ->assertJsonPath('data.already_voided', true);
    }

    public function test_void_payment_recalculates_amount_paid_when_only_one_of_many_is_voided(): void
    {
        // Simulates the PO #1030 mistake at smaller scale: three payments,
        // we void only one — amount_paid drops by exactly that one's value.
        $po = $this->makePO([
            'total' => 3000,
            'amount_paid' => 0,
            'approval_status' => Purchase::APPROVAL_APPROVED,
        ]);

        $ids = [];
        foreach ([1000, 1000, 1000] as $amt) {
            $this->withHeader('Authorization', "Bearer {$this->payToken}")
                ->postJson("/api/v1/openclaw/purchases/{$po->id}/pay", [
                    'amount' => $amt,
                    'bank_id' => $this->bank->id,
                    'payment_method' => 'cash',
                ])
                ->assertStatus(201);
        }
        $ids = PurchasePayment::query()->where('purchase_id', $po->id)->pluck('id')->all();
        $this->assertCount(3, $ids);
        $this->assertSame(Purchase::PAYMENT_PAID, $po->fresh()->payment_status);

        // Void the middle payment.
        $this->withHeader('Authorization', "Bearer {$this->voidPaymentToken()}")
            ->postJson("/api/v1/openclaw/purchases/{$po->id}/payments/{$ids[1]}/void")
            ->assertStatus(200);

        $po->refresh();
        $this->assertEqualsWithDelta(2000.0, (float) $po->amount_paid, 0.001);
        $this->assertSame(Purchase::PAYMENT_PARTIAL, $po->payment_status);
    }

    public function test_void_payment_returns_404_when_payment_belongs_to_a_different_po(): void
    {
        $poA = $this->makePO(['total' => 100, 'approval_status' => Purchase::APPROVAL_APPROVED]);
        $poB = $this->makePO(['total' => 100, 'approval_status' => Purchase::APPROVAL_APPROVED]);

        $this->withHeader('Authorization', "Bearer {$this->payToken}")
            ->postJson("/api/v1/openclaw/purchases/{$poA->id}/pay", [
                'amount' => 100,
                'bank_id' => $this->bank->id,
                'payment_method' => 'cash',
            ])
            ->assertStatus(201);

        $paymentOnA = PurchasePayment::query()->where('purchase_id', $poA->id)->first();

        // Try to void poA's payment via poB's URL — must 404.
        $this->withHeader('Authorization', "Bearer {$this->voidPaymentToken()}")
            ->postJson("/api/v1/openclaw/purchases/{$poB->id}/payments/{$paymentOnA->id}/void")
            ->assertStatus(404);

        $this->assertDatabaseHas('purchase_payments', ['id' => $paymentOnA->id, 'deleted_at' => null]);
    }

    public function test_void_payment_returns_404_when_po_belongs_to_other_tenant(): void
    {
        // Foreign PO. Even with valid void-payment ability, attempting to
        // void via the wrong tenant's URL must 404.
        $foreignPo = Purchase::factory()->create([
            'user_id' => $this->otherOwner->user_id,
            'supplier_id' => $this->supplier->id,
            'store_id' => $this->store->id,
            'total' => 100,
            'amount_paid' => 100,
            'approval_status' => Purchase::APPROVAL_APPROVED,
            'payment_status' => Purchase::PAYMENT_PAID,
            'created_by' => $this->otherOwner->id,
        ]);

        $this->withHeader('Authorization', "Bearer {$this->voidPaymentToken()}")
            ->postJson("/api/v1/openclaw/purchases/{$foreignPo->id}/payments/9999/void")
            ->assertStatus(404);
    }

    public function test_void_payment_requires_the_void_payment_ability(): void
    {
        $po = $this->makePO(['total' => 100, 'approval_status' => Purchase::APPROVAL_APPROVED]);

        $this->withHeader('Authorization', "Bearer {$this->payToken}")
            ->postJson("/api/v1/openclaw/purchases/{$po->id}/pay", [
                'amount' => 100,
                'bank_id' => $this->bank->id,
                'payment_method' => 'cash',
            ])
            ->assertStatus(201);

        $payment = PurchasePayment::query()->where('purchase_id', $po->id)->first();

        // A token with pay (but not void-payment) must NOT be able to void.
        $this->withHeader('Authorization', "Bearer {$this->payToken}")
            ->postJson("/api/v1/openclaw/purchases/{$po->id}/payments/{$payment->id}/void")
            ->assertStatus(403);

        $this->assertDatabaseHas('purchase_payments', ['id' => $payment->id, 'deleted_at' => null]);
    }

    // -------- void payment with reverse_to_expense (Model D) --------

    private function makeUtilitiesCategory(): ExpenseCategory
    {
        return ExpenseCategory::create([
            'name' => 'Utilities',
            'description' => null,
            'status' => true,
            'created_by' => $this->owner->id,
        ]);
    }

    private function payAndReturnPayment(Purchase $po, float $amount, string $method = 'cash'): PurchasePayment
    {
        $this->withHeader('Authorization', "Bearer {$this->payToken}")
            ->postJson("/api/v1/openclaw/purchases/{$po->id}/pay", [
                'amount' => $amount,
                'bank_id' => $this->bank->id,
                'payment_method' => $method,
            ])
            ->assertStatus(201);

        return PurchasePayment::query()->where('purchase_id', $po->id)->latest('id')->firstOrFail();
    }

    public function test_void_with_reverse_to_expense_emits_three_bank_rows_and_zero_net_balance(): void
    {
        $cat = $this->makeUtilitiesCategory();
        $po = $this->makePO(['total' => 200000, 'approval_status' => Purchase::APPROVAL_APPROVED]);
        $payment = $this->payAndReturnPayment($po, 200000);

        $balanceAfterPayment = (float) $this->bank->fresh()->balance;  // 800,000
        $bankTxCountBefore = BankTransaction::query()->where('bank_id', $this->bank->id)->count();

        $response = $this->withHeader('Authorization', "Bearer {$this->voidPaymentToken()}")
            ->postJson("/api/v1/openclaw/purchases/{$po->id}/payments/{$payment->id}/void", [
                'reason' => 'Wrong PO — was utilities expense',
                'reverse_to_expense' => [
                    'expense' => [
                        'payee' => 'Meralco',
                        'expense_date' => now()->toDateString(),
                        'category' => 'Utilities',
                        'description' => 'May 2026 electric bill',
                        'receipt_number' => 'MERALCO-2026-05',
                    ],
                    'reversal' => [
                        'description' => 'Reversing PO #'.$po->po.' payment — misclassified',
                        'payee' => 'PO Payment Correction',
                        'reference_number' => 'REV-TEST-001',
                    ],
                ],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.already_voided', false)
            ->assertJsonPath('data.reversal.reference_number', 'REV-TEST-001')
            ->assertJsonPath('data.expense.payee', 'Meralco')
            ->assertJsonPath('data.expense.expense_category_id', $cat->id);
        $this->assertEqualsWithDelta(200000.0, (float) $response->json('data.reversal.amount'), 0.001);
        $this->assertEqualsWithDelta(200000.0, (float) $response->json('data.expense.amount'), 0.001);

        // Net bank balance: SAME as after the wrong payment landed. The
        // reversal deposit + new expense withdrawal cancel each other on
        // the running balance — they're pure ledger entries recording the
        // correction. The cash genuinely left the bank once (as the now-
        // correctly-attributed expense). Bank statement still shows -200k
        // net; the app's ledger now shows the correction story in three
        // bank_transaction rows.
        $this->assertEqualsWithDelta($balanceAfterPayment, (float) $this->bank->fresh()->balance, 0.001);

        // Three bank rows now exist for this payment's amount (original
        // withdrawal + REV deposit + new expense withdrawal).
        $this->assertSame(
            $bankTxCountBefore + 2,
            BankTransaction::query()->where('bank_id', $this->bank->id)->count(),
        );

        // PurchasePayment is soft-deleted, Expense is now linked to its own
        // new withdrawal (NOT to the original PurchasePayment's withdrawal).
        $this->assertSoftDeleted('purchase_payments', ['id' => $payment->id]);
        $expense = Expense::query()->latest('id')->first();
        $this->assertNotSame($payment->bank_transaction_id, $expense->bank_transaction_id);

        // PO rolled back to UNPAID.
        $po->refresh();
        $this->assertEqualsWithDelta(0.0, (float) $po->amount_paid, 0.001);
        $this->assertSame(Purchase::PAYMENT_UNPAID, $po->payment_status);
    }

    public function test_reverse_to_expense_accepts_expense_category_id_directly(): void
    {
        $cat = $this->makeUtilitiesCategory();
        $po = $this->makePO(['total' => 500, 'approval_status' => Purchase::APPROVAL_APPROVED]);
        $payment = $this->payAndReturnPayment($po, 500);

        $this->withHeader('Authorization', "Bearer {$this->voidPaymentToken()}")
            ->postJson("/api/v1/openclaw/purchases/{$po->id}/payments/{$payment->id}/void", [
                'reverse_to_expense' => [
                    'expense' => [
                        'payee' => 'Meralco',
                        'expense_date' => now()->toDateString(),
                        'expense_category_id' => $cat->id,
                    ],
                    'reversal' => ['description' => 'Reverse'],
                ],
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.expense.expense_category_id', $cat->id);
    }

    public function test_reverse_to_expense_422s_on_unknown_category_name(): void
    {
        $po = $this->makePO(['total' => 500, 'approval_status' => Purchase::APPROVAL_APPROVED]);
        $payment = $this->payAndReturnPayment($po, 500);

        $this->withHeader('Authorization', "Bearer {$this->voidPaymentToken()}")
            ->postJson("/api/v1/openclaw/purchases/{$po->id}/payments/{$payment->id}/void", [
                'reverse_to_expense' => [
                    'expense' => [
                        'payee' => 'Meralco',
                        'expense_date' => now()->toDateString(),
                        'category' => 'Definitely Not A Real Category',
                    ],
                    'reversal' => ['description' => 'Reverse'],
                ],
            ])
            ->assertStatus(422);

        // Transaction must have rolled back — payment still alive, no expense.
        $this->assertDatabaseHas('purchase_payments', ['id' => $payment->id, 'deleted_at' => null]);
        $this->assertSame(0, Expense::query()->count());
    }

    public function test_reverse_to_expense_requires_explicit_reversal_description(): void
    {
        $this->makeUtilitiesCategory();
        $po = $this->makePO(['total' => 500, 'approval_status' => Purchase::APPROVAL_APPROVED]);
        $payment = $this->payAndReturnPayment($po, 500);

        $this->withHeader('Authorization', "Bearer {$this->voidPaymentToken()}")
            ->postJson("/api/v1/openclaw/purchases/{$po->id}/payments/{$payment->id}/void", [
                'reverse_to_expense' => [
                    'expense' => [
                        'payee' => 'Meralco',
                        'expense_date' => now()->toDateString(),
                        'category' => 'Utilities',
                    ],
                    'reversal' => [],  // description missing
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reverse_to_expense.reversal.description']);
    }

    public function test_already_voided_payment_skips_reverse_block_and_returns_idempotent(): void
    {
        // Guard against bot retrying after a successful Model-D call. The
        // second call must NOT create another reversal + expense pair.
        $this->makeUtilitiesCategory();
        $po = $this->makePO(['total' => 500, 'approval_status' => Purchase::APPROVAL_APPROVED]);
        $payment = $this->payAndReturnPayment($po, 500);
        $token = $this->voidPaymentToken();

        $body = [
            'reverse_to_expense' => [
                'expense' => [
                    'payee' => 'Meralco',
                    'expense_date' => now()->toDateString(),
                    'category' => 'Utilities',
                ],
                'reversal' => ['description' => 'First reverse'],
            ],
        ];

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/openclaw/purchases/{$po->id}/payments/{$payment->id}/void", $body)
            ->assertStatus(200);

        $expenseCountAfterFirst = Expense::query()->count();
        $bankTxCountAfterFirst = BankTransaction::query()->count();

        // Retry with the same body — must be idempotent.
        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/openclaw/purchases/{$po->id}/payments/{$payment->id}/void", $body)
            ->assertStatus(200)
            ->assertJsonPath('data.already_voided', true)
            ->assertJsonPath('data.reversal', null)
            ->assertJsonPath('data.expense', null);

        $this->assertSame($expenseCountAfterFirst, Expense::query()->count());
        $this->assertSame($bankTxCountAfterFirst, BankTransaction::query()->count());
    }
}
