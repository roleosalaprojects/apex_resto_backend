<?php

namespace Tests\Feature\API\v1\openclaw;

use App\Models\Accounting\Bank;
use App\Models\Accounting\BankTransaction;
use App\Models\ApiToken;
use App\Models\Employees\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpenclawBankAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Bank $bank;

    protected string $adjustToken;

    protected string $readToken;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::factory()->admin()->create();
        $this->owner = User::factory()->create(['role_id' => $role->id]);
        $this->owner->forceFill(['user_id' => $this->owner->id])->save();

        $this->bank = Bank::create([
            'bank_name' => 'BDO',
            'account_name' => 'Leteres',
            'account_number' => '041078001670',
            'account_type' => Bank::TYPE_CHECKING,
            'opening_balance' => 2906004.77,
            'balance' => 2906004.77,
        ]);

        $this->adjustToken = $this->mintToken(['openclaw:read', 'openclaw:banks:adjust']);
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

    public function test_adjustment_via_new_balance_decreases_and_records_withdrawal(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adjustToken}")
            ->postJson("/api/v1/openclaw/banks/{$this->bank->id}/adjustment", [
                'new_balance' => 1855633.09,
                'reason' => 'Passbook reconciliation 2026-05-10',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.bank.id', $this->bank->id)
            ->assertJsonPath('data.adjustment_transaction.type', 'Withdrawal');

        $this->assertEqualsWithDelta(2906004.77, $response->json('data.bank.old_balance'), 0.001);
        $this->assertEqualsWithDelta(1855633.09, $response->json('data.bank.new_balance'), 0.001);
        $this->assertEqualsWithDelta(1050371.68, $response->json('data.adjustment_transaction.amount'), 0.001);
        $this->assertEqualsWithDelta(-1050371.68, $response->json('data.adjustment_transaction.delta'), 0.001);

        // Bank persisted.
        $this->assertEqualsWithDelta(1855633.09, (float) $this->bank->fresh()->balance, 0.001);

        // Transaction has ADJ- prefix and stored reason.
        $tx = BankTransaction::find($response->json('data.adjustment_transaction.id'));
        $this->assertStringStartsWith('ADJ-', $tx->reference_number);
        $this->assertStringContainsString('Passbook reconciliation 2026-05-10', $tx->description);
        $this->assertSame(BankTransaction::TYPE_WITHDRAWAL, $tx->type);
    }

    public function test_adjustment_via_new_balance_increases_and_records_deposit(): void
    {
        $this->withHeader('Authorization', "Bearer {$this->adjustToken}")
            ->postJson("/api/v1/openclaw/banks/{$this->bank->id}/adjustment", [
                'new_balance' => 3000000.00,
                'reason' => 'Found a missed deposit',
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.adjustment_transaction.type', 'Deposit');

        $this->assertEqualsWithDelta(3000000.0, (float) $this->bank->fresh()->balance, 0.001);
    }

    public function test_adjustment_via_amount_delta_is_supported(): void
    {
        // Negative delta — withdrawal.
        $response = $this->withHeader('Authorization', "Bearer {$this->adjustToken}")
            ->postJson("/api/v1/openclaw/banks/{$this->bank->id}/adjustment", [
                'amount' => -500.00,
                'reason' => 'Bank fee not previously recorded',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.adjustment_transaction.type', 'Withdrawal');
        $this->assertEqualsWithDelta(2906004.77 - 500, (float) $this->bank->fresh()->balance, 0.001);
    }

    public function test_zero_delta_is_a_noop(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adjustToken}")
            ->postJson("/api/v1/openclaw/banks/{$this->bank->id}/adjustment", [
                'new_balance' => 2906004.77,
                'reason' => 'Already reconciled',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.adjustment_transaction', null);

        // No bank_transaction row was created.
        $this->assertSame(0, BankTransaction::count());
        $this->assertEqualsWithDelta(2906004.77, (float) $this->bank->fresh()->balance, 0.001);
    }

    public function test_must_provide_exactly_one_of_new_balance_or_amount(): void
    {
        $auth = ['Authorization' => "Bearer {$this->adjustToken}"];

        // Neither.
        $this->withHeaders($auth)->postJson("/api/v1/openclaw/banks/{$this->bank->id}/adjustment", [
            'reason' => 'reason',
        ])->assertStatus(422)->assertJsonValidationErrors('amount');

        // Both.
        $this->withHeaders($auth)->postJson("/api/v1/openclaw/banks/{$this->bank->id}/adjustment", [
            'new_balance' => 100,
            'amount' => 100,
            'reason' => 'reason',
        ])->assertStatus(422)->assertJsonValidationErrors('amount');
    }

    public function test_reason_is_required(): void
    {
        $this->withHeader('Authorization', "Bearer {$this->adjustToken}")
            ->postJson("/api/v1/openclaw/banks/{$this->bank->id}/adjustment", [
                'new_balance' => 100,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');
    }

    public function test_endpoint_requires_banks_adjust_ability(): void
    {
        // openclaw:banks:write is NOT enough — adjust is its own ability.
        $writeOnly = $this->mintToken(['openclaw:read', 'openclaw:banks:write']);

        $this->withHeader('Authorization', "Bearer {$writeOnly}")
            ->postJson("/api/v1/openclaw/banks/{$this->bank->id}/adjustment", [
                'new_balance' => 100,
                'reason' => 'reason',
            ])
            ->assertStatus(403)
            ->assertJsonPath('message', 'This token is missing the required ability: openclaw:banks:adjust.');

        // Read-only also blocked.
        $this->withHeader('Authorization', "Bearer {$this->readToken}")
            ->postJson("/api/v1/openclaw/banks/{$this->bank->id}/adjustment", [
                'new_balance' => 100,
                'reason' => 'reason',
            ])
            ->assertStatus(403);
    }

    public function test_unauthenticated_returns_401(): void
    {
        $this->postJson("/api/v1/openclaw/banks/{$this->bank->id}/adjustment", [
            'new_balance' => 100,
            'reason' => 'reason',
        ])->assertStatus(401);
    }
}
