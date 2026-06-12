<?php

namespace Tests\Feature\API\v1\openclaw;

use App\Models\Accounting\Bank;
use App\Models\Accounting\BankTransaction;
use App\Models\ApiToken;
use App\Models\Employees\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpenclawBankMovementsTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected string $writeToken;

    protected string $readToken;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::factory()->admin()->create();
        $this->owner = User::factory()->create(['role_id' => $role->id]);
        $this->owner->forceFill(['user_id' => $this->owner->id])->save();

        $this->writeToken = $this->mintToken(['openclaw:read', 'openclaw:banks:movements']);
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

    private function makeBank(float $balance, string $name = 'BPI'): Bank
    {
        return Bank::create([
            'bank_name' => $name,
            'account_name' => "{$name} Main",
            'account_number' => '0001',
            'account_type' => Bank::TYPE_CHECKING,
            'opening_balance' => $balance,
            'balance' => $balance,
        ]);
    }

    public function test_deposit_increases_balance_and_creates_typed_transaction(): void
    {
        $bank = $this->makeBank(1_000.00);

        $response = $this->withHeader('Authorization', "Bearer {$this->writeToken}")
            ->postJson("/api/v1/openclaw/banks/{$bank->id}/deposit", [
                'amount' => 250.00,
                'payee' => 'Cash drop from POS',
                'description' => 'End-of-day deposit',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.transaction.type', BankTransaction::TYPE_DEPOSIT);
        $this->assertEqualsWithDelta(1_250.00, (float) $response->json('data.bank.balance'), 0.001);

        $this->assertEqualsWithDelta(1_250.00, (float) $bank->fresh()->balance, 0.001);
        $this->assertSame(1, BankTransaction::where('bank_id', $bank->id)->where('type', BankTransaction::TYPE_DEPOSIT)->count());
    }

    public function test_withdrawal_decreases_balance(): void
    {
        $bank = $this->makeBank(5_000.00);

        $response = $this->withHeader('Authorization', "Bearer {$this->writeToken}")
            ->postJson("/api/v1/openclaw/banks/{$bank->id}/withdrawal", [
                'amount' => 750.50,
                'payee' => 'Petty cash',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.transaction.type', BankTransaction::TYPE_WITHDRAWAL);
        $this->assertEqualsWithDelta(4_249.50, (float) $response->json('data.bank.balance'), 0.001);

        $this->assertEqualsWithDelta(4_249.50, (float) $bank->fresh()->balance, 0.001);
    }

    public function test_withdrawal_rejects_overdraft(): void
    {
        $bank = $this->makeBank(100.00);

        $this->withHeader('Authorization', "Bearer {$this->writeToken}")
            ->postJson("/api/v1/openclaw/banks/{$bank->id}/withdrawal", [
                'amount' => 250.00,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('amount');

        $this->assertEqualsWithDelta(100.00, (float) $bank->fresh()->balance, 0.001);
    }

    public function test_transfer_creates_two_linked_transactions_and_updates_both_balances(): void
    {
        $from = $this->makeBank(10_000.00, 'BDO');
        $to = $this->makeBank(2_000.00, 'BPI');

        $response = $this->withHeader('Authorization', "Bearer {$this->writeToken}")
            ->postJson("/api/v1/openclaw/banks/{$from->id}/transfer", [
                'transfer_to_bank_id' => $to->id,
                'amount' => 3_500.00,
                'description' => 'Move to operations',
            ]);

        $response->assertStatus(200);
        $this->assertEqualsWithDelta(6_500.00, (float) $response->json('data.from_bank.balance'), 0.001);
        $this->assertEqualsWithDelta(5_500.00, (float) $response->json('data.to_bank.balance'), 0.001);

        $this->assertEqualsWithDelta(6_500.00, (float) $from->fresh()->balance, 0.001);
        $this->assertEqualsWithDelta(5_500.00, (float) $to->fresh()->balance, 0.001);

        $this->assertSame(1, BankTransaction::where('bank_id', $from->id)->where('type', BankTransaction::TYPE_TRANSFER_OUT)->count());
        $this->assertSame(1, BankTransaction::where('bank_id', $to->id)->where('type', BankTransaction::TYPE_TRANSFER_IN)->count());
    }

    public function test_transfer_rejects_same_bank_destination(): void
    {
        $bank = $this->makeBank(1_000.00);

        $this->withHeader('Authorization', "Bearer {$this->writeToken}")
            ->postJson("/api/v1/openclaw/banks/{$bank->id}/transfer", [
                'transfer_to_bank_id' => $bank->id,
                'amount' => 100,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('transfer_to_bank_id');
    }

    public function test_movement_endpoints_require_banks_movements_ability(): void
    {
        $from = $this->makeBank(1_000.00, 'BDO');
        $to = $this->makeBank(0.00, 'BPI');

        $this->withHeader('Authorization', "Bearer {$this->readToken}")
            ->postJson("/api/v1/openclaw/banks/{$from->id}/deposit", ['amount' => 100])
            ->assertStatus(403);

        $this->withHeader('Authorization', "Bearer {$this->readToken}")
            ->postJson("/api/v1/openclaw/banks/{$from->id}/withdrawal", ['amount' => 100])
            ->assertStatus(403);

        $this->withHeader('Authorization', "Bearer {$this->readToken}")
            ->postJson("/api/v1/openclaw/banks/{$from->id}/transfer", [
                'transfer_to_bank_id' => $to->id, 'amount' => 100,
            ])
            ->assertStatus(403);
    }
}
