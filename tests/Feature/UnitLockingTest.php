<?php

namespace Tests\Feature;

use App\Models\Employees\Role;
use App\Models\Pos\HigherAccessRequest;
use App\Models\Products\Item;
use App\Models\Products\ItemUnit;
use App\Models\Products\Unit;
use App\Models\Settings\Pos;
use App\Models\Settings\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Coverage for the locked-unit feature shipped on feature/unit-locking.
 *
 * The feature wires three concerns together — schema columns, RBAC flags,
 * and the existing higher-access flow — so the tests pin each of those at
 * least once end-to-end.
 */
class UnitLockingTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Role $adminRole;

    protected Store $store;

    protected Pos $pos;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::factory()->admin()->create();
        $this->owner = User::factory()->create(['role_id' => $this->adminRole->id]);
        $this->owner->forceFill(['user_id' => $this->owner->id])->save();
        $this->store = Store::factory()->create(['user_id' => $this->owner->user_id]);
        $this->pos = Pos::factory()->create(['store_id' => $this->store->id]);
    }

    // ----- POS higher-access flow with the new permission_type -----

    public function test_pos_accepts_locked_unit_permission_type(): void
    {
        Passport::actingAs($this->owner);

        $response = $this->postJson('/api/v1/auth/higher-access/request', [
            'user_id' => $this->owner->id,
            'user_name' => $this->owner->name,
            'store_id' => $this->store->id,
            'store_name' => $this->store->name,
            'pos_id' => $this->pos->id,
            'pos_name' => $this->pos->name,
            'permission_type' => 'locked_unit',
            'context_data' => ['item_id' => 42, 'item_name' => 'Rice', 'unit_id' => 7, 'unit_name' => 'Sack'],
            'device_id' => 'pos-test-1',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('higher_access_requests', [
            'permission_type' => 'locked_unit',
            'status' => 'pending',
        ]);
    }

    public function test_respond_locked_unit_requires_unit_lock_approve_role(): void
    {
        $weakRole = Role::factory()->create([
            'unit_lock' => false,
            'unit_lock_approve' => false,
            'discounts' => false,
            'rfnd' => false,
            'delete_items' => false,
            'csh_out' => false,
            'crdt_sale' => false,
        ]);
        $weakUser = User::factory()->create([
            'role_id' => $weakRole->id,
            'user_id' => $this->owner->user_id,
        ]);
        $accessRequest = $this->createPendingLockedUnitRequest();

        Passport::actingAs($weakUser);

        $response = $this->postJson('/api/v1/auth/higher-access/respond', [
            'request_id' => $accessRequest->request_id,
            'status' => 'approved',
        ]);

        $response->assertStatus(403);
        $this->assertSame('pending', $accessRequest->fresh()->status);
    }

    public function test_respond_locked_unit_succeeds_with_unit_lock_approve_role(): void
    {
        $approverRole = Role::factory()->create([
            'unit_lock_approve' => true,
        ]);
        $approver = User::factory()->create([
            'role_id' => $approverRole->id,
            'user_id' => $this->owner->user_id,
        ]);
        $accessRequest = $this->createPendingLockedUnitRequest();

        Passport::actingAs($approver);

        $response = $this->postJson('/api/v1/auth/higher-access/respond', [
            'request_id' => $accessRequest->request_id,
            'status' => 'approved',
        ]);

        $response->assertStatus(200);
        $this->assertSame('approved', $accessRequest->fresh()->status);
    }

    // ----- POS item payload exposes the locked flag -----

    public function test_pos_item_payload_includes_locked_field(): void
    {
        $item = Item::factory()->create(['user_id' => $this->owner->user_id, 'status' => true]);
        $unit = Unit::factory()->create();
        ItemUnit::create([
            'item_id' => $item->id,
            'unit_id' => $unit->id,
            'qty' => 25,
            'price' => 2500,
            'barcode' => null,
            'status' => true,
            'locked' => true,
        ]);

        Passport::actingAs($this->owner);

        $response = $this->getJson("/api/v1/items/{$item->id}");

        $response->assertStatus(200);
        $units = $response->json('data.item_units');
        $this->assertNotEmpty($units);
        $this->assertTrue((bool) $units[0]['locked']);
    }

    // ----- Web /admin/access-requests endpoints -----

    public function test_admin_pending_endpoint_returns_only_approvable_requests_in_tenant(): void
    {
        // In-tenant, type the approver CAN approve (unit_lock_approve role).
        $inTenantApprovable = $this->createPendingLockedUnitRequest();

        // In-tenant, type the approver CANNOT approve (refunds; their role doesn't carry rfnd).
        $approverRole = Role::factory()->create([
            'unit_lock_approve' => true,
            'rfnd' => false,
            'discounts' => false,
            'delete_items' => false,
            'csh_out' => false,
            'crdt_sale' => false,
        ]);
        $approver = User::factory()->create([
            'role_id' => $approverRole->id,
            'user_id' => $this->owner->user_id,
        ]);

        HigherAccessRequest::create([
            'request_id' => Str::uuid(),
            'user_id' => $this->owner->id,
            'user_name' => 'Cashier',
            'store_id' => $this->store->id,
            'store_name' => $this->store->name,
            'pos_id' => $this->pos->id,
            'pos_name' => $this->pos->name,
            'permission_type' => 'refunds',
            'status' => 'pending',
            'device_id' => 'pos-x',
            'expires_at' => now()->addMinutes(2),
        ]);

        // Foreign-tenant, same type — must NOT leak.
        $otherOwner = User::factory()->create(['role_id' => $this->adminRole->id]);
        $otherOwner->forceFill(['user_id' => $otherOwner->id])->save();
        $otherStore = Store::factory()->create(['user_id' => $otherOwner->user_id]);
        $otherPos = Pos::factory()->create(['store_id' => $otherStore->id]);
        HigherAccessRequest::create([
            'request_id' => Str::uuid(),
            'user_id' => $otherOwner->id,
            'user_name' => 'Foreign cashier',
            'store_id' => $otherStore->id,
            'store_name' => $otherStore->name,
            'pos_id' => $otherPos->id,
            'pos_name' => $otherPos->name,
            'permission_type' => 'locked_unit',
            'status' => 'pending',
            'device_id' => 'pos-foreign',
            'expires_at' => now()->addMinutes(2),
        ]);

        $response = $this->actingAs($approver)->getJson(route('access-requests.pending'));

        $response->assertStatus(200);
        $ids = collect($response->json('data.requests'))->pluck('request_id')->all();
        $this->assertContains((string) $inTenantApprovable->request_id, $ids);
        $this->assertSame(1, count($ids), 'should not include refund (unapprovable) or foreign-tenant rows');
    }

    public function test_admin_respond_endpoint_approves_request(): void
    {
        $accessRequest = $this->createPendingLockedUnitRequest();

        $approverRole = Role::factory()->create(['unit_lock_approve' => true]);
        $approver = User::factory()->create([
            'role_id' => $approverRole->id,
            'user_id' => $this->owner->user_id,
        ]);

        $response = $this->actingAs($approver)->postJson(
            route('access-requests.respond', ['requestId' => $accessRequest->request_id]),
            ['status' => 'approved'],
        );

        $response->assertStatus(200);
        $this->assertSame('approved', $accessRequest->fresh()->status);
        $this->assertSame($approver->id, $accessRequest->fresh()->approver_id);
    }

    // ----- Admin item form save: role gate + preservation -----

    public function test_item_save_with_unit_lock_role_persists_locked_flag(): void
    {
        $unit = Unit::factory()->create();
        $item = Item::factory()->create(['user_id' => $this->owner->user_id, 'status' => true]);
        ItemUnit::create([
            'item_id' => $item->id,
            'unit_id' => $unit->id,
            'qty' => 25,
            'price' => 2500,
            'status' => true,
            'locked' => false,
        ]);

        $response = $this->actingAs($this->owner)->put(route('items.update', $item->id), [
            'name' => $item->name,
            'barcode' => $item->barcode ?? '0001',
            'code' => $item->code ?? 'C001',
            'cost' => 100,
            'main_price' => 130,
            'markup' => 30,
            'vatable' => 1,
            'rate' => 12,
            'discountable' => 0,
            'creditable_to_points' => 'off',
            'type' => $item->type ?? 0,
            'category' => $item->category_id,
            'tax' => $item->tax_id,
            'supplier' => $item->supplier_id,
            'uom_id' => [$unit->id],
            'qty' => [25],
            'price' => [2500],
            'uom_barcode' => [''],
            'locked' => [1], // role has unit_lock=true via admin() state
        ]);

        // Don't care about redirect destination; just want the DB row.
        $row = ItemUnit::where('item_id', $item->id)->where('unit_id', $unit->id)->first();
        $this->assertNotNull($row);
        $this->assertTrue((bool) $row->locked);
    }

    public function test_item_save_without_unit_lock_role_preserves_previous_lock(): void
    {
        // Pre-condition: a locked unit on the item, set previously by an owner.
        $unit = Unit::factory()->create();
        $item = Item::factory()->create(['user_id' => $this->owner->user_id, 'status' => true]);
        ItemUnit::create([
            'item_id' => $item->id,
            'unit_id' => $unit->id,
            'qty' => 25,
            'price' => 2500,
            'status' => true,
            'locked' => true,
        ]);

        // Editor with itms_update but NOT unit_lock — they can save the item
        // but cannot flip the lock.
        $editorRole = Role::factory()->create([
            'itms' => true,
            'itms_update' => true,
            'unit_lock' => false,
        ]);
        $editor = User::factory()->create([
            'role_id' => $editorRole->id,
            'user_id' => $this->owner->user_id,
        ]);

        $this->actingAs($editor)->put(route('items.update', $item->id), [
            'name' => $item->name,
            'barcode' => $item->barcode ?? '0001',
            'code' => $item->code ?? 'C001',
            'cost' => 100,
            'main_price' => 130,
            'markup' => 30,
            'vatable' => 1,
            'rate' => 12,
            'discountable' => 0,
            'creditable_to_points' => 'off',
            'type' => $item->type ?? 0,
            'category' => $item->category_id,
            'tax' => $item->tax_id,
            'supplier' => $item->supplier_id,
            'uom_id' => [$unit->id],
            'qty' => [25],
            'price' => [2500],
            'uom_barcode' => [''],
            // No 'locked' key submitted — the form would hide the column for this user.
        ]);

        $row = ItemUnit::where('item_id', $item->id)->where('unit_id', $unit->id)->first();
        $this->assertNotNull($row);
        $this->assertTrue((bool) $row->locked, 'previous lock state must be preserved when actor lacks unit_lock');
    }

    private function createPendingLockedUnitRequest(): HigherAccessRequest
    {
        return HigherAccessRequest::create([
            'request_id' => Str::uuid(),
            'user_id' => $this->owner->id,
            'user_name' => 'Cashier',
            'store_id' => $this->store->id,
            'store_name' => $this->store->name,
            'pos_id' => $this->pos->id,
            'pos_name' => $this->pos->name,
            'permission_type' => 'locked_unit',
            'context_data' => ['item_id' => 1, 'item_name' => 'Rice', 'unit_id' => 1, 'unit_name' => 'Sack'],
            'status' => 'pending',
            'device_id' => 'pos-test',
            'expires_at' => now()->addMinutes(2),
        ]);
    }
}
