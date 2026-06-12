<?php

namespace Tests\Feature\API\v1\openclaw;

use App\Models\Accounting\ExpenseCategory;
use App\Models\ApiToken;
use App\Models\Employees\Role;
use App\Models\Reports\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpenclawExpenseCategoryWriteTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected string $readToken;

    protected string $writeToken;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::factory()->admin()->create();
        $this->owner = User::factory()->create(['role_id' => $role->id]);
        $this->owner->forceFill(['user_id' => $this->owner->id])->save();

        $this->readToken = $this->mintToken(['openclaw:read']);
        $this->writeToken = $this->mintToken(['openclaw:read', 'openclaw:expense-categories:write']);
    }

    /**
     * @param  array<int, string>  $abilities
     */
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

    public function test_store_creates_a_category_with_correct_response_shape(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->writeToken}")
            ->postJson('/api/v1/openclaw/expenses/categories', [
                'name' => 'Delivery Expense',
                'description' => 'Trucking, rider payments, delivery fees',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Expense category created.',
                'data' => [
                    'category' => [
                        'name' => 'Delivery Expense',
                        'description' => 'Trucking, rider payments, delivery fees',
                        'status' => true,
                    ],
                ],
            ]);

        $this->assertDatabaseHas('expense_categories', [
            'name' => 'Delivery Expense',
            'status' => true,
            'created_by' => $this->owner->id,
        ]);
    }

    public function test_store_trims_whitespace_around_name_and_description(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->writeToken}")
            ->postJson('/api/v1/openclaw/expenses/categories', [
                'name' => '  Utilities  ',
                'description' => '  Electric, water, internet  ',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('expense_categories', ['name' => 'Utilities', 'description' => 'Electric, water, internet']);
    }

    public function test_store_accepts_null_description(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->writeToken}")
            ->postJson('/api/v1/openclaw/expenses/categories', [
                'name' => 'Office Supplies',
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('expense_categories', ['name' => 'Office Supplies', 'description' => null]);
    }

    public function test_store_returns_409_with_existing_category_on_duplicate_name(): void
    {
        $existing = ExpenseCategory::create([
            'name' => 'Utilities',
            'description' => 'Power, water, etc.',
            'status' => true,
            'created_by' => $this->owner->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->writeToken}")
            ->postJson('/api/v1/openclaw/expenses/categories', [
                'name' => 'Utilities',
                'description' => 'Different description',
            ]);

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'data' => [
                    'category' => [
                        'id' => $existing->id,
                        'name' => 'Utilities',
                        'description' => 'Power, water, etc.',
                    ],
                ],
            ]);
        $this->assertSame(1, ExpenseCategory::query()->count());
    }

    public function test_store_treats_duplicate_name_as_conflict_case_insensitively(): void
    {
        // MySQL's utf8mb4_unicode_ci collation makes 'utilities' == 'UTILITIES'
        // for VARCHAR comparisons. Sqlite (sometimes used for tests) is
        // case-insensitive by default for ASCII as well. Either way the
        // duplicate must be caught.
        ExpenseCategory::create([
            'name' => 'Utilities',
            'description' => null,
            'status' => true,
            'created_by' => $this->owner->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->writeToken}")
            ->postJson('/api/v1/openclaw/expenses/categories', [
                'name' => 'utilities',
            ]);

        $response->assertStatus(409);
    }

    public function test_store_rejects_missing_name(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->writeToken}")
            ->postJson('/api/v1/openclaw/expenses/categories', [
                'description' => 'No name here',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_requires_the_expense_categories_write_ability(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->readToken}")
            ->postJson('/api/v1/openclaw/expenses/categories', [
                'name' => 'Should Not Land',
            ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('expense_categories', ['name' => 'Should Not Land']);
    }

    public function test_store_with_no_bearer_returns_401(): void
    {
        $response = $this->postJson('/api/v1/openclaw/expenses/categories', [
            'name' => 'Anything',
        ]);

        $response->assertStatus(401);
    }

    public function test_store_writes_an_audit_log_row_with_openclaw_source_and_token_id(): void
    {
        AuditLog::query()->delete();

        $token = ApiToken::query()->latest('id')->first();

        $this->withHeader('Authorization', "Bearer {$this->writeToken}")
            ->postJson('/api/v1/openclaw/expenses/categories', [
                'name' => 'Audited Category',
            ])
            ->assertStatus(201);

        $log = AuditLog::query()
            ->where('auditable_type', ExpenseCategory::class)
            ->where('event', 'created')
            ->latest('id')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('openclaw', $log->source);
        $this->assertSame($token->id, (int) $log->api_token_id);
    }

    public function test_update_changes_name_and_description(): void
    {
        $category = ExpenseCategory::create([
            'name' => 'Old Name',
            'description' => 'Old description',
            'status' => true,
            'created_by' => $this->owner->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->writeToken}")
            ->patchJson("/api/v1/openclaw/expenses/categories/{$category->id}", [
                'name' => 'New Name',
                'description' => 'New description',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'category' => [
                        'id' => $category->id,
                        'name' => 'New Name',
                        'description' => 'New description',
                    ],
                ],
            ]);

        $this->assertSame('New Name', $category->fresh()->name);
    }

    public function test_update_can_soft_archive_via_status_toggle(): void
    {
        // Spec says "Prefer soft-disable/archive over delete." The bot achieves
        // this by PATCHing status=false.
        $category = ExpenseCategory::create([
            'name' => 'Legacy Category',
            'description' => null,
            'status' => true,
            'created_by' => $this->owner->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->writeToken}")
            ->patchJson("/api/v1/openclaw/expenses/categories/{$category->id}", [
                'status' => false,
            ]);

        $response->assertStatus(200);
        $this->assertFalse((bool) $category->fresh()->status);
    }

    public function test_update_rejects_rename_when_target_name_already_exists(): void
    {
        $a = ExpenseCategory::create([
            'name' => 'Alpha', 'description' => null, 'status' => true, 'created_by' => $this->owner->id,
        ]);
        ExpenseCategory::create([
            'name' => 'Beta', 'description' => null, 'status' => true, 'created_by' => $this->owner->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->writeToken}")
            ->patchJson("/api/v1/openclaw/expenses/categories/{$a->id}", [
                'name' => 'Beta',
            ]);

        $response->assertStatus(409)
            ->assertJsonPath('data.category.name', 'Beta');
        $this->assertSame('Alpha', $a->fresh()->name);
    }

    public function test_update_allows_keeping_the_same_name(): void
    {
        // A rename that's a no-op on name (e.g., the bot sends back the
        // same name with a new description) must not 409 against the row's
        // own name.
        $category = ExpenseCategory::create([
            'name' => 'Steady', 'description' => null, 'status' => true, 'created_by' => $this->owner->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->writeToken}")
            ->patchJson("/api/v1/openclaw/expenses/categories/{$category->id}", [
                'name' => 'Steady',
                'description' => 'Now with a description',
            ]);

        $response->assertStatus(200);
        $this->assertSame('Now with a description', $category->fresh()->description);
    }

    public function test_update_requires_the_expense_categories_write_ability(): void
    {
        $category = ExpenseCategory::create([
            'name' => 'Locked', 'description' => null, 'status' => true, 'created_by' => $this->owner->id,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->readToken}")
            ->patchJson("/api/v1/openclaw/expenses/categories/{$category->id}", [
                'name' => 'Tampered',
            ]);

        $response->assertStatus(403);
        $this->assertSame('Locked', $category->fresh()->name);
    }

    public function test_update_returns_404_for_unknown_category(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->writeToken}")
            ->patchJson('/api/v1/openclaw/expenses/categories/99999', [
                'name' => 'Whatever',
            ]);

        $response->assertStatus(404);
    }
}
