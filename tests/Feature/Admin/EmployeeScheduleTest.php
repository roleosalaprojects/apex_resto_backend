<?php

namespace Tests\Feature\Admin;

use App\Models\Employees\Employee;
use App\Models\Employees\EmployeeSchedule;
use App\Models\Employees\Role;
use App\Models\Settings\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeScheduleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Role $adminRole;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::factory()->admin()->create();
        $this->admin = User::factory()->create([
            'role_id' => $this->adminRole->id,
            'user_id' => 1,
        ]);
        $this->admin->update(['user_id' => $this->admin->id]);

        // Create employee details for navbar
        Employee::create([
            'user_id' => $this->admin->id,
            'phone' => '123456789',
            'address' => 'Test Address',
            'status' => true,
            'image' => null,
        ]);

        $this->store = Store::factory()->create([
            'user_id' => $this->admin->id,
        ]);
    }

    public function test_can_view_schedules_index(): void
    {
        $this->actingAs($this->admin)
            ->get(route('schedules.index'))
            ->assertOk()
            ->assertViewIs('admin.employees.schedules.index');
    }

    public function test_can_view_schedule_edit_form(): void
    {
        $employee = User::factory()->create([
            'user_id' => $this->admin->id,
            'role_id' => $this->adminRole->id,
        ]);

        $this->actingAs($this->admin)
            ->get(route('schedules.edit', $employee))
            ->assertOk()
            ->assertViewIs('admin.employees.schedules.edit')
            ->assertViewHas('employee')
            ->assertViewHas('schedules');
    }

    public function test_can_update_employee_schedule(): void
    {
        $employee = User::factory()->create([
            'user_id' => $this->admin->id,
            'role_id' => $this->adminRole->id,
        ]);

        $scheduleData = [
            'schedules' => [
                0 => ['start_time' => null, 'is_rest_day' => '1'], // Sunday - rest
                1 => ['start_time' => '08:00'], // Monday
                2 => ['start_time' => '08:00'], // Tuesday
                3 => ['start_time' => '08:00'], // Wednesday
                4 => ['start_time' => '08:00'], // Thursday
                5 => ['start_time' => '08:00'], // Friday
                6 => ['start_time' => null, 'is_rest_day' => '1'], // Saturday - rest
            ],
        ];

        $response = $this->actingAs($this->admin)
            ->put(route('schedules.update', $employee), $scheduleData);

        $response->assertRedirect(route('schedules.index'));

        // Check Monday schedule was created
        $this->assertDatabaseHas('employee_schedules', [
            'user_id' => $employee->id,
            'day_of_week' => 1,
            'start_time' => '08:00:00',
        ]);

        // Check Sunday is rest day (null start_time)
        $this->assertDatabaseHas('employee_schedules', [
            'user_id' => $employee->id,
            'day_of_week' => 0,
            'start_time' => null,
        ]);
    }

    public function test_schedule_table_shows_employee_schedules(): void
    {
        $employee = User::factory()->create([
            'user_id' => $this->admin->id,
            'role_id' => $this->adminRole->id,
        ]);

        EmployeeSchedule::factory()
            ->forDay(1) // Monday
            ->startingAt('09:00')
            ->create(['user_id' => $employee->id]);

        $response = $this->actingAs($this->admin)
            ->get(route('schedules.table'));

        $response->assertOk()
            ->assertViewIs('admin.employees.schedules.table')
            ->assertViewHas('employees');
    }

    public function test_schedule_table_can_filter_by_search(): void
    {
        $employee1 = User::factory()->create([
            'user_id' => $this->admin->id,
            'role_id' => $this->adminRole->id,
            'name' => 'John Doe',
        ]);

        $employee2 = User::factory()->create([
            'user_id' => $this->admin->id,
            'role_id' => $this->adminRole->id,
            'name' => 'Jane Smith',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('schedules.table', ['search' => 'John']));

        $response->assertOk();
        $employees = $response->viewData('employees');
        $this->assertTrue($employees->contains('id', $employee1->id));
        $this->assertFalse($employees->contains('id', $employee2->id));
    }

    public function test_update_schedule_validates_time_format(): void
    {
        $employee = User::factory()->create([
            'user_id' => $this->admin->id,
            'role_id' => $this->adminRole->id,
        ]);

        $scheduleData = [
            'schedules' => [
                0 => ['start_time' => 'invalid'],
                1 => ['start_time' => '08:00'],
                2 => ['start_time' => '08:00'],
                3 => ['start_time' => '08:00'],
                4 => ['start_time' => '08:00'],
                5 => ['start_time' => '08:00'],
                6 => ['start_time' => '08:00'],
            ],
        ];

        $response = $this->actingAs($this->admin)
            ->put(route('schedules.update', $employee), $scheduleData);

        $response->assertSessionHasErrors(['schedules.0.start_time']);
    }

    public function test_existing_schedule_is_updated_not_duplicated(): void
    {
        $employee = User::factory()->create([
            'user_id' => $this->admin->id,
            'role_id' => $this->adminRole->id,
        ]);

        // Create initial schedule
        EmployeeSchedule::factory()
            ->forDay(1)
            ->startingAt('08:00')
            ->create(['user_id' => $employee->id]);

        $scheduleData = [
            'schedules' => [
                0 => ['start_time' => null, 'is_rest_day' => '1'],
                1 => ['start_time' => '09:00'], // Updated Monday
                2 => ['start_time' => '09:00'],
                3 => ['start_time' => '09:00'],
                4 => ['start_time' => '09:00'],
                5 => ['start_time' => '09:00'],
                6 => ['start_time' => null, 'is_rest_day' => '1'],
            ],
        ];

        $this->actingAs($this->admin)
            ->put(route('schedules.update', $employee), $scheduleData);

        // Should have exactly one Monday schedule
        $mondaySchedules = EmployeeSchedule::where('user_id', $employee->id)
            ->where('day_of_week', 1)
            ->get();

        $this->assertCount(1, $mondaySchedules);
        $this->assertEquals('09:00:00', $mondaySchedules->first()->start_time->format('H:i:s'));
    }

    public function test_user_without_permission_cannot_access_schedules(): void
    {
        $restrictedRole = Role::factory()->create([
            'attndnc_schedules' => false,
        ]);

        $restrictedUser = User::factory()->create([
            'role_id' => $restrictedRole->id,
            'user_id' => 1,
        ]);
        $restrictedUser->update(['user_id' => $restrictedUser->id]);

        Employee::create([
            'user_id' => $restrictedUser->id,
            'phone' => '123456789',
            'address' => 'Test Address',
            'status' => true,
            'image' => null,
        ]);

        $response = $this->actingAs($restrictedUser)
            ->get(route('schedules.index'));

        $response->assertRedirect(route('admin.home'));
    }
}
