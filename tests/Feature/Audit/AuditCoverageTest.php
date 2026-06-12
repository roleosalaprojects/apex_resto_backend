<?php

namespace Tests\Feature\Audit;

use App\Models\Accounting\Bank;
use App\Models\Accounting\BankTransaction;
use App\Models\Accounting\Expense;
use App\Models\Accounting\ExpenseCategory;
use App\Models\ApiToken;
use App\Models\BusinessSettings;
use App\Models\Employees\Role;
use App\Models\InventoryManagement\Purchase;
use App\Models\InventoryManagement\PurchasePayment;
use App\Models\InventoryManagement\Supplier;
use App\Models\Reports\AuditLog;
use App\Models\Settings\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditCoverageTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Bank $bank;

    protected Store $store;

    protected ExpenseCategory $category;

    protected string $writeToken;

    protected ApiToken $apiToken;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::factory()->admin()->create();
        $this->owner = User::factory()->create(['role_id' => $role->id]);
        $this->owner->forceFill(['user_id' => $this->owner->id])->save();

        $this->bank = Bank::create([
            'bank_name' => 'BPI', 'account_name' => 'Main', 'account_number' => '1',
            'account_type' => Bank::TYPE_CHECKING, 'opening_balance' => 100000, 'balance' => 100000,
        ]);

        $this->store = Store::factory()->create(['user_id' => $this->owner->user_id]);

        $this->category = ExpenseCategory::create([
            'name' => 'Utilities', 'status' => 1, 'created_by' => $this->owner->id,
        ]);

        // The api_token row creation itself produced an audit log (ApiToken
        // is now Auditable). Remember its id so the test can scope to logs
        // that came AFTER setup, not the setup ones.
        $plain = ApiToken::generatePlainToken();
        $this->apiToken = ApiToken::create([
            'user_id' => $this->owner->user_id,
            'name' => 'Bot',
            'token' => ApiToken::hashToken($plain),
            'abilities' => [
                'openclaw:read',
                'openclaw:expenses:create',
                'openclaw:expenses:void',
                'openclaw:expenses:update',
                'openclaw:banks:write',
                'openclaw:banks:adjust',
                'openclaw:purchases:approve',
                'openclaw:purchases:pay',
                'openclaw:settings:write',
            ],
        ]);
        $this->writeToken = $plain;

        // Clear the api-token-creation audit row so subsequent assertions
        // reflect only what the test under test actually did.
        AuditLog::query()->delete();
    }

    public function test_creating_an_expense_via_openclaw_logs_with_source_openclaw_and_token_id(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->writeToken}")
            ->postJson('/api/v1/openclaw/expenses', [
                'amount' => 1250,
                'payee' => 'Meralco',
                'expense_date' => now()->toDateString(),
                'bank_id' => $this->bank->id,
                'expense_category_id' => $this->category->id,
                'description' => 'Electric bill',
            ]);

        $response->assertStatus(201);

        // Should have one Expense + one BankTransaction + one Bank (update)
        // audit row at minimum, all stamped openclaw + the bot's token id.
        $expenseLogs = AuditLog::query()
            ->where('auditable_type', Expense::class)
            ->get();
        $this->assertCount(1, $expenseLogs);

        $log = $expenseLogs->first();
        $this->assertSame('created', $log->event);
        $this->assertSame('openclaw', $log->source);
        $this->assertSame((int) $this->apiToken->id, (int) $log->api_token_id);
        $this->assertSame((int) $this->owner->id, (int) $log->user_id);
        $this->assertSame('Meralco', $log->new_values['payee'] ?? null);
        // Excluded fields (timestamps) shouldn't be stored.
        $this->assertArrayNotHasKey('created_at', $log->new_values);
    }

    public function test_void_via_openclaw_logs_an_updated_event_for_the_expense(): void
    {
        $expense = Expense::create([
            'reference_number' => Expense::generateReferenceNumber(),
            'expense_category_id' => $this->category->id,
            'bank_id' => $this->bank->id,
            'payee' => 'X', 'amount' => 100, 'expense_date' => now()->toDateString(),
            'status' => Expense::STATUS_ACTIVE, 'created_by' => $this->owner->id,
            'bank_transaction_id' => null,
        ]);
        AuditLog::query()->delete();

        $this->withHeader('Authorization', "Bearer {$this->writeToken}")
            ->postJson("/api/v1/openclaw/expenses/{$expense->id}/void", ['reason' => 'oops'])
            ->assertStatus(200);

        $log = AuditLog::query()->where('auditable_type', Expense::class)->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame('updated', $log->event);
        $this->assertSame('openclaw', $log->source);
        $this->assertSame(Expense::STATUS_VOIDED, (int) $log->new_values['status']);
        $this->assertSame('oops', $log->new_values['void_reason']);
    }

    public function test_admin_dashboard_request_does_not_create_audit_rows_unless_a_model_changes(): void
    {
        // Sanity: just hitting an endpoint shouldn't create audit rows. Only
        // model writes do.
        $this->actingAs($this->owner)->get('/admin/home')->assertStatus(200);
        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_eloquent_calls_with_no_http_request_path_are_tagged_console(): void
    {
        // PHPUnit runs in CLI; without an HTTP test method (getJson/postJson/
        // actingAs+get), there's no request path to inspect, so the trait
        // falls through to runningInConsole() and tags the source 'console'.
        AuditLog::query()->delete();

        $this->bank->update(['balance' => 12345]);

        $log = AuditLog::query()
            ->where('auditable_type', Bank::class)
            ->where('auditable_id', $this->bank->id)
            ->where('event', 'updated')
            ->latest('id')
            ->first();
        $this->assertNotNull($log);
        $this->assertSame('console', $log->source);
        $this->assertNull($log->api_token_id);
    }

    public function test_api_token_creation_is_audited(): void
    {
        AuditLog::query()->delete();

        $plain = ApiToken::generatePlainToken();
        $token = ApiToken::create([
            'user_id' => $this->owner->user_id,
            'name' => 'Audit Test Token',
            'token' => ApiToken::hashToken($plain),
        ]);

        $log = AuditLog::query()
            ->where('auditable_type', ApiToken::class)
            ->where('auditable_id', $token->id)
            ->first();
        $this->assertNotNull($log);
        $this->assertSame('created', $log->event);
        $this->assertSame('Audit Test Token', $log->new_values['name']);
        // Token hash itself must NOT appear in the audit payload.
        $this->assertArrayNotHasKey('token', $log->new_values);
    }

    public function test_audited_models_pick_up_the_trait(): void
    {
        // Sanity check the wiring — these models must boot the Auditable
        // trait. We do that by exercising create on each and looking for a
        // log row.
        AuditLog::query()->delete();

        $this->bank->update(['balance' => 99999]);
        $this->assertSame(1, AuditLog::query()->where('auditable_type', Bank::class)->count());

        AuditLog::query()->delete();
        $supplier = Supplier::factory()->create(['user_id' => $this->owner->user_id]);
        $this->assertSame(1, AuditLog::query()->where('auditable_type', Supplier::class)->where('event', 'created')->count());

        AuditLog::query()->delete();
        $settings = BusinessSettings::current();
        $settings->thresholds = ['daily_sales_floor' => 50000];
        $settings->save();
        $this->assertGreaterThanOrEqual(
            1,
            AuditLog::query()->where('auditable_type', BusinessSettings::class)->count(),
        );

        AuditLog::query()->delete();
        $tx = BankTransaction::create([
            'reference_number' => BankTransaction::generateReferenceNumber(),
            'bank_id' => $this->bank->id,
            'type' => BankTransaction::TYPE_DEPOSIT,
            'amount' => 100, 'balance_before' => 99999, 'balance_after' => 100099,
            'transaction_date' => now()->toDateString(),
            'created_by' => $this->owner->id,
        ]);
        $this->assertSame(1, AuditLog::query()->where('auditable_type', BankTransaction::class)->where('auditable_id', $tx->id)->count());

        AuditLog::query()->delete();
        $supplier2 = Supplier::factory()->create(['user_id' => $this->owner->user_id]);
        $po = Purchase::factory()->create([
            'user_id' => $this->owner->user_id, 'supplier_id' => $supplier2->id, 'store_id' => $this->store->id,
        ]);
        $this->assertSame(1, AuditLog::query()->where('auditable_type', Purchase::class)->where('auditable_id', $po->id)->count());

        AuditLog::query()->delete();
        $linkedTx = BankTransaction::create([
            'reference_number' => BankTransaction::generateReferenceNumber(),
            'bank_id' => $this->bank->id,
            'type' => BankTransaction::TYPE_WITHDRAWAL,
            'amount' => 50, 'balance_before' => 100, 'balance_after' => 50,
            'transaction_date' => now()->toDateString(),
            'created_by' => $this->owner->id,
        ]);
        AuditLog::query()->delete();
        $payment = PurchasePayment::create([
            'reference_number' => PurchasePayment::generateReferenceNumber(),
            'purchase_id' => $po->id, 'bank_id' => $this->bank->id,
            'bank_transaction_id' => $linkedTx->id,
            'amount' => 50, 'payment_date' => now()->toDateString(),
            'payment_method' => PurchasePayment::METHOD_CASH,
            'created_by' => $this->owner->id,
        ]);
        $this->assertSame(1, AuditLog::query()->where('auditable_type', PurchasePayment::class)->where('auditable_id', $payment->id)->count());
    }
}
