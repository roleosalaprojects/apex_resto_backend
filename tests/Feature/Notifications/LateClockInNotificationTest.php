<?php

namespace Tests\Feature\Notifications;

use App\Models\Employees\AttendanceRecord;
use App\Models\Employees\Role;
use App\Models\Settings\Store;
use App\Models\User;
use App\Services\FcmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class LateClockInNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $employee;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::factory()->admin()->create();
        $this->owner = User::factory()->create([
            'role_id' => $role->id,
            'user_id' => 1,
        ]);

        $role->update(['user_id' => $this->owner->id]);
        $this->owner->update(['user_id' => $this->owner->id]);

        $employeeRole = Role::factory()->create([
            'user_id' => $this->owner->id,
            'attndnc' => false,
        ]);

        $this->employee = User::factory()->create([
            'user_id' => $this->owner->id,
            'role_id' => $employeeRole->id,
        ]);

        $this->store = Store::factory()->create([
            'user_id' => $this->owner->id,
        ]);
    }

    public function test_sends_notification_when_employee_is_late(): void
    {
        $this->mock(FcmService::class, function (MockInterface $mock) {
            $mock->shouldReceive('sendToUsersWithPermission')
                ->once()
                ->withArgs(function ($userId, $permission, $title, $body, $data) {
                    return $userId === $this->owner->id
                        && $permission === 'attndnc'
                        && $title === 'Late Clock-in'
                        && str_contains($body, '15 minutes late')
                        && str_contains($body, $this->employee->name)
                        && $data['type'] === 'late_clockin';
                })
                ->andReturn(1);
        });

        $record = AttendanceRecord::factory()
            ->late(15)
            ->create([
                'user_id' => $this->employee->id,
                'store_id' => $this->store->id,
                'date' => now()->toDateString(),
            ]);

        $record->load(['user', 'store']);
        $record->notifyLateClockIn();
    }

    public function test_no_notification_when_on_time(): void
    {
        $this->mock(FcmService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('sendToUsersWithPermission');
        });

        $record = AttendanceRecord::factory()
            ->onTime()
            ->create([
                'user_id' => $this->employee->id,
                'store_id' => $this->store->id,
                'date' => now()->toDateString(),
            ]);

        $record->load(['user', 'store']);
        $record->notifyLateClockIn();
    }

    public function test_no_notification_when_late_minutes_zero(): void
    {
        $this->mock(FcmService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('sendToUsersWithPermission');
        });

        $record = AttendanceRecord::factory()->create([
            'user_id' => $this->employee->id,
            'store_id' => $this->store->id,
            'date' => now()->toDateString(),
            'is_late' => true,
            'late_minutes' => 0,
        ]);

        $record->load(['user', 'store']);
        $record->notifyLateClockIn();
    }
}
