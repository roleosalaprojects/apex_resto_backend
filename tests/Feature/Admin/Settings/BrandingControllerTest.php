<?php

namespace Tests\Feature\Admin\Settings;

use App\Models\Employees\Role;
use App\Models\Settings\BrandingSetting;
use App\Models\User;
use Database\Seeders\ColorPaletteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BrandingControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $tenantA;

    private User $tenantB;

    private Role $role;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ColorPaletteSeeder::class);

        $this->role = Role::factory()->admin()->create();
        $this->tenantA = User::factory()->create([
            'role_id' => $this->role->id,
            'user_id' => 1,
            'status' => true,
        ]);
        $this->tenantB = User::factory()->create([
            'role_id' => $this->role->id,
            'user_id' => 2,
            'status' => true,
        ]);
    }

    public function test_tenant_can_view_branding_page(): void
    {
        $response = $this->actingAs($this->tenantA)
            ->get(route('admin.settings.branding.show'));

        $response->assertOk();
        $response->assertSee('Color palette');
        $response->assertSee('Apex Blue');
    }

    public function test_tenant_can_save_palette_selection(): void
    {
        $response = $this->actingAs($this->tenantA)
            ->put(route('admin.settings.branding.update'), [
                'palette_key' => 'ocean_breeze',
                'brand_name' => 'Quick Baskets',
            ]);

        $response->assertRedirect(route('admin.settings.branding.show'));
        $this->assertDatabaseHas('branding_settings', [
            'user_id' => $this->tenantA->user_id,
            'palette_key' => 'ocean_breeze',
            'brand_name' => 'Quick Baskets',
        ]);
    }

    public function test_validation_rejects_inactive_palette(): void
    {
        \App\Models\Settings\ColorPalette::query()
            ->where('key', 'ocean_breeze')
            ->update(['is_active' => false]);

        $response = $this->actingAs($this->tenantA)
            ->put(route('admin.settings.branding.update'), [
                'palette_key' => 'ocean_breeze',
                'brand_name' => 'Quick Baskets',
            ]);

        $response->assertSessionHasErrors('palette_key');
    }

    public function test_validation_rejects_brand_name_with_html(): void
    {
        $response = $this->actingAs($this->tenantA)
            ->put(route('admin.settings.branding.update'), [
                'palette_key' => 'apex_default',
                'brand_name' => '<script>alert(1)</script>',
            ]);

        $response->assertSessionHasErrors('brand_name');
    }

    public function test_tenant_can_upload_png_logo(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->tenantA)
            ->put(route('admin.settings.branding.update'), [
                'palette_key' => 'apex_default',
                'logo' => UploadedFile::fake()->image('logo.png', 200, 80),
            ]);

        $response->assertRedirect(route('admin.settings.branding.show'));

        $setting = BrandingSetting::query()
            ->where('user_id', $this->tenantA->user_id)
            ->firstOrFail();

        $this->assertNotNull($setting->logo_path);
        $this->assertStringStartsWith("branding/{$this->tenantA->user_id}/", $setting->logo_path);
        Storage::disk('public')->assertExists($setting->logo_path);
    }

    public function test_logo_upload_rejects_svg(): void
    {
        Storage::fake('public');

        $svg = File::create('logo.svg', 200);

        $response = $this->actingAs($this->tenantA)
            ->put(route('admin.settings.branding.update'), [
                'palette_key' => 'apex_default',
                'logo' => $svg,
            ]);

        $response->assertSessionHasErrors('logo');
    }

    public function test_logo_upload_rejects_oversize_file(): void
    {
        Storage::fake('public');

        $response = $this->actingAs($this->tenantA)
            ->put(route('admin.settings.branding.update'), [
                'palette_key' => 'apex_default',
                'logo' => UploadedFile::fake()->image('logo.png', 800, 200)->size(600),
            ]);

        $response->assertSessionHasErrors('logo');
    }

    public function test_tenant_a_cannot_overwrite_tenant_b_branding(): void
    {
        BrandingSetting::factory()->create([
            'user_id' => $this->tenantB->user_id,
            'palette_key' => 'apex_default',
            'brand_name' => 'Tenant B',
        ]);

        // Tenant A submits — must only touch tenant A's row.
        $this->actingAs($this->tenantA)
            ->put(route('admin.settings.branding.update'), [
                'palette_key' => 'ocean_breeze',
                'brand_name' => 'Tenant A try',
            ]);

        $this->assertDatabaseHas('branding_settings', [
            'user_id' => $this->tenantB->user_id,
            'brand_name' => 'Tenant B',
            'palette_key' => 'apex_default',
        ]);
        $this->assertDatabaseHas('branding_settings', [
            'user_id' => $this->tenantA->user_id,
            'brand_name' => 'Tenant A try',
            'palette_key' => 'ocean_breeze',
        ]);
    }

    public function test_unauthenticated_cannot_access_branding(): void
    {
        $response = $this->get(route('admin.settings.branding.show'));
        $response->assertRedirect();
    }
}
