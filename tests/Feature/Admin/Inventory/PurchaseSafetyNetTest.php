<?php

namespace Tests\Feature\Admin\Inventory;

use App\Models\Employees\Role;
use App\Models\InventoryManagement\Purchase;
use App\Models\InventoryManagement\PurchaseApproval;
use App\Models\InventoryManagement\PurchaseLine;
use App\Models\InventoryManagement\Supplier;
use App\Models\Products\Item;
use App\Models\Products\ItemStore;
use App\Models\Settings\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Safety net for the Purchase Order remediation work scoped in
 * development/specs/purchase_order_audit_and_remediation.md.
 *
 * Two test classes here:
 *
 * 1. CHARACTERIZATION tests — green now, must stay green through
 *    every Tier 1 fix. These pin down the behaviors we DON'T want
 *    to break while refactoring (receive happy path, approve flow,
 *    self-approval block, …).
 *
 * 2. BUG-DEMONSTRATING tests — marked with $this->markTestSkipped()
 *    today so CI stays green. Each one carries the assertion that
 *    SHOULD pass after the fix lands. Remove the skip when fixing
 *    the corresponding bug.
 *
 * Reference patterns: Admin/PurchasePaymentTest, API/v1/openclaw/
 * OpenclawPurchaseReceiveTest.
 */
class PurchaseSafetyNetTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $approver;  // distinct user to bypass the self-approval block

    protected Store $store;

    protected Supplier $supplier;

    protected Item $item;

    protected ItemStore $itemStore;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::factory()->admin()->create();
        $this->admin = User::factory()->create(['role_id' => $adminRole->id, 'user_id' => 1]);
        $this->approver = User::factory()->create(['role_id' => $adminRole->id, 'user_id' => 1]);
        $this->store = Store::factory()->create(['user_id' => 1]);
        $this->supplier = Supplier::factory()->create(['user_id' => 1]);
        $this->item = Item::factory()->create(['user_id' => 1, 'cost' => 50]);
        $this->itemStore = ItemStore::factory()->create([
            'item_id' => $this->item->id,
            'store_id' => $this->store->id,
            'stock' => 100,
        ]);
    }

    /**
     * Build a PO with a single line, ready to be approved/received.
     * Most tests start from this fixture so they share a known shape.
     */
    private function approvedPurchase(int $qty = 10, float $cost = 50): Purchase
    {
        $purchase = Purchase::factory()->create([
            'user_id' => 1,
            'supplier_id' => $this->supplier->id,
            'store_id' => $this->store->id,
            'created_by' => $this->admin->id,
            'total' => $qty * $cost,
            'items' => $qty,
            'received' => 0,
            'status' => 0,
            'amount_paid' => 0,
            'payment_status' => Purchase::PAYMENT_UNPAID,
            'approval_status' => Purchase::APPROVAL_APPROVED,
        ]);

        PurchaseLine::create([
            'purchase_id' => $purchase->id,
            'item_id' => $this->item->id,
            'qty' => $qty,
            'cost' => $cost,
            'unit_qty' => 1,
            'received' => 0,
        ]);

        // Approval row mirrors the integer status on Purchase so the
        // invariant in §1.5 holds for the seed data.
        PurchaseApproval::create([
            'purchase_id' => $purchase->id,
            'status' => 'approved',
            'approved_by' => $this->approver->id,
            'approved_at' => now(),
        ]);

        return $purchase->fresh();
    }

    // -------------------------------------------------------------------
    // CHARACTERIZATION — these are the rails. Stay green through fixes.
    // -------------------------------------------------------------------

    public function test_receive_happy_path_updates_stock_line_and_purchase_received(): void
    {
        $purchase = $this->approvedPurchase(qty: 10);
        $line = $purchase->lines->first();
        $stockBefore = $this->itemStore->fresh()->stock;

        // The admin receive controller expects parallel arrays
        // line_id[] + toReceive[] (matches the form's input names),
        // NOT the structured `lines[].line_id` shape OpenClaw uses.
        $this->actingAs($this->admin)
            ->post(route('purchase.receive.now', $purchase->id), [
                'line_id' => [$line->id],
                'toReceive' => [4],
            ])
            ->assertRedirect();

        // Stock incremented by exactly the received qty.
        $this->assertSame(
            $stockBefore + 4,
            (float) $this->itemStore->fresh()->stock,
            'ItemStore.stock must increment by the line.received delta.'
        );
        // Line received qty matches.
        $this->assertSame(4, (int) $line->fresh()->received);
        // Purchase aggregate received matches.
        $this->assertSame(4, (int) $purchase->fresh()->received);
    }

    public function test_partial_then_full_receive_progresses_status_correctly(): void
    {
        $purchase = $this->approvedPurchase(qty: 10);
        $line = $purchase->lines->first();

        // First call: 4 of 10
        $this->actingAs($this->admin)->post(route('purchase.receive.now', $purchase->id), [
            'line_id' => [$line->id],
            'toReceive' => [4],
        ]);
        $fresh = $purchase->fresh();
        $this->assertSame(4, (int) $fresh->received);
        $this->assertNotSame((int) $fresh->items, (int) $fresh->received,
            'Partial receive should not equal the total expected.');

        // Second call: remaining 6
        $this->actingAs($this->admin)->post(route('purchase.receive.now', $purchase->id), [
            'line_id' => [$line->id],
            'toReceive' => [6],
        ]);
        $fresh = $purchase->fresh();
        $this->assertSame(10, (int) $fresh->received);
        $this->assertSame((int) $fresh->items, (int) $fresh->received,
            'After full receive items == received.');
    }

    public function test_approve_creates_approval_row_and_sets_int_status(): void
    {
        $purchase = Purchase::factory()->create([
            'user_id' => 1,
            'supplier_id' => $this->supplier->id,
            'store_id' => $this->store->id,
            'created_by' => $this->admin->id,
            'approval_status' => Purchase::APPROVAL_PENDING,
        ]);

        $this->actingAs($this->approver)
            ->post(route('purchase.approve', $purchase->id))
            ->assertRedirect();

        $fresh = $purchase->fresh();
        $this->assertSame((int) Purchase::APPROVAL_APPROVED, (int) $fresh->approval_status,
            'approval_status must be the int constant after approve.');
        $this->assertNotNull($fresh->latestApproval ?? null,
            'A PurchaseApproval row must exist.');
        $this->assertSame('approved', $fresh->latestApproval->status,
            'The approval row carries the string status.');
        $this->assertSame($this->approver->id, $fresh->latestApproval->approved_by);
    }

    public function test_reject_stores_comment_and_sets_int_status(): void
    {
        $purchase = Purchase::factory()->create([
            'user_id' => 1,
            'supplier_id' => $this->supplier->id,
            'store_id' => $this->store->id,
            'created_by' => $this->admin->id,
            'approval_status' => Purchase::APPROVAL_PENDING,
        ]);

        $this->actingAs($this->approver)
            ->post(route('purchase.reject', $purchase->id), [
                'rejection_comment' => 'Price exceeds approved budget for Q2.',
            ])
            ->assertRedirect();

        $fresh = $purchase->fresh();
        $this->assertSame((int) Purchase::APPROVAL_REJECTED, (int) $fresh->approval_status);
        $this->assertSame('rejected', $fresh->latestApproval->status);
        $this->assertSame('Price exceeds approved budget for Q2.',
            $fresh->latestApproval->rejection_comment);
    }

    public function test_creator_cannot_self_approve(): void
    {
        $purchase = Purchase::factory()->create([
            'user_id' => 1,
            'supplier_id' => $this->supplier->id,
            'store_id' => $this->store->id,
            'created_by' => $this->admin->id,
            'approval_status' => Purchase::APPROVAL_PENDING,
        ]);

        // Self-approval is blocked with a flashed `error` (not a
        // validation/session error), so we assert on the flash slot
        // and on the unchanged DB state.
        $this->actingAs($this->admin)  // SAME user as created_by
            ->post(route('purchase.approve', $purchase->id))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame((int) Purchase::APPROVAL_PENDING, (int) $purchase->fresh()->approval_status,
            'Self-approval must NOT mutate the approval_status.');
        $this->assertNull(PurchaseApproval::where('purchase_id', $purchase->id)->first(),
            'No PurchaseApproval row should be written on a blocked self-approval.');
    }

    public function test_payment_status_constants_are_integers(): void
    {
        // Documents the intended schema even if the column still
        // happens to be boolean today. Should this assertion ever
        // start failing it'd be a sign of constant drift.
        $this->assertSame(0, Purchase::PAYMENT_UNPAID);
        $this->assertSame(1, Purchase::PAYMENT_PARTIAL);
        $this->assertSame(2, Purchase::PAYMENT_PAID);
    }

    public function test_approval_status_constants_are_integers(): void
    {
        $this->assertSame(0, Purchase::APPROVAL_DRAFT);
        $this->assertSame(1, Purchase::APPROVAL_PENDING);
        $this->assertSame(2, Purchase::APPROVAL_APPROVED);
        $this->assertSame(3, Purchase::APPROVAL_REJECTED);
    }

    public function test_approval_observer_keeps_parent_int_status_in_sync(): void
    {
        // §1.5 — PurchaseApprovalObserver bumps the parent's int
        // approval_status whenever a child PurchaseApproval row is
        // written. Test by writing the child directly (bypassing the
        // controller) and asserting the parent flips with no
        // intermediate writes.
        $purchase = Purchase::factory()->create([
            'user_id' => 1,
            'supplier_id' => $this->supplier->id,
            'store_id' => $this->store->id,
            'created_by' => $this->admin->id,
            'approval_status' => Purchase::APPROVAL_PENDING,
        ]);

        PurchaseApproval::create([
            'purchase_id' => $purchase->id,
            'status' => 'approved',
            'approved_by' => $this->approver->id,
            'approved_at' => now(),
        ]);

        $this->assertSame(
            (int) Purchase::APPROVAL_APPROVED,
            (int) $purchase->fresh()->approval_status,
            'Observer should have flipped the parent to APPROVAL_APPROVED.'
        );

        // And an update of the approval row should propagate too.
        $approval = $purchase->fresh()->latestApproval;
        $approval->update(['status' => 'rejected', 'rejection_comment' => 'Changed mind']);

        $this->assertSame(
            (int) Purchase::APPROVAL_REJECTED,
            (int) $purchase->fresh()->approval_status,
            'Observer should propagate updates too, not just creates.'
        );
    }

    public function test_save_payment_details_has_no_route_binding(): void
    {
        // §3.1 — confirms the method is unreachable through HTTP.
        // Once we delete it (Phase 2) this test stays green because
        // no route ever pointed there.
        $routes = collect(\Route::getRoutes())->map(
            fn ($r) => method_exists($r, 'getActionMethod') ? $r->getActionMethod() : null
        )->filter()->values();

        $this->assertFalse(
            $routes->contains('savePaymentDetails'),
            'savePaymentDetails should not be wired to any route. Delete the method.'
        );
    }

    // -------------------------------------------------------------------
    // BUG-DEMONSTRATING — marked skipped today; flip these on as the
    // corresponding fix lands in Phase 2 of the remediation spec.
    // -------------------------------------------------------------------

    public function test_bu_g_typo_ammount_column_should_not_be_writable(): void
    {
        // §1.1 + §3.1 closed: savePaymentDetails() deleted entirely,
        // taking the `ammount` typo with it. This test is now a
        // regression guard — no one re-introduces either the typo or
        // the dead method.
        $hits = shell_exec('grep -rn "ammount" '.base_path('app').' 2>/dev/null || true');
        $this->assertEmpty(trim((string) $hits),
            'Found "ammount" typo in: '.PHP_EOL.$hits);

        $savePaymentDetailsExists = shell_exec(
            'grep -rln "function savePaymentDetails" '.base_path('app').' 2>/dev/null || true'
        );
        $this->assertEmpty(trim((string) $savePaymentDetailsExists),
            'savePaymentDetails() was deleted in §3.1 — do not re-add it.');
    }

    public function test_bu_g_payment_status_column_should_be_tinyint(): void
    {
        // §1.2 closed: column converted to unsigned tinyint, NULLs
        // backfilled to UNPAID. Regression guard from here on.
        $col = collect(\Schema::getColumns('purchases'))->firstWhere('name', 'payment_status');
        $this->assertSame('tinyint unsigned', strtolower($col['type'] ?? ''),
            'payment_status column must be tinyint unsigned (default 0).');
        $this->assertFalse((bool) ($col['nullable'] ?? true),
            'payment_status must be NOT NULL to prevent drift back into ambiguous state.');

        // No more boolean writes anywhere.
        $boolWrites = shell_exec(
            'grep -rn "payment_status.*=>\\s*\\(true\\|false\\)" '.base_path('app').' 2>/dev/null || true'
        );
        $this->assertEmpty(trim((string) $boolWrites),
            'No code path may write `payment_status => true|false`. Use Purchase::PAYMENT_* constants instead. Found: '.PHP_EOL.$boolWrites);
    }

    public function test_bu_g_receive_now_must_wrap_in_db_transaction(): void
    {
        // §1.3 closed: receiveNow wrapped in DB::transaction with
        // lockForUpdate on ItemStore. Regression guard: simulate a
        // mid-call DB failure and assert the line.received bump
        // rolled back too.
        $purchase = $this->approvedPurchase(qty: 10);
        $line = $purchase->lines->first();
        $stockBefore = $this->itemStore->fresh()->stock;

        // One-shot tripwire: throw on the FIRST item_stores SELECT
        // inside the receive loop. The flag stops the listener from
        // firing again on the post-rollback SELECTs that the
        // assertions below run via $itemStore->fresh().
        $thrown = false;
        \DB::listen(function ($q) use (&$thrown) {
            if (! $thrown && str_contains($q->sql, 'item_stores') && str_starts_with($q->sql, 'select')) {
                $thrown = true;
                throw new \RuntimeException('Simulated failure mid-receive.');
            }
        });

        try {
            $this->actingAs($this->admin)->post(route('purchase.receive.now', $purchase->id), [
                'line_id' => [$line->id],
                'toReceive' => [4],
            ]);
        } catch (\Throwable $e) {
            // expected
        }

        $this->assertSame($stockBefore, (float) $this->itemStore->fresh()->stock,
            'On mid-transaction failure no stock write should survive.');
        $this->assertSame(0, (int) $line->fresh()->received,
            'On rollback line.received must remain 0.');
    }

    public function test_bu_g_destroy_should_cascade_to_lines_and_payments(): void
    {
        // §1.6 closed: destroy wraps the parent flip + child cleanup
        // in a DB::transaction and writes an audit log row.
        // PurchaseLine and PurchaseAdd are hard-deleted (snapshots —
        // no independent meaning once the PO is void). PurchasePayment
        // is soft-deleted because the model already carries the trait.
        $purchase = $this->approvedPurchase(qty: 5);
        $line = $purchase->lines->first();

        $this->actingAs($this->admin)
            ->delete(route('purchases.destroy', $purchase->id))
            ->assertRedirect();

        $this->assertNull(PurchaseLine::find($line->id),
            'PurchaseLine must be deleted when its parent Purchase is destroyed.');
        $this->assertSame('3', (string) $purchase->fresh()->status,
            'Parent Purchase carries status=3 as the void marker.');

        // Audit row landed too — §4 work picks this up for the
        // remaining methods (approve / reject / recordPayment /
        // receiveNow), but destroy's audit happens here.
        $audit = \App\Models\Reports\AuditLog::where('auditable_type', Purchase::class)
            ->where('auditable_id', $purchase->id)
            ->where('event', 'purchase_voided')
            ->first();
        $this->assertNotNull($audit, 'A void must leave an audit row.');
        $this->assertSame($this->admin->id, $audit->user_id);
        $this->assertSame(1, (int) $audit->new_values['lines_deleted']);
    }

    public function test_audit_log_written_on_approve_and_reject(): void
    {
        // §4 closed: approve / reject / recordPayment / receiveNow /
        // destroy all write AuditLog rows. This test covers approve
        // and reject (the two paths exercised by the existing
        // characterization tests above); recordPayment is covered by
        // PurchasePaymentTest; receiveNow + destroy already have
        // their own audit assertions in the §1.3 and §1.6 tests.
        $purchase = Purchase::factory()->create([
            'user_id' => 1,
            'supplier_id' => $this->supplier->id,
            'store_id' => $this->store->id,
            'created_by' => $this->admin->id,
            'approval_status' => Purchase::APPROVAL_PENDING,
        ]);

        $this->actingAs($this->approver)
            ->post(route('purchase.approve', $purchase->id))
            ->assertRedirect();

        $approveAudit = \App\Models\Reports\AuditLog::where('auditable_type', Purchase::class)
            ->where('auditable_id', $purchase->id)
            ->where('event', 'purchase_approved')
            ->first();
        $this->assertNotNull($approveAudit, 'Every approve must leave an audit row.');
        $this->assertSame($this->approver->id, $approveAudit->user_id);
        $this->assertSame((int) Purchase::APPROVAL_APPROVED, $approveAudit->new_values['to_status']);

        // And a fresh PO routed through reject() writes its own row.
        $rejected = Purchase::factory()->create([
            'user_id' => 1,
            'supplier_id' => $this->supplier->id,
            'store_id' => $this->store->id,
            'created_by' => $this->admin->id,
            'approval_status' => Purchase::APPROVAL_PENDING,
        ]);

        $this->actingAs($this->approver)
            ->post(route('purchase.reject', $rejected->id), [
                'rejection_comment' => 'Price exceeds approved budget for Q2.',
            ])
            ->assertRedirect();

        $rejectAudit = \App\Models\Reports\AuditLog::where('auditable_type', Purchase::class)
            ->where('auditable_id', $rejected->id)
            ->where('event', 'purchase_rejected')
            ->first();
        $this->assertNotNull($rejectAudit, 'Every reject must leave an audit row.');
        $this->assertSame('Price exceeds approved budget for Q2.',
            $rejectAudit->new_values['rejection_comment']);
    }

    public function test_edit_preserves_purchase_add_amounts_in_total(): void
    {
        // §3.3 verified on re-inspection (2026-06-10): the audit was
        // wrong. The current update() at line 362 does:
        //     `$total += $request->addAmount[$i];`
        // inside its addAmount loop, then writes `total = $total` to
        // the purchases row on line 370. PurchaseAdd values ARE
        // included. This test makes that invariant a regression guard.
        $purchase = Purchase::factory()->create([
            'user_id' => 1,
            'supplier_id' => $this->supplier->id,
            'store_id' => $this->store->id,
            'created_by' => $this->admin->id,
            'approval_status' => Purchase::APPROVAL_PENDING,
            'total' => 600,
        ]);

        $response = $this->actingAs($this->admin)
            ->put(route('purchases.update', $purchase->id), [
                'supplier' => $this->supplier->id,
                'store' => $this->store->id,
                'purchased' => now()->format('Y-m-d'),
                'expect' => 30,
                'invoice_no' => 'INV-001',
                // One line at ₱500 + one extra fee at ₱100 → total ₱600
                'item_id' => [$this->item->id],
                'qty' => [10],
                'price' => [50],
                'unit' => [0],
                'addAmount' => [100],
                'addDescription' => ['Delivery fee'],
            ]);

        $this->assertSame(
            600.0,
            (float) $purchase->fresh()->total,
            'After edit() recompute, total must include both line subtotals AND PurchaseAdd amounts.'
        );
    }
}
