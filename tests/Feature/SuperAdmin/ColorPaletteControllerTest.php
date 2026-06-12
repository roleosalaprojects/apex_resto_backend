<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\Admin;
use App\Models\Employees\Role;
use App\Models\Settings\ColorPalette;
use App\Models\User;
use Database\Seeders\ColorPaletteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ColorPaletteControllerTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ColorPaletteSeeder::class);
        $this->admin = Admin::factory()->create();
    }

    public function test_superadmin_can_view_palettes_index(): void
    {
        $response = $this->actingAs($this->admin, 'superadmin')
            ->get(route('superadmin.color-palettes.index'));

        $response->assertOk();
        $response->assertSee('Color Palettes');
    }

    public function test_superadmin_can_create_a_palette(): void
    {
        $payload = [
            'key' => 'arctic_blue',
            'label' => 'Arctic Blue',
            'primary' => '#1d4ed8',
            'secondary' => '#1e40af',
            'accent' => '#22d3ee',
            'on_primary' => '#ffffff',
            'on_secondary' => '#ffffff',
            'is_active' => '1',
            'sort_order' => 75,
        ];

        $response = $this->actingAs($this->admin, 'superadmin')
            ->post(route('superadmin.color-palettes.store'), $payload);

        $response->assertRedirect(route('superadmin.color-palettes.index'));
        $this->assertDatabaseHas('color_palettes', [
            'key' => 'arctic_blue',
            'primary' => '#1d4ed8',
            'is_default' => false,
            'is_active' => true,
        ]);
    }

    public function test_validation_rejects_non_hex_color(): void
    {
        $payload = [
            'key' => 'broken',
            'label' => 'Broken',
            'primary' => 'red; } body { display: none; ',
            'secondary' => '#1e40af',
            'accent' => '#22d3ee',
            'on_primary' => '#ffffff',
            'on_secondary' => '#ffffff',
        ];

        $response = $this->actingAs($this->admin, 'superadmin')
            ->post(route('superadmin.color-palettes.store'), $payload);

        $response->assertSessionHasErrors('primary');
        $this->assertDatabaseMissing('color_palettes', ['key' => 'broken']);
    }

    public function test_validation_rejects_duplicate_key(): void
    {
        $payload = [
            'key' => 'apex_default',
            'label' => 'Trying to overwrite',
            'primary' => '#111111',
            'secondary' => '#222222',
            'accent' => '#333333',
            'on_primary' => '#ffffff',
            'on_secondary' => '#ffffff',
        ];

        $response = $this->actingAs($this->admin, 'superadmin')
            ->post(route('superadmin.color-palettes.store'), $payload);

        $response->assertSessionHasErrors('key');
    }

    public function test_superadmin_can_update_a_palette(): void
    {
        $palette = ColorPalette::query()->where('key', 'ocean_breeze')->first();

        $response = $this->actingAs($this->admin, 'superadmin')
            ->put(route('superadmin.color-palettes.update', $palette), [
                'key' => $palette->key,
                'label' => 'Updated Ocean',
                'primary' => '#123456',
                'secondary' => $palette->secondary,
                'accent' => $palette->accent,
                'on_primary' => $palette->on_primary,
                'on_secondary' => $palette->on_secondary,
                'is_active' => '1',
                'sort_order' => $palette->sort_order,
            ]);

        $response->assertRedirect(route('superadmin.color-palettes.index'));
        $this->assertDatabaseHas('color_palettes', [
            'id' => $palette->id,
            'label' => 'Updated Ocean',
            'primary' => '#123456',
        ]);
    }

    public function test_set_default_clears_previous_default(): void
    {
        $previousDefault = ColorPalette::query()->where('is_default', true)->first();
        $other = ColorPalette::query()->where('key', 'forest_green')->first();

        $response = $this->actingAs($this->admin, 'superadmin')
            ->post(route('superadmin.color-palettes.set-default', $other));

        $response->assertRedirect(route('superadmin.color-palettes.index'));
        $this->assertDatabaseHas('color_palettes', [
            'id' => $other->id,
            'is_default' => true,
        ]);
        $this->assertDatabaseHas('color_palettes', [
            'id' => $previousDefault->id,
            'is_default' => false,
        ]);
    }

    public function test_cannot_delete_default_palette(): void
    {
        $default = ColorPalette::query()->where('is_default', true)->first();

        $response = $this->actingAs($this->admin, 'superadmin')
            ->delete(route('superadmin.color-palettes.destroy', $default));

        $response->assertRedirect(route('superadmin.color-palettes.index'));
        $response->assertSessionHas('error');
        $this->assertNotSoftDeleted('color_palettes', ['id' => $default->id]);
    }

    public function test_superadmin_can_soft_delete_non_default_palette(): void
    {
        $palette = ColorPalette::query()->where('key', 'rose_gold')->first();

        $response = $this->actingAs($this->admin, 'superadmin')
            ->delete(route('superadmin.color-palettes.destroy', $palette));

        $response->assertRedirect(route('superadmin.color-palettes.index'));
        $this->assertSoftDeleted('color_palettes', ['id' => $palette->id]);
    }

    public function test_cannot_deactivate_default_palette(): void
    {
        $default = ColorPalette::query()->where('is_default', true)->first();

        $response = $this->actingAs($this->admin, 'superadmin')
            ->post(route('superadmin.color-palettes.toggle-active', $default));

        $response->assertRedirect();
        $this->assertDatabaseHas('color_palettes', [
            'id' => $default->id,
            'is_active' => true,
        ]);
    }

    public function test_non_superadmin_cannot_access_palettes(): void
    {
        $role = Role::factory()->admin()->create();
        $user = User::factory()->create([
            'role_id' => $role->id,
            'status' => true,
        ]);

        $response = $this->actingAs($user)
            ->get(route('superadmin.color-palettes.index'));

        $response->assertRedirect();
    }
}
