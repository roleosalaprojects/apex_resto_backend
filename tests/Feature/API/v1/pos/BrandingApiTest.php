<?php

namespace Tests\Feature\API\v1\pos;

use App\Models\Employees\Role;
use App\Models\Settings\BrandingSetting;
use App\Models\User;
use Database\Seeders\ColorPaletteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class BrandingApiTest extends TestCase
{
    use RefreshDatabase;

    private Role $role;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ColorPaletteSeeder::class);
        $this->role = Role::factory()->admin()->create();
    }

    public function test_unauthenticated_returns_401(): void
    {
        $response = $this->getJson('/api/v1/branding');
        $response->assertStatus(401);
    }

    public function test_authenticated_returns_default_palette_when_no_setting(): void
    {
        $user = User::factory()->create(['role_id' => $this->role->id, 'user_id' => 1]);
        Passport::actingAs($user);

        $response = $this->getJson('/api/v1/branding');

        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                'palette_key' => 'apex_default',
                'primary_color' => '#1858fd',
                'brand_name' => 'APEX',
                'logo_url' => null,
            ],
        ]);
    }

    public function test_authenticated_returns_tenant_palette(): void
    {
        $user = User::factory()->create(['role_id' => $this->role->id, 'user_id' => 1]);
        BrandingSetting::factory()->create([
            'user_id' => $user->user_id,
            'palette_key' => 'forest_green',
            'brand_name' => 'Quick Baskets',
        ]);
        Passport::actingAs($user);

        $response = $this->getJson('/api/v1/branding');

        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                'palette_key' => 'forest_green',
                'primary_color' => '#16a34a',
                'brand_name' => 'Quick Baskets',
            ],
        ]);
    }

    public function test_response_shape_matches_contract(): void
    {
        $user = User::factory()->create(['role_id' => $this->role->id, 'user_id' => 1]);
        Passport::actingAs($user);

        $response = $this->getJson('/api/v1/branding');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'palette_key',
                'primary_color',
                'secondary_color',
                'accent_color',
                'on_primary',
                'on_secondary',
                'logo_url',
                'brand_name',
                'updated_at',
            ],
        ]);
    }

    public function test_tenant_b_cannot_see_tenant_a_branding(): void
    {
        $tenantA = User::factory()->create(['role_id' => $this->role->id, 'user_id' => 1]);
        $tenantB = User::factory()->create(['role_id' => $this->role->id, 'user_id' => 2]);

        BrandingSetting::factory()->create([
            'user_id' => $tenantA->user_id,
            'palette_key' => 'forest_green',
            'brand_name' => 'Tenant A only',
        ]);

        Passport::actingAs($tenantB);
        $response = $this->getJson('/api/v1/branding');

        $response->assertJsonMissing(['brand_name' => 'Tenant A only']);
        $response->assertJsonPath('data.palette_key', 'apex_default');
    }
}
