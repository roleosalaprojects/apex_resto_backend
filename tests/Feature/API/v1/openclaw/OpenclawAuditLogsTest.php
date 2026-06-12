<?php

namespace Tests\Feature\API\v1\openclaw;

use App\Models\ApiToken;
use App\Models\Employees\Role;
use App\Models\Reports\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpenclawAuditLogsTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected User $otherOwner;

    protected string $readToken;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::factory()->admin()->create();

        $this->owner = User::factory()->create(['role_id' => $role->id]);
        $this->owner->forceFill(['user_id' => $this->owner->id])->save();

        $this->otherOwner = User::factory()->create(['role_id' => $role->id]);
        $this->otherOwner->forceFill(['user_id' => $this->otherOwner->id])->save();

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

    private function makeLog(int $actorUserId, array $overrides = []): AuditLog
    {
        return AuditLog::create(array_merge([
            'user_id' => $actorUserId,
            'auditable_type' => 'App\\Models\\Accounting\\Bank',
            'auditable_id' => 1,
            'event' => 'updated',
            'source' => 'web',
            'old_values' => ['balance' => 100],
            'new_values' => ['balance' => 200],
        ], $overrides));
    }

    public function test_index_returns_entries_for_actors_in_current_tenant_only(): void
    {
        $mine = $this->makeLog($this->owner->id);
        $foreign = $this->makeLog($this->otherOwner->id);

        $response = $this->withHeader('Authorization', "Bearer {$this->readToken}")
            ->getJson('/api/v1/openclaw/audit-logs');

        $response->assertStatus(200);
        $ids = collect($response->json('data.entries'))->pluck('id')->all();
        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($foreign->id, $ids);
    }

    public function test_index_filters_by_event_and_source(): void
    {
        $this->makeLog($this->owner->id, ['event' => 'created', 'source' => 'openclaw']);
        $this->makeLog($this->owner->id, ['event' => 'updated', 'source' => 'web']);
        $this->makeLog($this->owner->id, ['event' => 'deleted', 'source' => 'openclaw']);

        $response = $this->withHeader('Authorization', "Bearer {$this->readToken}")
            ->getJson('/api/v1/openclaw/audit-logs?source=openclaw&event=created');

        $response->assertStatus(200);
        $entries = $response->json('data.entries');
        $this->assertCount(1, $entries);
        $this->assertSame('created', $entries[0]['event']);
        $this->assertSame('openclaw', $entries[0]['source']);
    }

    public function test_index_paginates_via_cursor(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->makeLog($this->owner->id);
        }

        $page1 = $this->withHeader('Authorization', "Bearer {$this->readToken}")
            ->getJson('/api/v1/openclaw/audit-logs?limit=2');

        $page1->assertStatus(200);
        $this->assertCount(2, $page1->json('data.entries'));
        $cursor = $page1->json('data.next_cursor');
        $this->assertNotNull($cursor);

        $page2 = $this->withHeader('Authorization', "Bearer {$this->readToken}")
            ->getJson("/api/v1/openclaw/audit-logs?limit=2&cursor={$cursor}");

        $page2->assertStatus(200);
        $page2Ids = collect($page2->json('data.entries'))->pluck('id')->all();
        $page1Ids = collect($page1->json('data.entries'))->pluck('id')->all();
        $this->assertEmpty(array_intersect($page1Ids, $page2Ids), 'pages should not overlap');
    }

    public function test_index_shortens_auditable_type_in_presentation(): void
    {
        $this->makeLog($this->owner->id, ['auditable_type' => 'App\\Models\\Accounting\\Bank']);

        $response = $this->withHeader('Authorization', "Bearer {$this->readToken}")
            ->getJson('/api/v1/openclaw/audit-logs');

        $response->assertStatus(200)
            ->assertJsonPath('data.entries.0.auditable_type', 'Bank');
    }
}
