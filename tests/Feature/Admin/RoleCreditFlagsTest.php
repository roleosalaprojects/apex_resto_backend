<?php

namespace Tests\Feature\Admin;

use App\Models\Employees\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pins the bug fix: the role admin form now exposes crdt_sale and
 * crdt_pymnt checkboxes, and RoleController saves them. Previously the
 * columns existed in the DB and were consumed by HigherAccessController
 * but there was no UI to grant/revoke them.
 */
class RoleCreditFlagsTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::factory()->admin()->create();
        $this->owner = User::factory()->create(['role_id' => $adminRole->id]);
        $this->owner->forceFill(['user_id' => $this->owner->id])->save();
    }

    public function test_role_form_includes_credit_flag_checkboxes(): void
    {
        $role = Role::factory()->create([
            'user_id' => $this->owner->user_id,
            'crdt_sale' => true,
            'crdt_pymnt' => false,
        ]);

        $response = $this->actingAs($this->owner)->get(route('roles.edit', $role->id));

        $response->assertStatus(200);
        $response->assertSee('name="crdt_sale"', false);
        $response->assertSee('name="crdt_pymnt"', false);
    }

    public function test_role_store_persists_credit_flags(): void
    {
        $payload = $this->validRolePayload([
            'name' => 'Credit-Capable Manager',
            'crdt_sale' => 1,
            'crdt_pymnt' => 1,
        ]);

        $this->actingAs($this->owner)->post(route('roles.store'), $payload);

        $created = Role::where('name', 'CREDIT-CAPABLE MANAGER')->first();
        $this->assertNotNull($created);
        $this->assertTrue((bool) $created->crdt_sale);
        $this->assertTrue((bool) $created->crdt_pymnt);
    }

    public function test_role_update_persists_credit_flag_changes(): void
    {
        $role = Role::factory()->create([
            'user_id' => $this->owner->user_id,
            'crdt_sale' => false,
            'crdt_pymnt' => true,
        ]);

        $payload = $this->validRolePayload([
            'name' => $role->name,
            'crdt_sale' => 1,
            // crdt_pymnt omitted → should clear to false
        ]);

        $this->actingAs($this->owner)->put(route('roles.update', $role->id), $payload);

        $role->refresh();
        $this->assertTrue((bool) $role->crdt_sale);
        $this->assertFalse((bool) $role->crdt_pymnt);
    }

    /**
     * Role store/update validate name + pos as required. Build a payload
     * with sensible defaults and merge in test-specific overrides.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validRolePayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Test Role',
            'pos' => 1,
        ], $overrides);
    }
}
