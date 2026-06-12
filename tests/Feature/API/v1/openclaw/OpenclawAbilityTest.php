<?php

namespace Tests\Feature\API\v1\openclaw;

use App\Models\Accounting\Bank;
use App\Models\Accounting\ExpenseCategory;
use App\Models\ApiToken;
use App\Models\Employees\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpenclawAbilityTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Bank $bank;

    protected ExpenseCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::factory()->admin()->create();
        $this->owner = User::factory()->create(['role_id' => $role->id]);
        $this->owner->forceFill(['user_id' => $this->owner->id])->save();

        $this->bank = Bank::create([
            'bank_name' => 'BPI', 'account_name' => 'Main', 'account_number' => '1',
            'account_type' => Bank::TYPE_CHECKING, 'opening_balance' => 10000, 'balance' => 10000,
        ]);
        $this->category = ExpenseCategory::create([
            'name' => 'Utilities', 'status' => 1, 'created_by' => $this->owner->id,
        ]);
    }

    private function mintToken(?array $abilities): string
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

    private function payload(): array
    {
        return [
            'amount' => 100,
            'payee' => 'Test',
            'expense_date' => now()->toDateString(),
            'bank_id' => $this->bank->id,
            'expense_category_id' => $this->category->id,
        ];
    }

    public function test_default_null_abilities_token_is_read_only(): void
    {
        $token = $this->mintToken(null);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/openclaw/snapshot')->assertStatus(200);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/openclaw/expenses', $this->payload())
            ->assertStatus(403)
            ->assertJsonPath('message', 'This token is missing the required ability: openclaw:expenses:create.');
    }

    public function test_read_only_token_cannot_create_expenses(): void
    {
        $token = $this->mintToken(['openclaw:read']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/openclaw/expenses', $this->payload())
            ->assertStatus(403);
    }

    public function test_read_only_token_can_hit_all_get_endpoints(): void
    {
        $token = $this->mintToken(['openclaw:read']);
        $auth = $this->withHeader('Authorization', "Bearer {$token}");

        $auth->getJson('/api/v1/openclaw/snapshot')->assertStatus(200);
        $auth->getJson('/api/v1/openclaw/sales/summary')->assertStatus(200);
        $auth->getJson('/api/v1/openclaw/banks/balances')->assertStatus(200);
        $auth->getJson('/api/v1/openclaw/expenses')->assertStatus(200);
        $auth->getJson('/api/v1/openclaw/expenses/categories')->assertStatus(200);
    }

    public function test_token_with_create_ability_can_post_expenses(): void
    {
        $token = $this->mintToken(['openclaw:read', 'openclaw:expenses:create']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/openclaw/expenses', $this->payload())
            ->assertStatus(201);
    }

    public function test_token_with_create_ability_only_still_blocked_from_reads(): void
    {
        // openclaw:expenses:create alone (no openclaw:read). Should be blocked from GETs.
        $token = $this->mintToken(['openclaw:expenses:create']);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/openclaw/snapshot')
            ->assertStatus(403)
            ->assertJsonPath('message', 'This token is missing the required ability: openclaw:read.');
    }

    public function test_wildcard_token_can_do_anything(): void
    {
        $token = $this->mintToken(['*']);
        $auth = $this->withHeader('Authorization', "Bearer {$token}");

        $auth->getJson('/api/v1/openclaw/snapshot')->assertStatus(200);
        $auth->postJson('/api/v1/openclaw/expenses', $this->payload())->assertStatus(201);
    }

    public function test_unauthenticated_request_returns_401_not_403(): void
    {
        // No Authorization header at all -> guard fails -> 401, before ability is even checked.
        $this->postJson('/api/v1/openclaw/expenses', $this->payload())->assertStatus(401);
        $this->getJson('/api/v1/openclaw/snapshot')->assertStatus(401);
    }
}
