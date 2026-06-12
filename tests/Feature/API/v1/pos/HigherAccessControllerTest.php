<?php

namespace Tests\Feature\API\v1\pos;

use App\Models\Employees\Role;
use App\Models\Pos\HigherAccessRequest;
use App\Models\Settings\Pos;
use App\Models\Settings\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Tests\TestCase;

class HigherAccessControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected User $approver;

    protected Role $role;

    protected Store $store;

    protected Pos $pos;

    protected function setUp(): void
    {
        parent::setUp();

        $this->role = Role::factory()->admin()->create();
        $this->store = Store::factory()->create();
        $this->pos = Pos::factory()->create([
            'store_id' => $this->store->id,
        ]);
        $this->user = User::factory()->create([
            'role_id' => $this->role->id,
            'user_id' => 1,
        ]);
        $this->approver = User::factory()->create([
            'role_id' => $this->role->id,
            'user_id' => 1,
        ]);
    }

    public function test_can_create_higher_access_request(): void
    {
        Passport::actingAs($this->user);

        $response = $this->postJson('/api/v1/auth/higher-access/request', [
            'user_id' => $this->user->id,
            'user_name' => $this->user->name,
            'store_id' => $this->store->id,
            'store_name' => $this->store->name,
            'pos_id' => $this->pos->id,
            'pos_name' => $this->pos->name,
            'permission_type' => 'discounts',
            'context_data' => ['cart_total' => 100],
            'device_id' => 'test-device-123',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => ['request_id', 'expires_at'],
        ]);

        $this->assertDatabaseHas('higher_access_requests', [
            'user_id' => $this->user->id,
            'permission_type' => 'discounts',
            'status' => 'pending',
        ]);
    }

    public function test_new_request_cancels_existing_pending_request(): void
    {
        Passport::actingAs($this->user);

        // Create first request
        $firstRequest = HigherAccessRequest::create([
            'request_id' => Str::uuid(),
            'user_id' => $this->user->id,
            'user_name' => $this->user->name,
            'store_id' => $this->store->id,
            'store_name' => $this->store->name,
            'pos_id' => $this->pos->id,
            'pos_name' => $this->pos->name,
            'permission_type' => 'discounts',
            'device_id' => 'test-device-123',
            'status' => 'pending',
            'expires_at' => now()->addMinutes(2),
        ]);

        // Create another request from same user/device
        $response = $this->postJson('/api/v1/auth/higher-access/request', [
            'user_id' => $this->user->id,
            'user_name' => $this->user->name,
            'store_id' => $this->store->id,
            'store_name' => $this->store->name,
            'pos_id' => $this->pos->id,
            'pos_name' => $this->pos->name,
            'permission_type' => 'discounts',
            'device_id' => 'test-device-123',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        // First request should be cancelled
        $this->assertEquals('cancelled', $firstRequest->fresh()->status);
    }

    public function test_can_check_request_status(): void
    {
        Passport::actingAs($this->user);

        $accessRequest = HigherAccessRequest::create([
            'request_id' => Str::uuid(),
            'user_id' => $this->user->id,
            'user_name' => $this->user->name,
            'store_id' => $this->store->id,
            'store_name' => $this->store->name,
            'pos_id' => $this->pos->id,
            'pos_name' => $this->pos->name,
            'permission_type' => 'discounts',
            'device_id' => 'test-device',
            'status' => 'pending',
            'expires_at' => now()->addMinutes(2),
        ]);

        $response = $this->getJson("/api/v1/auth/higher-access/status/{$accessRequest->request_id}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'request_id',
                'status',
                'approver_id',
                'approver_name',
                'response_message',
                'responded_at',
            ],
        ]);
        $response->assertJsonPath('data.status', 'pending');
    }

    public function test_status_auto_expires_request(): void
    {
        Passport::actingAs($this->user);

        $accessRequest = HigherAccessRequest::create([
            'request_id' => Str::uuid(),
            'user_id' => $this->user->id,
            'user_name' => $this->user->name,
            'store_id' => $this->store->id,
            'store_name' => $this->store->name,
            'pos_id' => $this->pos->id,
            'pos_name' => $this->pos->name,
            'permission_type' => 'discounts',
            'device_id' => 'test-device',
            'status' => 'pending',
            'expires_at' => now()->subMinute(),
        ]);

        $response = $this->getJson("/api/v1/auth/higher-access/status/{$accessRequest->request_id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.status', 'expired');

        $accessRequest->refresh();
        $this->assertEquals('expired', $accessRequest->status);
    }

    public function test_can_approve_higher_access_request(): void
    {
        Passport::actingAs($this->approver);

        $accessRequest = HigherAccessRequest::create([
            'request_id' => Str::uuid(),
            'user_id' => $this->user->id,
            'user_name' => $this->user->name,
            'store_id' => $this->store->id,
            'store_name' => $this->store->name,
            'pos_id' => $this->pos->id,
            'pos_name' => $this->pos->name,
            'permission_type' => 'refunds',
            'device_id' => 'test-device',
            'status' => 'pending',
            'expires_at' => now()->addMinutes(2),
        ]);

        $response = $this->postJson('/api/v1/auth/higher-access/respond', [
            'request_id' => $accessRequest->request_id,
            'status' => 'approved',
            'message' => 'Approved by manager',
        ]);

        $response->assertStatus(200);

        $accessRequest->refresh();
        $this->assertEquals('approved', $accessRequest->status);
        $this->assertEquals($this->approver->id, $accessRequest->approver_id);
    }

    public function test_can_deny_higher_access_request(): void
    {
        Passport::actingAs($this->approver);

        $accessRequest = HigherAccessRequest::create([
            'request_id' => Str::uuid(),
            'user_id' => $this->user->id,
            'user_name' => $this->user->name,
            'store_id' => $this->store->id,
            'store_name' => $this->store->name,
            'pos_id' => $this->pos->id,
            'pos_name' => $this->pos->name,
            'permission_type' => 'delete_items',
            'device_id' => 'test-device',
            'status' => 'pending',
            'expires_at' => now()->addMinutes(2),
        ]);

        $response = $this->postJson('/api/v1/auth/higher-access/respond', [
            'request_id' => $accessRequest->request_id,
            'status' => 'denied',
            'message' => 'Not authorized',
        ]);

        $response->assertStatus(200);

        $accessRequest->refresh();
        $this->assertEquals('denied', $accessRequest->status);
    }

    public function test_cannot_respond_to_expired_request(): void
    {
        Passport::actingAs($this->approver);

        $accessRequest = HigherAccessRequest::create([
            'request_id' => Str::uuid(),
            'user_id' => $this->user->id,
            'user_name' => $this->user->name,
            'store_id' => $this->store->id,
            'store_name' => $this->store->name,
            'pos_id' => $this->pos->id,
            'pos_name' => $this->pos->name,
            'permission_type' => 'discounts',
            'device_id' => 'test-device',
            'status' => 'pending',
            'expires_at' => now()->subMinute(),
        ]);

        $response = $this->postJson('/api/v1/auth/higher-access/respond', [
            'request_id' => $accessRequest->request_id,
            'status' => 'approved',
        ]);

        $response->assertStatus(410);
    }

    public function test_can_cancel_own_pending_request(): void
    {
        Passport::actingAs($this->user);

        $accessRequest = HigherAccessRequest::create([
            'request_id' => Str::uuid(),
            'user_id' => $this->user->id,
            'user_name' => $this->user->name,
            'store_id' => $this->store->id,
            'store_name' => $this->store->name,
            'pos_id' => $this->pos->id,
            'pos_name' => $this->pos->name,
            'permission_type' => 'discounts',
            'device_id' => 'test-device',
            'status' => 'pending',
            'expires_at' => now()->addMinutes(2),
        ]);

        $response = $this->postJson("/api/v1/auth/higher-access/cancel/{$accessRequest->request_id}");

        $response->assertStatus(200);

        $accessRequest->refresh();
        $this->assertEquals('cancelled', $accessRequest->status);
    }

    public function test_cannot_cancel_other_users_request(): void
    {
        Passport::actingAs($this->approver);

        $accessRequest = HigherAccessRequest::create([
            'request_id' => Str::uuid(),
            'user_id' => $this->user->id,
            'user_name' => $this->user->name,
            'store_id' => $this->store->id,
            'store_name' => $this->store->name,
            'pos_id' => $this->pos->id,
            'pos_name' => $this->pos->name,
            'permission_type' => 'discounts',
            'device_id' => 'test-device',
            'status' => 'pending',
            'expires_at' => now()->addMinutes(2),
        ]);

        $response = $this->postJson("/api/v1/auth/higher-access/cancel/{$accessRequest->request_id}");

        $response->assertStatus(403);
    }

    public function test_can_list_pending_requests(): void
    {
        Passport::actingAs($this->approver);

        HigherAccessRequest::create([
            'request_id' => Str::uuid(),
            'user_id' => $this->user->id,
            'user_name' => $this->user->name,
            'store_id' => $this->store->id,
            'store_name' => $this->store->name,
            'pos_id' => $this->pos->id,
            'pos_name' => $this->pos->name,
            'permission_type' => 'discounts',
            'device_id' => 'test-device',
            'status' => 'pending',
            'expires_at' => now()->addMinutes(2),
        ]);

        $response = $this->getJson('/api/v1/auth/higher-access/pending?store_id='.$this->store->id);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => ['requests'],
        ]);
    }

    public function test_validation_requires_all_fields(): void
    {
        Passport::actingAs($this->user);

        $response = $this->postJson('/api/v1/auth/higher-access/request', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors([
            'user_id',
            'user_name',
            'store_id',
            'store_name',
            'pos_id',
            'pos_name',
            'permission_type',
            'device_id',
        ]);
    }

    public function test_permission_type_must_be_valid(): void
    {
        Passport::actingAs($this->user);

        $response = $this->postJson('/api/v1/auth/higher-access/request', [
            'user_id' => $this->user->id,
            'user_name' => $this->user->name,
            'store_id' => $this->store->id,
            'store_name' => $this->store->name,
            'pos_id' => $this->pos->id,
            'pos_name' => $this->pos->name,
            'permission_type' => 'invalid_permission',
            'device_id' => 'test-device',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['permission_type']);
    }
}
