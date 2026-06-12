<?php

namespace Tests\Feature\API\v1\openclaw;

use App\Models\Accounting\Bank;
use App\Models\ApiToken;
use App\Models\CustomerRelations\Customer;
use App\Models\CustomerRelations\CustomerCreditTransaction;
use App\Models\Employees\Role;
use App\Models\InventoryManagement\Purchase;
use App\Models\InventoryManagement\Supplier;
use App\Models\Settings\Store;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpenclawAlertsFeedTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected User $otherOwner;

    protected Store $store;

    protected Supplier $supplier;

    protected string $readToken;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::factory()->admin()->create();
        $this->owner = User::factory()->create(['role_id' => $role->id]);
        $this->owner->forceFill(['user_id' => $this->owner->id])->save();

        $this->otherOwner = User::factory()->create(['role_id' => $role->id]);
        $this->otherOwner->forceFill(['user_id' => $this->otherOwner->id])->save();

        $this->store = Store::factory()->create(['user_id' => $this->owner->user_id]);
        $this->supplier = Supplier::factory()->create(['user_id' => $this->owner->user_id]);

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

    public function test_alerts_bundles_three_feeds(): void
    {
        // Bank below threshold
        $lowBank = Bank::create([
            'bank_name' => 'GCash',
            'account_name' => 'Wallet',
            'account_number' => '1',
            'account_type' => Bank::TYPE_EWALLET,
            'opening_balance' => 0,
            'balance' => 3_000,
            'low_balance_threshold' => 5_000,
        ]);
        // Bank healthy (not in feed)
        Bank::create([
            'bank_name' => 'BPI',
            'account_name' => 'Main',
            'account_number' => '2',
            'account_type' => Bank::TYPE_CHECKING,
            'opening_balance' => 0,
            'balance' => 200_000,
            'low_balance_threshold' => 50_000,
        ]);

        // Pending PO past the age threshold
        $oldPO = Purchase::factory()->create([
            'user_id' => $this->owner->user_id,
            'supplier_id' => $this->supplier->id,
            'store_id' => $this->store->id,
            'approval_status' => Purchase::APPROVAL_PENDING,
            'created_at' => Carbon::now()->subDays(7),
        ]);
        // Pending PO fresh (not in feed)
        Purchase::factory()->create([
            'user_id' => $this->owner->user_id,
            'supplier_id' => $this->supplier->id,
            'store_id' => $this->store->id,
            'approval_status' => Purchase::APPROVAL_PENDING,
            'created_at' => Carbon::now(),
        ]);

        // Overdue credit customer
        $overdueCustomer = Customer::factory()->create([
            'user_id' => $this->owner->user_id,
            'credit_balance' => 1_500,
            'credit_limit' => 5_000,
            'credit_term_days' => 30,
        ]);
        CustomerCreditTransaction::create([
            'customer_id' => $overdueCustomer->id,
            'type' => 1,
            'amount' => 1_500,
            'balance_after' => 1_500,
            'due_date' => Carbon::now()->subDays(15)->toDateString(),
            'user_id' => $this->owner->user_id,
            'store_id' => $this->store->id,
        ]);

        // Foreign-tenant customer (must NOT leak)
        $foreignCustomer = Customer::factory()->create([
            'user_id' => $this->otherOwner->user_id,
            'credit_balance' => 9_999,
        ]);
        CustomerCreditTransaction::create([
            'customer_id' => $foreignCustomer->id,
            'type' => 1,
            'amount' => 9_999,
            'balance_after' => 9_999,
            'due_date' => Carbon::now()->subDays(30)->toDateString(),
            'user_id' => $this->otherOwner->user_id,
            'store_id' => $this->store->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->readToken}")
            ->getJson('/api/v1/openclaw/alerts?approval_age_days=3');

        $response->assertStatus(200);

        $bankIds = collect($response->json('data.banks_below_threshold'))->pluck('bank_id')->all();
        $this->assertSame([$lowBank->id], $bankIds);

        $poIds = collect($response->json('data.pending_approvals'))->pluck('purchase_id')->all();
        $this->assertSame([$oldPO->id], $poIds);

        $customerIds = collect($response->json('data.overdue_credit'))->pluck('customer_id')->all();
        $this->assertContains($overdueCustomer->id, $customerIds);
        $this->assertNotContains($foreignCustomer->id, $customerIds);
    }
}
