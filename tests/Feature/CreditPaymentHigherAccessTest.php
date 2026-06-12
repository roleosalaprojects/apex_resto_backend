<?php

namespace Tests\Feature;

use App\Models\Employees\Role;
use App\Models\Pos\HigherAccessRequest;
use App\Models\Settings\Pos;
use App\Models\Settings\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Pins the credit_payment permission_type end-to-end:
 *
 * - POS validator accepts permission_type=credit_payment
 * - Approver gate reads roles.crdt_pymnt (Option A — same flag for "can do"
 *   and "can approve", mirroring discounts/rfnd/etc)
 * - Admin web AccessRequestController surfaces it for approvers + uses the
 *   correct label "Receive Credit Payment"
 */
class CreditPaymentHigherAccessTest extends TestCase
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

    public function test_pos_accepts_credit_payment_permission_type(): void
    {
        Passport::actingAs($this->owner);

        $response = $this->postJson('/api/v1/auth/higher-access/request', [
            'user_id' => $this->owner->id,
            'user_name' => $this->owner->name,
            'store_id' => $this->store->id,
            'store_name' => $this->store->name,
            'pos_id' => $this->pos->id,
            'pos_name' => $this->pos->name,
            'permission_type' => 'credit_payment',
            'context_data' => ['customer_id' => 42, 'customer_name' => 'Jane Doe'],
            'device_id' => 'pos-test-credit-payment',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('higher_access_requests', [
            'permission_type' => 'credit_payment',
            'status' => 'pending',
        ]);
    }

    public function test_respond_credit_payment_requires_crdt_pymnt_role(): void
    {
        $weakRole = Role::factory()->create([
            'crdt_pymnt' => false,
            'discounts' => false,
            'rfnd' => false,
            'delete_items' => false,
            'csh_out' => false,
            'crdt_sale' => false,
            'unit_lock_approve' => false,
        ]);
        $weakUser = User::factory()->create([
            'role_id' => $weakRole->id,
            'user_id' => $this->owner->user_id,
        ]);
        $accessRequest = $this->createPendingCreditPaymentRequest();

        Passport::actingAs($weakUser);

        $this->postJson('/api/v1/auth/higher-access/respond', [
            'request_id' => $accessRequest->request_id,
            'status' => 'approved',
        ])->assertStatus(403);

        $this->assertSame('pending', $accessRequest->fresh()->status);
    }

    public function test_respond_credit_payment_succeeds_with_crdt_pymnt_role(): void
    {
        $approverRole = Role::factory()->create(['crdt_pymnt' => true]);
        $approver = User::factory()->create([
            'role_id' => $approverRole->id,
            'user_id' => $this->owner->user_id,
        ]);
        $accessRequest = $this->createPendingCreditPaymentRequest();

        Passport::actingAs($approver);

        $this->postJson('/api/v1/auth/higher-access/respond', [
            'request_id' => $accessRequest->request_id,
            'status' => 'approved',
        ])->assertStatus(200);

        $this->assertSame('approved', $accessRequest->fresh()->status);
    }

    public function test_admin_pending_endpoint_surfaces_credit_payment_for_crdt_pymnt_approver(): void
    {
        $accessRequest = $this->createPendingCreditPaymentRequest();

        $approverRole = Role::factory()->create([
            'crdt_pymnt' => true,
            'discounts' => false,
            'rfnd' => false,
            'delete_items' => false,
            'csh_out' => false,
            'crdt_sale' => false,
            'unit_lock_approve' => false,
        ]);
        $approver = User::factory()->create([
            'role_id' => $approverRole->id,
            'user_id' => $this->owner->user_id,
        ]);

        $response = $this->actingAs($approver)->getJson(route('access-requests.pending'));

        $response->assertStatus(200);
        $entries = collect($response->json('data.requests'));
        $this->assertCount(1, $entries);
        $this->assertSame((string) $accessRequest->request_id, $entries->first()['request_id']);
        $this->assertSame('credit_payment', $entries->first()['permission_type']);
        $this->assertSame('Receive Credit Payment', $entries->first()['permission_label']);
    }

    public function test_admin_pending_endpoint_does_not_surface_credit_payment_when_role_lacks_flag(): void
    {
        $this->createPendingCreditPaymentRequest();

        $approverRole = Role::factory()->create([
            'crdt_pymnt' => false,
            // grant a different flag so the approver does see *some* requests
            // and we know the filter is specifically the credit_payment row
            'discounts' => true,
            'rfnd' => false,
            'delete_items' => false,
            'csh_out' => false,
            'crdt_sale' => false,
            'unit_lock_approve' => false,
        ]);
        $approver = User::factory()->create([
            'role_id' => $approverRole->id,
            'user_id' => $this->owner->user_id,
        ]);

        $response = $this->actingAs($approver)->getJson(route('access-requests.pending'));

        $response->assertStatus(200);
        $this->assertCount(0, $response->json('data.requests'));
    }

    private function createPendingCreditPaymentRequest(): HigherAccessRequest
    {
        return HigherAccessRequest::create([
            'request_id' => Str::uuid(),
            'user_id' => $this->owner->id,
            'user_name' => 'Cashier',
            'store_id' => $this->store->id,
            'store_name' => $this->store->name,
            'pos_id' => $this->pos->id,
            'pos_name' => $this->pos->name,
            'permission_type' => 'credit_payment',
            'context_data' => ['customer_id' => 1, 'customer_name' => 'Jane Doe'],
            'status' => 'pending',
            'device_id' => 'pos-test',
            'expires_at' => now()->addMinutes(2),
        ]);
    }
}
