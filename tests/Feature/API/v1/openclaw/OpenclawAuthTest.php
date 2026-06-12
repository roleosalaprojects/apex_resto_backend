<?php

namespace Tests\Feature\API\v1\openclaw;

use App\Models\ApiToken;
use App\Models\Employees\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpenclawAuthTest extends TestCase
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

    public function test_missing_bearer_token_is_unauthenticated(): void
    {
        $this->getJson('/api/v1/openclaw/snapshot')->assertStatus(401);
    }

    public function test_invalid_bearer_token_is_unauthenticated(): void
    {
        $this->withHeader('Authorization', 'Bearer not-a-real-token')
            ->getJson('/api/v1/openclaw/snapshot')
            ->assertStatus(401);
    }

    public function test_revoked_bearer_token_is_unauthenticated(): void
    {
        ApiToken::query()->update(['revoked_at' => now()]);

        $this->withHeader('Authorization', "Bearer {$this->plainToken}")
            ->getJson('/api/v1/openclaw/snapshot')
            ->assertStatus(401);
    }

    public function test_valid_bearer_token_authenticates_and_updates_last_used_at(): void
    {
        $this->assertNull(ApiToken::query()->first()->last_used_at);

        $this->withHeader('Authorization', "Bearer {$this->plainToken}")
            ->getJson('/api/v1/openclaw/snapshot')
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.tenant_user_id', $this->owner->user_id);

        $this->assertNotNull(ApiToken::query()->first()->last_used_at);
    }

    public function test_token_for_one_tenant_cannot_see_another_tenants_data(): void
    {
        $role = Role::factory()->admin()->create();
        $otherOwner = User::factory()->create(['role_id' => $role->id]);
        $otherOwner->forceFill(['user_id' => $otherOwner->id])->save();

        $response = $this->withHeader('Authorization', "Bearer {$this->plainToken}")
            ->getJson('/api/v1/openclaw/snapshot');

        $response->assertStatus(200);
        $this->assertSame($this->owner->user_id, $response->json('data.tenant_user_id'));
        $this->assertNotSame($otherOwner->user_id, $response->json('data.tenant_user_id'));
    }
}
