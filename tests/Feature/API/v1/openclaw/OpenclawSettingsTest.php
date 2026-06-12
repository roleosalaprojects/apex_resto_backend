<?php

namespace Tests\Feature\API\v1\openclaw;

use App\Models\Accounting\Bank;
use App\Models\Accounting\ExpenseCategory;
use App\Models\ApiToken;
use App\Models\BusinessSettings;
use App\Models\Employees\Role;
use App\Models\InventoryManagement\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpenclawSettingsTest extends TestCase
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
        $this->writeToken = $this->mintToken(['openclaw:read', 'openclaw:settings:write']);
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

    public function test_get_returns_defaults_when_no_row_exists(): void
    {
        $this->assertSame(0, BusinessSettings::query()->count());

        $response = $this->withHeader('Authorization', "Bearer {$this->readToken}")
            ->getJson('/api/v1/openclaw/settings');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'settings' => [
                        'thresholds' => ['daily_sales_floor', 'daily_sales_check_after'],
                        'expense_rules' => ['default_expense_bank_id', 'receipt_required_above', 'preferred_categories'],
                        'supplier_rules' => ['default_supplier_id', 'treat_supplier_payments_as_expenses'],
                    ],
                ],
            ])
            ->assertJsonPath('data.settings.thresholds.daily_sales_check_after', '18:00')
            ->assertJsonPath('data.settings.supplier_rules.treat_supplier_payments_as_expenses', true);

        // The accessor created the singleton so subsequent reads are stable.
        $this->assertSame(1, BusinessSettings::query()->count());
    }

    public function test_patch_deep_merges_one_threshold_without_blowing_others_away(): void
    {
        // Seed an existing row with two thresholds set.
        BusinessSettings::query()->create([
            'thresholds' => ['daily_sales_floor' => 50000, 'daily_sales_check_after' => '17:00'],
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->writeToken}")
            ->patchJson('/api/v1/openclaw/settings', [
                'thresholds' => ['daily_sales_floor' => 60000],
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.settings.thresholds.daily_sales_floor', 60000)
            ->assertJsonPath('data.settings.thresholds.daily_sales_check_after', '17:00');

        $stored = BusinessSettings::current();
        $this->assertSame(60000, $stored->thresholds['daily_sales_floor']);
        $this->assertSame('17:00', $stored->thresholds['daily_sales_check_after']);
    }

    public function test_patch_merges_each_top_level_section_independently(): void
    {
        $bank = Bank::create([
            'bank_name' => 'BPI', 'account_name' => 'Main', 'account_number' => '1',
            'account_type' => Bank::TYPE_CHECKING, 'opening_balance' => 0, 'balance' => 0,
        ]);
        $category = ExpenseCategory::create([
            'name' => 'Utilities', 'status' => 1, 'created_by' => $this->owner->id,
        ]);
        $supplier = Supplier::factory()->create(['user_id' => $this->owner->user_id]);

        $auth = ['Authorization' => "Bearer {$this->writeToken}"];

        $this->withHeaders($auth)->patchJson('/api/v1/openclaw/settings', [
            'thresholds' => ['daily_sales_floor' => 75000],
            'expense_rules' => ['default_expense_bank_id' => $bank->id, 'preferred_categories' => [$category->id]],
            'supplier_rules' => ['default_supplier_id' => $supplier->id],
        ])->assertStatus(200);

        $stored = BusinessSettings::current()->withDefaults();
        $this->assertSame(75000, $stored['thresholds']['daily_sales_floor']);
        $this->assertSame($bank->id, $stored['expense_rules']['default_expense_bank_id']);
        $this->assertSame([$category->id], $stored['expense_rules']['preferred_categories']);
        $this->assertSame($supplier->id, $stored['supplier_rules']['default_supplier_id']);
        // Default that wasn't touched is still present.
        $this->assertTrue($stored['supplier_rules']['treat_supplier_payments_as_expenses']);
    }

    public function test_read_only_token_cannot_patch(): void
    {
        $this->withHeader('Authorization', "Bearer {$this->readToken}")
            ->patchJson('/api/v1/openclaw/settings', ['thresholds' => ['daily_sales_floor' => 5000]])
            ->assertStatus(403)
            ->assertJsonPath('message', 'This token is missing the required ability: openclaw:settings:write.');
    }

    public function test_unauthenticated_get_and_patch_return_401(): void
    {
        $this->getJson('/api/v1/openclaw/settings')->assertStatus(401);
        $this->patchJson('/api/v1/openclaw/settings', [])->assertStatus(401);
    }

    public function test_patch_validates_payload_shape(): void
    {
        $this->withHeader('Authorization', "Bearer {$this->writeToken}")
            ->patchJson('/api/v1/openclaw/settings', [
                'thresholds' => [
                    'daily_sales_floor' => -1,               // min:0 violation
                    'daily_sales_check_after' => 'evening',  // not H:i
                ],
                'expense_rules' => [
                    'default_expense_bank_id' => 99999,      // doesn't exist
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'thresholds.daily_sales_floor',
                'thresholds.daily_sales_check_after',
                'expense_rules.default_expense_bank_id',
            ]);
    }
}
