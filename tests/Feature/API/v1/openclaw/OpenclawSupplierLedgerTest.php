<?php

namespace Tests\Feature\API\v1\openclaw;

use App\Models\Accounting\Bank;
use App\Models\Accounting\ExpenseCategory;
use App\Models\ApiToken;
use App\Models\Employees\Role;
use App\Models\InventoryManagement\Purchase;
use App\Models\InventoryManagement\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpenclawSupplierLedgerTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected User $otherOwner;

    protected string $readToken;

    protected string $writeToken;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::factory()->admin()->create();

        $this->owner = User::factory()->create(['role_id' => $role->id]);
        $this->owner->forceFill(['user_id' => $this->owner->id])->save();

        $this->otherOwner = User::factory()->create(['role_id' => $role->id]);
        $this->otherOwner->forceFill(['user_id' => $this->otherOwner->id])->save();

        $this->readToken = $this->mintToken(['openclaw:read']);
        $this->writeToken = $this->mintToken([
            'openclaw:read',
            'openclaw:expenses:create',
            'openclaw:suppliers:write',
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

    private function makePO(Supplier $supplier, float $total, float $paid, ?string $purchasedAt = null, int $approval = Purchase::APPROVAL_APPROVED, ?int $userId = null): Purchase
    {
        return Purchase::factory()->create([
            'supplier_id' => $supplier->id,
            'user_id' => $userId ?? $this->owner->user_id,
            'total' => $total,
            'amount_paid' => $paid,
            'purchased' => $purchasedAt ?? now()->toDateString(),
            'approval_status' => $approval,
        ]);
    }

    public function test_payables_summary_aggregates_outstanding_per_supplier(): void
    {
        $a = Supplier::factory()->create(['user_id' => $this->owner->user_id, 'name' => 'ACME', 'payment_terms_days' => 30]);
        $b = Supplier::factory()->create(['user_id' => $this->owner->user_id, 'name' => 'Beta']);
        $paid = Supplier::factory()->create(['user_id' => $this->owner->user_id, 'name' => 'PaidUp']);

        // ACME: 50000 owed across 2 POs.
        $this->makePO($a, total: 30000, paid: 10000);
        $this->makePO($a, total: 50000, paid: 20000);
        // Beta: 10000 owed across 1 PO.
        $this->makePO($b, total: 10000, paid: 0);
        // PaidUp: fully paid.
        $this->makePO($paid, total: 5000, paid: 5000);
        // Draft (not approved) — should be ignored.
        $this->makePO($a, total: 999, paid: 0, approval: Purchase::APPROVAL_DRAFT);

        $response = $this->withHeader('Authorization', "Bearer {$this->readToken}")
            ->getJson('/api/v1/openclaw/suppliers/payables-summary');

        $response->assertStatus(200)
            ->assertJsonPath('data.totals.supplier_count_with_balance', 2);
        $this->assertEqualsWithDelta(60000.0, $response->json('data.totals.total_payable'), 0.001);

        $rows = collect($response->json('data.top_creditors'))->keyBy('supplier_id');
        $this->assertEqualsWithDelta(50000.0, $rows[$a->id]['outstanding'], 0.001);
        $this->assertEqualsWithDelta(10000.0, $rows[$b->id]['outstanding'], 0.001);
        $this->assertSame(30, $rows[$a->id]['payment_terms_days']);
        $this->assertNull($rows[$b->id]['payment_terms_days']);
        $this->assertArrayNotHasKey($paid->id, $rows->all());
    }

    public function test_payables_summary_excludes_other_tenant_data(): void
    {
        $mine = Supplier::factory()->create(['user_id' => $this->owner->user_id, 'name' => 'Mine']);
        $theirs = Supplier::factory()->create(['user_id' => $this->otherOwner->user_id, 'name' => 'Theirs']);

        $this->makePO($mine, total: 1000, paid: 0);
        $this->makePO($theirs, total: 999999, paid: 0, userId: $this->otherOwner->user_id);

        $response = $this->withHeader('Authorization', "Bearer {$this->readToken}")
            ->getJson('/api/v1/openclaw/suppliers/payables-summary');

        $response->assertStatus(200);
        $this->assertEqualsWithDelta(1000.0, $response->json('data.totals.total_payable'), 0.001);
        $names = collect($response->json('data.top_creditors'))->pluck('supplier_name')->all();
        $this->assertContains('Mine', $names);
        $this->assertNotContains('Theirs', $names);
    }

    public function test_per_supplier_payable_returns_po_breakdown_with_days_overdue(): void
    {
        $supplier = Supplier::factory()->create([
            'user_id' => $this->owner->user_id,
            'name' => 'ACME',
            'payment_terms_days' => 30,
        ]);
        // PO purchased 60 days ago, terms net 30 -> 30 days overdue.
        $latePO = $this->makePO($supplier, total: 50000, paid: 10000, purchasedAt: now()->subDays(60)->toDateString());
        // PO purchased 5 days ago, not overdue.
        $freshPO = $this->makePO($supplier, total: 20000, paid: 0, purchasedAt: now()->subDays(5)->toDateString());

        $response = $this->withHeader('Authorization', "Bearer {$this->readToken}")
            ->getJson("/api/v1/openclaw/suppliers/{$supplier->id}/payable");

        $response->assertStatus(200)
            ->assertJsonPath('data.supplier.id', $supplier->id)
            ->assertJsonPath('data.supplier.payment_terms_days', 30)
            ->assertJsonPath('data.totals.po_count', 2)
            ->assertJsonPath('data.totals.overdue_po_count', 1);

        $this->assertEqualsWithDelta(60000.0, $response->json('data.totals.outstanding'), 0.001);

        $pos = collect($response->json('data.purchase_orders'))->keyBy('id');
        $this->assertGreaterThanOrEqual(29, $pos[$latePO->id]['days_overdue']);
        $this->assertSame(0, $pos[$freshPO->id]['days_overdue']);
    }

    public function test_per_supplier_payable_404s_for_other_tenant_supplier(): void
    {
        $foreign = Supplier::factory()->create(['user_id' => $this->otherOwner->user_id]);

        $this->withHeader('Authorization', "Bearer {$this->readToken}")
            ->getJson("/api/v1/openclaw/suppliers/{$foreign->id}/payable")
            ->assertStatus(404);
    }

    public function test_patch_payment_terms_sets_and_clears(): void
    {
        $supplier = Supplier::factory()->create(['user_id' => $this->owner->user_id, 'name' => 'ACME']);

        $this->withHeader('Authorization', "Bearer {$this->writeToken}")
            ->patchJson("/api/v1/openclaw/suppliers/{$supplier->id}/payment-terms", ['payment_terms_days' => 45])
            ->assertStatus(200)
            ->assertJsonPath('data.supplier.payment_terms_days', 45);
        $this->assertSame(45, (int) $supplier->fresh()->payment_terms_days);

        $this->withHeader('Authorization', "Bearer {$this->writeToken}")
            ->patchJson("/api/v1/openclaw/suppliers/{$supplier->id}/payment-terms", ['payment_terms_days' => null])
            ->assertStatus(200)
            ->assertJsonPath('data.supplier.payment_terms_days', null);
        $this->assertNull($supplier->fresh()->payment_terms_days);
    }

    public function test_patch_payment_terms_requires_suppliers_write_ability(): void
    {
        $supplier = Supplier::factory()->create(['user_id' => $this->owner->user_id]);

        $this->withHeader('Authorization', "Bearer {$this->readToken}")
            ->patchJson("/api/v1/openclaw/suppliers/{$supplier->id}/payment-terms", ['payment_terms_days' => 30])
            ->assertStatus(403)
            ->assertJsonPath('message', 'This token is missing the required ability: openclaw:suppliers:write.');
    }

    public function test_patch_payment_terms_404s_for_other_tenant_supplier(): void
    {
        $foreign = Supplier::factory()->create(['user_id' => $this->otherOwner->user_id]);

        $this->withHeader('Authorization', "Bearer {$this->writeToken}")
            ->patchJson("/api/v1/openclaw/suppliers/{$foreign->id}/payment-terms", ['payment_terms_days' => 30])
            ->assertStatus(404);
    }

    public function test_inventory_suppliers_list_now_includes_payment_terms_days(): void
    {
        Supplier::factory()->create(['user_id' => $this->owner->user_id, 'status' => 1, 'name' => 'A', 'payment_terms_days' => 15]);

        $response = $this->withHeader('Authorization', "Bearer {$this->readToken}")
            ->getJson('/api/v1/openclaw/inventory/suppliers');

        $response->assertStatus(200)
            ->assertJsonPath('data.suppliers.0.payment_terms_days', 15);
    }

    public function test_expense_create_accepts_supplier_id_and_includes_it_in_response(): void
    {
        $supplier = Supplier::factory()->create(['user_id' => $this->owner->user_id, 'name' => 'ACME']);
        $bank = Bank::create([
            'bank_name' => 'BPI', 'account_name' => 'Main', 'account_number' => '1',
            'account_type' => Bank::TYPE_CHECKING, 'opening_balance' => 50000, 'balance' => 50000,
        ]);
        ExpenseCategory::create(['name' => 'Supplies', 'status' => 1, 'created_by' => $this->owner->id]);

        $response = $this->withHeader('Authorization', "Bearer {$this->writeToken}")
            ->postJson('/api/v1/openclaw/expenses', [
                'amount' => 1500,
                'payee' => 'ACME',
                'expense_date' => now()->toDateString(),
                'bank_id' => $bank->id,
                'category' => 'Supplies',
                'supplier_id' => $supplier->id,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.expense.supplier_id', $supplier->id)
            ->assertJsonPath('data.expense.supplier_name', 'ACME');
    }
}
