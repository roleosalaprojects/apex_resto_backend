<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\OpenclawTokens\Index;
use App\Models\ApiToken;
use App\Models\Employees\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OpenclawTokensPageTest extends TestCase
{
    use RefreshDatabase;

    protected Role $role;

    protected function setUp(): void
    {
        parent::setUp();
        $this->role = Role::factory()->admin()->create();
    }

    private function makeOwner(): User
    {
        $owner = User::factory()->create(['role_id' => $this->role->id]);
        $owner->forceFill(['user_id' => $owner->id])->save();

        return $owner;
    }

    private function makeEmployeeOf(User $owner): User
    {
        return User::factory()->create([
            'role_id' => $this->role->id,
            'user_id' => $owner->user_id,
        ]);
    }

    public function test_owner_can_mint_a_new_token_and_see_the_plain_value_once(): void
    {
        $owner = $this->makeOwner();
        $this->actingAs($owner);

        Livewire::test(Index::class)
            ->set('name', 'Production bot')
            ->call('create')
            ->assertSet('name', '')
            ->assertSet('newPlainToken', fn ($v) => is_string($v) && strlen($v) === 64);

        $this->assertDatabaseHas('api_tokens', [
            'user_id' => $owner->user_id,
            'name' => 'Production bot',
            'revoked_at' => null,
        ]);
    }

    public function test_owner_can_revoke_an_active_token(): void
    {
        $owner = $this->makeOwner();
        $this->actingAs($owner);

        $token = ApiToken::create([
            'user_id' => $owner->user_id,
            'name' => 'Bot',
            'token' => ApiToken::hashToken(ApiToken::generatePlainToken()),
        ]);

        Livewire::test(Index::class)
            ->call('revoke', $token->id);

        $this->assertNotNull($token->fresh()->revoked_at);
    }

    public function test_owner_cannot_revoke_a_token_belonging_to_another_tenant(): void
    {
        $owner = $this->makeOwner();
        $otherOwner = $this->makeOwner();
        $this->actingAs($owner);

        $foreignToken = ApiToken::create([
            'user_id' => $otherOwner->user_id,
            'name' => 'Other',
            'token' => ApiToken::hashToken(ApiToken::generatePlainToken()),
        ]);

        Livewire::test(Index::class)
            ->call('revoke', $foreignToken->id);

        $this->assertNull($foreignToken->fresh()->revoked_at);
    }

    public function test_non_owner_employee_is_blocked_from_the_page(): void
    {
        $owner = $this->makeOwner();
        $employee = $this->makeEmployeeOf($owner);

        $this->actingAs($employee)
            ->get('/admin/openclaw-tokens')
            ->assertStatus(403);
    }
}
