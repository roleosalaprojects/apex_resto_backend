<?php

namespace Tests\Feature\Admin;

use App\Models\Accounting\Bank;
use App\Models\ApiToken;
use App\Models\Employees\Role;
use App\Models\Reports\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected User $clerk;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::factory()->admin()->create();
        $this->owner = User::factory()->create(['role_id' => $adminRole->id]);
        $this->owner->forceFill(['user_id' => $this->owner->id])->save();

        // A role with reports access but NOT settings — should be blocked.
        $clerkRole = Role::factory()->create([
            'name' => 'Reports Clerk',
            'sls' => true,
            'sttngs' => false,
        ]);
        $this->clerk = User::factory()->create([
            'role_id' => $clerkRole->id,
            'user_id' => $this->owner->id,
        ]);
    }

    public function test_index_is_blocked_for_users_without_sttngs(): void
    {
        $this->actingAs($this->clerk)
            ->get(route('audit_logs.index'))
            ->assertStatus(403);
    }

    public function test_index_is_accessible_to_users_with_sttngs(): void
    {
        $this->actingAs($this->owner)
            ->get(route('audit_logs.index'))
            ->assertOk();
    }

    public function test_table_filters_by_source(): void
    {
        AuditLog::query()->delete();
        $this->seedLog(['source' => 'web', 'auditable_type' => Bank::class, 'auditable_id' => 1]);
        $this->seedLog(['source' => 'openclaw', 'auditable_type' => Bank::class, 'auditable_id' => 2]);
        $this->seedLog(['source' => 'console', 'auditable_type' => Bank::class, 'auditable_id' => 3]);

        $response = $this->actingAs($this->owner)
            ->get(route('audit_logs.table', ['source' => 'openclaw']));

        $response->assertOk();
        // Only the openclaw row's auditable_id (2) should appear.
        $response->assertSee('>2<', false);
        $response->assertDontSee('>1<', false);
        $response->assertDontSee('>3<', false);
    }

    public function test_table_filters_by_api_token_id(): void
    {
        AuditLog::query()->delete();
        $token = ApiToken::create([
            'user_id' => $this->owner->id,
            'name' => 'Bot',
            'token' => ApiToken::hashToken(ApiToken::generatePlainToken()),
        ]);
        $otherToken = ApiToken::create([
            'user_id' => $this->owner->id,
            'name' => 'Other Bot',
            'token' => ApiToken::hashToken(ApiToken::generatePlainToken()),
        ]);

        AuditLog::query()->delete();
        $this->seedLog(['source' => 'openclaw', 'api_token_id' => $token->id, 'auditable_id' => 11]);
        $this->seedLog(['source' => 'openclaw', 'api_token_id' => $otherToken->id, 'auditable_id' => 22]);

        $response = $this->actingAs($this->owner)
            ->get(route('audit_logs.table', ['api_token_id' => $token->id]));

        $response->assertOk();
        $response->assertSee('>11<', false);
        $response->assertDontSee('>22<', false);
    }

    public function test_table_renders_token_name_for_openclaw_rows(): void
    {
        AuditLog::query()->delete();
        $token = ApiToken::create([
            'user_id' => $this->owner->id,
            'name' => 'Claudette-Test-Token',
            'token' => ApiToken::hashToken(ApiToken::generatePlainToken()),
        ]);

        AuditLog::query()->delete();
        $this->seedLog(['source' => 'openclaw', 'api_token_id' => $token->id, 'auditable_id' => 99]);

        $response = $this->actingAs($this->owner)
            ->get(route('audit_logs.table'));

        $response->assertOk();
        $response->assertSee('Claudette-Test-Token');
    }

    public function test_table_endpoint_is_also_blocked_for_users_without_sttngs(): void
    {
        $this->actingAs($this->clerk)
            ->get(route('audit_logs.table'))
            ->assertStatus(403);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function seedLog(array $attributes): AuditLog
    {
        return AuditLog::create(array_merge([
            'user_id' => $this->owner->id,
            'auditable_type' => Bank::class,
            'auditable_id' => 1,
            'event' => 'updated',
            'source' => 'web',
            'api_token_id' => null,
            'old_values' => [],
            'new_values' => ['balance' => 1],
        ], $attributes));
    }
}
