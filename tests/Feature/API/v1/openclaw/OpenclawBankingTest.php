<?php

namespace Tests\Feature\API\v1\openclaw;

use App\Models\Accounting\Bank;
use App\Models\Accounting\BankTransaction;
use App\Models\ApiToken;
use App\Models\Employees\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpenclawBankingTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected string $plainToken;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::factory()->admin()->create();
        $this->owner = User::factory()->create(['role_id' => $role->id]);
        $this->owner->forceFill(['user_id' => $this->owner->id])->save();

        $this->plainToken = ApiToken::generatePlainToken();
        ApiToken::create([
            'user_id' => $this->owner->user_id,
            'name' => 'Test Bot',
            'token' => ApiToken::hashToken($this->plainToken),
        ]);
    }

    private function authed(): self
    {
        return $this->withHeader('Authorization', "Bearer {$this->plainToken}");
    }

    public function test_accounts_endpoint_returns_all_banks(): void
    {
        Bank::create([
            'bank_name' => 'BPI',
            'account_name' => 'Main Checking',
            'account_number' => '1234567890',
            'account_type' => Bank::TYPE_CHECKING,
            'opening_balance' => 100000,
            'balance' => 125000,
        ]);
        Bank::create([
            'bank_name' => 'GCash',
            'account_name' => 'GCash Wallet',
            'account_number' => '09171234567',
            'account_type' => Bank::TYPE_EWALLET,
            'opening_balance' => 5000,
            'balance' => 7500,
        ]);

        $response = $this->authed()->getJson('/api/v1/openclaw/banks/accounts');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'accounts' => [
                        '*' => [
                            'id', 'bank_name', 'account_name', 'account_number',
                            'account_type', 'account_type_name',
                            'opening_balance', 'balance',
                        ],
                    ],
                    'account_types',
                ],
            ]);

        $this->assertCount(2, $response->json('data.accounts'));
    }

    public function test_accounts_endpoint_filters_by_account_type(): void
    {
        Bank::create([
            'bank_name' => 'BPI', 'account_name' => 'Checking', 'account_number' => 'a',
            'account_type' => Bank::TYPE_CHECKING, 'opening_balance' => 0, 'balance' => 0,
        ]);
        Bank::create([
            'bank_name' => 'GCash', 'account_name' => 'EWallet', 'account_number' => 'b',
            'account_type' => Bank::TYPE_EWALLET, 'opening_balance' => 0, 'balance' => 0,
        ]);

        $response = $this->authed()->getJson('/api/v1/openclaw/banks/accounts?account_type='.Bank::TYPE_EWALLET);

        $response->assertStatus(200);
        $accounts = $response->json('data.accounts');
        $this->assertCount(1, $accounts);
        $this->assertSame('GCash', $accounts[0]['bank_name']);
    }

    public function test_summary_endpoint_aggregates_totals_by_account_type(): void
    {
        Bank::create([
            'bank_name' => 'A', 'account_name' => 'A', 'account_number' => 'a',
            'account_type' => Bank::TYPE_CHECKING, 'opening_balance' => 0, 'balance' => 1000,
        ]);
        Bank::create([
            'bank_name' => 'B', 'account_name' => 'B', 'account_number' => 'b',
            'account_type' => Bank::TYPE_CHECKING, 'opening_balance' => 0, 'balance' => 2500,
        ]);
        Bank::create([
            'bank_name' => 'C', 'account_name' => 'C', 'account_number' => 'c',
            'account_type' => Bank::TYPE_EWALLET, 'opening_balance' => 0, 'balance' => 750,
        ]);

        $response = $this->authed()->getJson('/api/v1/openclaw/banks/summary');

        $response->assertStatus(200)
            ->assertJsonPath('data.totals.account_count', 3)
            ->assertJsonPath('data.totals.total_balance', 4250)
            ->assertJsonPath('data.by_account_type.Checking.accounts', 2)
            ->assertJsonPath('data.by_account_type.Checking.total_balance', 3500)
            ->assertJsonPath('data.by_account_type.E-Wallet.accounts', 1)
            ->assertJsonPath('data.by_account_type.E-Wallet.total_balance', 750);
    }

    public function test_transactions_endpoint_returns_recent_transactions_with_filters(): void
    {
        $bank = Bank::create([
            'bank_name' => 'BPI', 'account_name' => 'Main', 'account_number' => 'x',
            'account_type' => Bank::TYPE_CHECKING, 'opening_balance' => 0, 'balance' => 0,
        ]);

        BankTransaction::create([
            'reference_number' => BankTransaction::generateReferenceNumber(),
            'bank_id' => $bank->id,
            'type' => BankTransaction::TYPE_DEPOSIT,
            'amount' => 1000,
            'balance_before' => 0,
            'balance_after' => 1000,
            'description' => 'Test deposit',
            'transaction_date' => now()->toDateString(),
            'created_by' => $this->owner->id,
        ]);
        BankTransaction::create([
            'reference_number' => BankTransaction::generateReferenceNumber(),
            'bank_id' => $bank->id,
            'type' => BankTransaction::TYPE_WITHDRAWAL,
            'amount' => 250,
            'balance_before' => 1000,
            'balance_after' => 750,
            'description' => 'Test withdrawal',
            'transaction_date' => now()->toDateString(),
            'created_by' => $this->owner->id,
        ]);

        $response = $this->authed()->getJson('/api/v1/openclaw/banks/transactions');

        $response->assertStatus(200);
        $this->assertSame(2, count($response->json('data.transactions')));

        // Filter by type=deposit only.
        $depositOnly = $this->authed()->getJson('/api/v1/openclaw/banks/transactions?type='.BankTransaction::TYPE_DEPOSIT);
        $depositOnly->assertStatus(200);
        $this->assertSame(1, count($depositOnly->json('data.transactions')));
        $this->assertSame('Deposit', $depositOnly->json('data.transactions.0.type_name'));
    }

    public function test_balances_endpoint_returns_lean_id_name_balance(): void
    {
        Bank::create([
            'bank_name' => 'BPI', 'account_name' => 'Main', 'account_number' => 'a',
            'account_type' => Bank::TYPE_CHECKING, 'opening_balance' => 0, 'balance' => 12500.5,
        ]);
        Bank::create([
            'bank_name' => 'GCash', 'account_name' => 'Wallet', 'account_number' => 'b',
            'account_type' => Bank::TYPE_EWALLET, 'opening_balance' => 0, 'balance' => 750,
        ]);

        $response = $this->authed()->getJson('/api/v1/openclaw/banks/balances');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'as_of',
                    'total_balance',
                    'accounts' => [
                        '*' => ['id', 'bank_name', 'account_name', 'account_type_name', 'balance'],
                    ],
                ],
            ])
            ->assertJsonPath('data.total_balance', 13250.5);
        $this->assertSame(2, count($response->json('data.accounts')));
    }

    public function test_banking_endpoints_require_a_valid_token(): void
    {
        $this->getJson('/api/v1/openclaw/banks/balances')->assertStatus(401);
        $this->getJson('/api/v1/openclaw/banks/accounts')->assertStatus(401);
        $this->getJson('/api/v1/openclaw/banks/summary')->assertStatus(401);
        $this->getJson('/api/v1/openclaw/banks/transactions')->assertStatus(401);
    }
}
