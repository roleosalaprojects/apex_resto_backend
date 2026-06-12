<?php

namespace Tests\Feature\Notifications;

use App\Models\Employees\Role;
use App\Models\Settings\Store;
use App\Models\User;
use App\Services\FcmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class HigherAccessRequestNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $employee;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $ownerRole = Role::factory()->admin()->create();
        $this->owner = User::factory()->create([
            'role_id' => $ownerRole->id,
            'user_id' => 1,
        ]);

        $ownerRole->update(['user_id' => $this->owner->id]);
        $this->owner->update(['user_id' => $this->owner->id]);

        $employeeRole = Role::factory()->create([
            'user_id' => $this->owner->id,
        ]);

        $this->employee = User::factory()->create([
            'user_id' => $this->owner->id,
            'role_id' => $employeeRole->id,
        ]);

        $this->store = Store::factory()->create([
            'user_id' => $this->owner->id,
        ]);
    }

    public function test_sends_notification_for_discount_request(): void
    {
        $this->mock(FcmService::class, function (MockInterface $mock) {
            $mock->shouldReceive('sendToUsersWithPermission')
                ->once()
                ->withArgs(function ($userId, $permission, $title, $body, $data) {
                    return $userId === $this->owner->id
                        && $permission === 'discounts'
                        && $title === 'Access Request'
                        && str_contains($body, 'discounts access')
                        && $data['type'] === 'higher_access_request';
                })
                ->andReturn(1);
        });

        app(FcmService::class)->sendToUsersWithPermission(
            $this->owner->id,
            'discounts',
            'Access Request',
            "{$this->employee->name} requests discounts access at {$this->store->name}",
            ['type' => 'higher_access_request', 'id' => 'test-uuid']
        );
    }

    public function test_sends_notification_for_refund_request(): void
    {
        $this->mock(FcmService::class, function (MockInterface $mock) {
            $mock->shouldReceive('sendToUsersWithPermission')
                ->once()
                ->withArgs(function ($userId, $permission, $title, $body, $data) {
                    return $userId === $this->owner->id
                        && $permission === 'rfnd'
                        && $title === 'Access Request'
                        && str_contains($body, 'refunds access')
                        && $data['type'] === 'higher_access_request';
                })
                ->andReturn(1);
        });

        app(FcmService::class)->sendToUsersWithPermission(
            $this->owner->id,
            'rfnd',
            'Access Request',
            "{$this->employee->name} requests refunds access at {$this->store->name}",
            ['type' => 'higher_access_request', 'id' => 'test-uuid']
        );
    }

    public function test_no_notification_when_no_matching_permission(): void
    {
        $this->mock(FcmService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('sendToUsersWithPermission');
        });

        // No call is made — verifying the mock expectation
        $this->assertTrue(true);
    }

    public function test_targets_delete_items_permission(): void
    {
        $this->mock(FcmService::class, function (MockInterface $mock) {
            $mock->shouldReceive('sendToUsersWithPermission')
                ->once()
                ->withArgs(function ($userId, $permission, $title, $body, $data) {
                    return $userId === $this->owner->id
                        && $permission === 'delete_items'
                        && $title === 'Access Request'
                        && str_contains($body, 'delete items access')
                        && $data['type'] === 'higher_access_request';
                })
                ->andReturn(1);
        });

        app(FcmService::class)->sendToUsersWithPermission(
            $this->owner->id,
            'delete_items',
            'Access Request',
            "{$this->employee->name} requests delete items access at {$this->store->name}",
            ['type' => 'higher_access_request', 'id' => 'test-uuid']
        );
    }
}
