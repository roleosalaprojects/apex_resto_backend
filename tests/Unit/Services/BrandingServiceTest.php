<?php

namespace Tests\Unit\Services;

use App\Models\Employees\Role;
use App\Models\Settings\BrandingSetting;
use App\Models\Settings\ColorPalette;
use App\Models\User;
use App\Services\BrandingService;
use Database\Seeders\ColorPaletteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class BrandingServiceTest extends TestCase
{
    use RefreshDatabase;

    private BrandingService $service;

    private Role $role;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ColorPaletteSeeder::class);
        $this->service = app(BrandingService::class);
        $this->role = Role::factory()->admin()->create();
        Cache::flush();
    }

    private function makeUser(array $overrides = []): User
    {
        return User::factory()->create(array_merge([
            'role_id' => $this->role->id,
            'status' => true,
        ], $overrides));
    }

    public function test_returns_default_payload_when_no_authenticated_user(): void
    {
        $payload = $this->service->forCurrentTenant();

        $this->assertSame('apex_default', $payload['palette_key']);
        $this->assertSame('#1858fd', $payload['primary']);
        $this->assertSame('APEX', $payload['brand_name']);
        $this->assertNull($payload['logo_url']);
        $this->assertNull($payload['updated_at']);
    }

    public function test_returns_tenant_palette_when_setting_exists(): void
    {
        $user = $this->makeUser(['user_id' => 7]);
        BrandingSetting::factory()->create([
            'user_id' => $user->id,
            'palette_key' => 'ocean_breeze',
            'brand_name' => 'Quick Baskets',
        ]);

        $payload = $this->service->forTenant($user->id);

        $this->assertSame('ocean_breeze', $payload['palette_key']);
        $this->assertSame('Quick Baskets', $payload['brand_name']);
        $this->assertSame('#0ea5e9', $payload['primary']);
    }

    public function test_falls_back_to_default_when_assigned_palette_inactive(): void
    {
        ColorPalette::query()->where('key', 'ocean_breeze')->update(['is_active' => false]);

        $user = $this->makeUser();
        BrandingSetting::factory()->create([
            'user_id' => $user->id,
            'palette_key' => 'ocean_breeze',
        ]);

        $payload = $this->service->forTenant($user->id);

        $this->assertSame('apex_default', $payload['palette_key']);
    }

    public function test_falls_back_to_default_when_assigned_palette_missing(): void
    {
        $user = $this->makeUser();
        BrandingSetting::factory()->create([
            'user_id' => $user->id,
            'palette_key' => 'nonexistent_key',
        ]);

        $payload = $this->service->forTenant($user->id);

        $this->assertSame('apex_default', $payload['palette_key']);
    }

    public function test_sanitize_hex_accepts_valid_hex(): void
    {
        $this->assertSame('#abcdef', $this->service->sanitizeHex('#abcdef'));
        $this->assertSame('#abcdef', $this->service->sanitizeHex('#ABCDEF'));
    }

    public function test_sanitize_hex_rejects_css_injection_attempt(): void
    {
        $this->assertSame(
            '#1858fd',
            $this->service->sanitizeHex('red; } body { display: none; '),
        );
        $this->assertSame('#1858fd', $this->service->sanitizeHex('rgb(0,0,0)'));
        $this->assertSame('#1858fd', $this->service->sanitizeHex('javascript:alert(1)'));
        $this->assertSame('#1858fd', $this->service->sanitizeHex(''));
        $this->assertSame('#1858fd', $this->service->sanitizeHex('#xyz123'));
    }

    public function test_cache_invalidates_when_branding_setting_saved(): void
    {
        $user = $this->makeUser();
        BrandingSetting::factory()->create([
            'user_id' => $user->id,
            'palette_key' => 'ocean_breeze',
        ]);

        $first = $this->service->forTenant($user->id);
        $this->assertSame('ocean_breeze', $first['palette_key']);

        BrandingSetting::where('user_id', $user->id)
            ->update(['palette_key' => 'forest_green']);
        // updateOrCreate / update bypasses observer; trigger save explicitly.
        BrandingSetting::where('user_id', $user->id)->first()->touch();

        $second = $this->service->forTenant($user->id);
        $this->assertSame('forest_green', $second['palette_key']);
    }

    public function test_for_storefront_returns_first_active_tenants_branding(): void
    {
        // user_id == id makes this the tenant owner.
        $tenant = $this->makeUser(['user_id' => null]);
        $tenant->update(['user_id' => $tenant->id]);

        BrandingSetting::factory()->create([
            'user_id' => $tenant->id,
            'palette_key' => 'forest_green',
            'brand_name' => 'Leteres',
        ]);

        $payload = $this->service->forStorefront();

        $this->assertSame('forest_green', $payload['palette_key']);
        $this->assertSame('Leteres', $payload['brand_name']);
    }

    public function test_for_storefront_falls_back_to_defaults_when_no_tenant_exists(): void
    {
        // Fresh DB, no tenants.
        $payload = $this->service->forStorefront();

        $this->assertSame('apex_default', $payload['palette_key']);
        $this->assertSame('APEX', $payload['brand_name']);
    }

    public function test_cache_invalidates_when_palette_saved(): void
    {
        $user = $this->makeUser();
        BrandingSetting::factory()->create([
            'user_id' => $user->id,
            'palette_key' => 'ocean_breeze',
        ]);

        $first = $this->service->forTenant($user->id);
        $this->assertSame('#0ea5e9', $first['primary']);

        $palette = ColorPalette::query()->where('key', 'ocean_breeze')->first();
        $palette->update(['primary' => '#123456']);

        $second = $this->service->forTenant($user->id);
        $this->assertSame('#123456', $second['primary']);
    }
}
