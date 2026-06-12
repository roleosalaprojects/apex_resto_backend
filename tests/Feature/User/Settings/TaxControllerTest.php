<?php

namespace Tests\Feature\User\Settings;

use App\Models\Employees\Role;
use App\Models\Settings\Tax;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaxControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Role $role;

    protected function setUp(): void
    {
        parent::setUp();

        $this->role = Role::factory()->admin()->create();
        $this->user = User::factory()->create([
            'role_id' => $this->role->id,
            'user_id' => 1,
            'status' => true,
        ]);
    }

    public function test_can_view_taxes_index(): void
    {
        $response = $this->actingAs($this->user)->get('/admin/taxes');

        $response->assertOk();
    }

    public function test_can_view_create_tax_form(): void
    {
        $response = $this->actingAs($this->user)->get('/admin/taxes/create');

        $response->assertOk();
    }

    public function test_can_store_tax(): void
    {
        $response = $this->actingAs($this->user)
            ->post('/admin/taxes', [
                'name' => 'VAT',
                'rate' => 12,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('taxes', [
            'name' => 'VAT',
            'rate' => 12,
            'status' => true,
        ]);
    }

    public function test_can_view_tax(): void
    {
        $tax = Tax::factory()->create([
            'status' => true,
            'user_id' => $this->user->user_id,
        ]);

        $response = $this->actingAs($this->user)->get("/admin/taxes/{$tax->id}");

        $response->assertOk();
    }

    public function test_can_view_edit_tax_form(): void
    {
        $tax = Tax::factory()->create([
            'status' => true,
            'user_id' => $this->user->user_id,
        ]);

        $response = $this->actingAs($this->user)->get("/admin/taxes/{$tax->id}/edit");

        $response->assertOk();
    }

    public function test_can_update_tax(): void
    {
        $tax = Tax::factory()->create([
            'status' => true,
            'user_id' => $this->user->user_id,
        ]);

        $response = $this->actingAs($this->user)
            ->put("/admin/taxes/{$tax->id}", [
                'name' => 'Updated Tax',
                'rate' => 15,
            ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('taxes', [
            'id' => $tax->id,
            'name' => 'Updated Tax',
            'rate' => 15,
        ]);
    }

    public function test_can_delete_tax(): void
    {
        $tax = Tax::factory()->create([
            'status' => true,
            'user_id' => $this->user->user_id,
        ]);

        $response = $this->actingAs($this->user)
            ->delete("/admin/taxes/{$tax->id}");

        $response->assertRedirect();

        $this->assertDatabaseHas('taxes', [
            'id' => $tax->id,
            'status' => false,
        ]);
    }

    public function test_can_get_taxes_table_data(): void
    {
        Tax::factory()->count(5)->create([
            'status' => true,
            'user_id' => $this->user->user_id,
        ]);

        $response = $this->actingAs($this->user)
            ->get('/admin/taxes/table');

        $response->assertStatus(200);
    }

    public function test_can_select_taxes(): void
    {
        Tax::factory()->count(3)->create([
            'status' => true,
            'user_id' => $this->user->user_id,
        ]);

        $response = $this->actingAs($this->user)
            ->get('/admin/taxes/select?term=');

        $response->assertStatus(200);
    }

    public function test_unauthenticated_user_cannot_access_taxes(): void
    {
        $response = $this->get('/admin/taxes');

        $response->assertRedirect('/admin/login');
    }
}
