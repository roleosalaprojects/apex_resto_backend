<?php

namespace Tests\Feature\Admin;

use App\Models\Employees\AttendanceRecord;
use App\Models\Employees\Employee;
use App\Models\Employees\Role;
use App\Models\Reports\AuditLog;
use App\Models\Settings\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceTest extends TestCase
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

    public function test_can_view_attendance_index(): void
    {
        $this->actingAs($this->admin)
            ->get(route('attendance.index'))
            ->assertOk()
            ->assertViewIs('admin.employees.attendance.index');
    }

    public function test_export_returns_all_records_as_csv(): void
    {
        $employee = User::factory()->create([
            'user_id' => $this->admin->id,
            'role_id' => $this->adminRole->id,
        ]);

        foreach (range(1, 30) as $i) {
            AttendanceRecord::factory()
                ->forDate(now()->subDays($i))
                ->create([
                    'user_id' => $employee->id,
                    'store_id' => $this->store->id,
                ]);
        }

        $response = $this->actingAs($this->admin)
            ->get(route('attendance.export'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/csv; charset=utf-8');

        $lines = array_filter(explode("\n", trim($response->streamedContent())));
        $this->assertCount(31, $lines);
        $this->assertStringContainsString('Date,Employee,Store', $lines[0]);
    }

    public function test_export_applies_filters(): void
    {
        $employee = User::factory()->create([
            'user_id' => $this->admin->id,
            'role_id' => $this->adminRole->id,
        ]);

        $otherStore = Store::factory()->create(['user_id' => $this->admin->id]);

        AttendanceRecord::factory()
            ->forDate(now()->subDay())
            ->create([
                'user_id' => $employee->id,
                'store_id' => $this->store->id,
            ]);

        AttendanceRecord::factory()
            ->forDate(now()->subDays(2))
            ->create([
                'user_id' => $employee->id,
                'store_id' => $otherStore->id,
            ]);

        $response = $this->actingAs($this->admin)
            ->get(route('attendance.export', ['store_id' => $this->store->id]));

        $response->assertOk();

        $lines = array_filter(explode("\n", trim($response->streamedContent())));
        $this->assertCount(2, $lines);
    }

    public function test_table_returns_json_with_records(): void
    {
        $employee = User::factory()->create([
            'user_id' => $this->admin->id,
            'role_id' => $this->adminRole->id,
        ]);

        foreach (range(1, 3) as $i) {
            AttendanceRecord::factory()
                ->forDate(now()->subDays($i))
                ->create([
                    'user_id' => $employee->id,
                    'store_id' => $this->store->id,
                ]);
        }

        $response = $this->actingAs($this->admin)
            ->getJson(route('attendance.table'));

        $response->assertOk();
        $response->assertJsonStructure(['data']);
        $response->assertJsonCount(3, 'data');
    }

    public function test_table_filters_by_store(): void
    {
        $employee = User::factory()->create([
            'user_id' => $this->admin->id,
            'role_id' => $this->adminRole->id,
        ]);

        $otherStore = Store::factory()->create(['user_id' => $this->admin->id]);

        AttendanceRecord::factory()->forDate(now()->subDays(1))->create([
            'user_id' => $employee->id,
            'store_id' => $this->store->id,
        ]);
        AttendanceRecord::factory()->forDate(now()->subDays(2))->create([
            'user_id' => $employee->id,
            'store_id' => $this->store->id,
        ]);

        AttendanceRecord::factory()->forDate(now()->subDays(3))->create([
            'user_id' => $employee->id,
            'store_id' => $otherStore->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson(route('attendance.table', ['store_id' => $this->store->id]));

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    public function test_table_filters_by_employee(): void
    {
        $employee1 = User::factory()->create([
            'user_id' => $this->admin->id,
            'role_id' => $this->adminRole->id,
        ]);
        $employee2 = User::factory()->create([
            'user_id' => $this->admin->id,
            'role_id' => $this->adminRole->id,
        ]);

        AttendanceRecord::factory()->forDate(now()->subDays(1))->create([
            'user_id' => $employee1->id,
            'store_id' => $this->store->id,
        ]);
        AttendanceRecord::factory()->forDate(now()->subDays(2))->create([
            'user_id' => $employee1->id,
            'store_id' => $this->store->id,
        ]);

        AttendanceRecord::factory()->forDate(now()->subDays(1))->create([
            'user_id' => $employee2->id,
            'store_id' => $this->store->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson(route('attendance.table', ['user_id' => $employee1->id]));

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    public function test_table_filters_by_status(): void
    {
        $employee = User::factory()->create([
            'user_id' => $this->admin->id,
            'role_id' => $this->adminRole->id,
        ]);

        AttendanceRecord::factory()->present()->forDate(now()->subDays(1))->create([
            'user_id' => $employee->id,
            'store_id' => $this->store->id,
        ]);
        AttendanceRecord::factory()->present()->forDate(now()->subDays(2))->create([
            'user_id' => $employee->id,
            'store_id' => $this->store->id,
        ]);

        AttendanceRecord::factory()->absent()->forDate(now()->subDays(3))->create([
            'user_id' => $employee->id,
            'store_id' => $this->store->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson(route('attendance.table', ['status' => 'present']));

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    public function test_table_filters_by_late_status(): void
    {
        $employee = User::factory()->create([
            'user_id' => $this->admin->id,
            'role_id' => $this->adminRole->id,
        ]);

        AttendanceRecord::factory()->late()->create([
            'user_id' => $employee->id,
            'store_id' => $this->store->id,
        ]);

        AttendanceRecord::factory()->onTime()->create([
            'user_id' => $employee->id,
            'store_id' => $this->store->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson(route('attendance.table', ['status' => 'late']));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    public function test_table_filters_by_date_range(): void
    {
        $employee = User::factory()->create([
            'user_id' => $this->admin->id,
            'role_id' => $this->adminRole->id,
        ]);

        AttendanceRecord::factory()->create([
            'user_id' => $employee->id,
            'store_id' => $this->store->id,
            'date' => now()->subDays(1),
        ]);

        AttendanceRecord::factory()->create([
            'user_id' => $employee->id,
            'store_id' => $this->store->id,
            'date' => now()->subDays(30),
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson(route('attendance.table', [
                'date_from' => now()->subDays(7)->format('Y-m-d'),
                'date_to' => now()->format('Y-m-d'),
            ]));

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    public function test_can_view_attendance_create_form(): void
    {
        $this->actingAs($this->admin)
            ->get(route('attendance.create'))
            ->assertOk()
            ->assertViewIs('admin.employees.attendance.create');
    }

    public function test_can_create_attendance_record(): void
    {
        $employee = User::factory()->create([
            'user_id' => $this->admin->id,
            'role_id' => $this->adminRole->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('attendance.store'), [
                'user_id' => $employee->id,
                'store_id' => $this->store->id,
                'date' => '2026-01-26',
                'time_in' => '08:00',
                'time_out' => '17:00',
                'status' => 'present',
                'remarks' => 'Test attendance',
            ]);

        $response->assertRedirect(route('attendance.index'));

        $this->assertDatabaseHas('attendance_records', [
            'user_id' => $employee->id,
            'store_id' => $this->store->id,
            'status' => 'present',
        ]);

        // Verify audit log was created
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => AttendanceRecord::class,
            'event' => 'created',
            'user_id' => $this->admin->id,
        ]);
    }

    public function test_can_update_attendance_record(): void
    {
        $employee = User::factory()->create([
            'user_id' => $this->admin->id,
            'role_id' => $this->adminRole->id,
        ]);

        $attendance = AttendanceRecord::factory()->create([
            'user_id' => $employee->id,
            'store_id' => $this->store->id,
            'status' => 'present',
        ]);

        $response = $this->actingAs($this->admin)
            ->put(route('attendance.update', $attendance), [
                'user_id' => $employee->id,
                'store_id' => $this->store->id,
                'date' => '2026-01-26',
                'time_in' => '09:00',
                'time_out' => '18:00',
                'status' => 'present',
                'remarks' => 'Updated remarks',
            ]);

        $response->assertRedirect(route('attendance.show', $attendance));

        $this->assertDatabaseHas('attendance_records', [
            'id' => $attendance->id,
            'remarks' => 'Updated remarks',
        ]);

        // Verify audit log was created
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => AttendanceRecord::class,
            'auditable_id' => $attendance->id,
            'event' => 'updated',
        ]);
    }

    public function test_can_view_attendance_with_audit_log(): void
    {
        $employee = User::factory()->create([
            'user_id' => $this->admin->id,
            'role_id' => $this->adminRole->id,
        ]);

        $attendance = AttendanceRecord::factory()->create([
            'user_id' => $employee->id,
            'store_id' => $this->store->id,
        ]);

        // Create an audit log entry
        AuditLog::create([
            'user_id' => $this->admin->id,
            'auditable_type' => AttendanceRecord::class,
            'auditable_id' => $attendance->id,
            'event' => 'created',
            'old_values' => [],
            'new_values' => $attendance->toArray(),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test',
            'url' => 'http://test.com',
        ]);

        $this->actingAs($this->admin)
            ->get(route('attendance.show', $attendance))
            ->assertOk()
            ->assertViewIs('admin.employees.attendance.show')
            ->assertViewHas('auditLogs');
    }

    public function test_can_delete_attendance_record(): void
    {
        $employee = User::factory()->create([
            'user_id' => $this->admin->id,
            'role_id' => $this->adminRole->id,
        ]);

        $attendance = AttendanceRecord::factory()->create([
            'user_id' => $employee->id,
            'store_id' => $this->store->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->delete(route('attendance.destroy', $attendance));

        $response->assertRedirect(route('attendance.index'));

        $this->assertDatabaseMissing('attendance_records', [
            'id' => $attendance->id,
        ]);

        // Verify audit log was created for deletion
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => AttendanceRecord::class,
            'auditable_id' => $attendance->id,
            'event' => 'deleted',
        ]);
    }

    public function test_can_view_attendance_summary(): void
    {
        $this->actingAs($this->admin)
            ->get(route('attendance.summary'))
            ->assertOk()
            ->assertViewIs('admin.employees.attendance.summary');
    }

    public function test_audit_log_tracks_changes(): void
    {
        $employee = User::factory()->create([
            'user_id' => $this->admin->id,
            'role_id' => $this->adminRole->id,
        ]);

        $attendance = AttendanceRecord::factory()->create([
            'user_id' => $employee->id,
            'store_id' => $this->store->id,
            'status' => 'present',
            'remarks' => 'Original remarks',
        ]);

        // Update the record
        $this->actingAs($this->admin)
            ->put(route('attendance.update', $attendance), [
                'user_id' => $employee->id,
                'store_id' => $this->store->id,
                'date' => $attendance->date->format('Y-m-d'),
                'time_in' => '08:00',
                'time_out' => '17:00',
                'status' => 'absent',
                'remarks' => 'Changed to absent',
            ]);

        $auditLog = AuditLog::where('auditable_type', AttendanceRecord::class)
            ->where('auditable_id', $attendance->id)
            ->where('event', 'updated')
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertEquals('present', $auditLog->old_values['status']);
        $this->assertEquals('absent', $auditLog->new_values['status']);
    }

    public function test_calendar_events_returns_json(): void
    {
        $employee = User::factory()->create([
            'user_id' => $this->admin->id,
            'role_id' => $this->adminRole->id,
        ]);

        AttendanceRecord::factory()->present()->onTime()->forDate(now()->subDays(1))->create([
            'user_id' => $employee->id,
            'store_id' => $this->store->id,
        ]);

        AttendanceRecord::factory()->present()->late()->forDate(now()->subDays(2))->create([
            'user_id' => $employee->id,
            'store_id' => $this->store->id,
        ]);

        AttendanceRecord::factory()->absent()->forDate(now()->subDays(3))->create([
            'user_id' => $employee->id,
            'store_id' => $this->store->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson(route('attendance.calendar-events', [
                'start' => now()->subDays(7)->format('Y-m-d'),
                'end' => now()->format('Y-m-d'),
            ]));

        $response->assertOk();
        $response->assertJsonCount(3);
        $response->assertJsonStructure([
            '*' => ['id', 'title', 'start', 'allDay', 'color', 'extendedProps' => [
                'status', 'employee', 'store', 'time_in', 'time_out', 'hours', 'is_late', 'late_minutes', 'remarks',
            ]],
        ]);

        // Verify colors match config
        $events = $response->json();
        $presentEvent = collect($events)->firstWhere('extendedProps.status', 'present');
        $absentEvent = collect($events)->firstWhere('extendedProps.status', 'absent');

        $this->assertNotNull($presentEvent);
        $this->assertNotNull($absentEvent);
        $this->assertEquals(config('colors.danger'), $absentEvent['color']);
    }

    public function test_calendar_events_filters_by_store(): void
    {
        $employee = User::factory()->create([
            'user_id' => $this->admin->id,
            'role_id' => $this->adminRole->id,
        ]);

        $otherStore = Store::factory()->create(['user_id' => $this->admin->id]);

        AttendanceRecord::factory()->forDate(now()->subDays(1))->create([
            'user_id' => $employee->id,
            'store_id' => $this->store->id,
        ]);

        AttendanceRecord::factory()->forDate(now()->subDays(2))->create([
            'user_id' => $employee->id,
            'store_id' => $otherStore->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson(route('attendance.calendar-events', [
                'store_id' => $this->store->id,
            ]));

        $response->assertOk();
        $response->assertJsonCount(1);
    }

    public function test_calendar_events_filters_by_employee(): void
    {
        $employee1 = User::factory()->create([
            'user_id' => $this->admin->id,
            'role_id' => $this->adminRole->id,
        ]);

        $employee2 = User::factory()->create([
            'user_id' => $this->admin->id,
            'role_id' => $this->adminRole->id,
        ]);

        AttendanceRecord::factory()->forDate(now()->subDays(1))->create([
            'user_id' => $employee1->id,
            'store_id' => $this->store->id,
        ]);

        AttendanceRecord::factory()->forDate(now()->subDays(2))->create([
            'user_id' => $employee1->id,
            'store_id' => $this->store->id,
        ]);

        AttendanceRecord::factory()->forDate(now()->subDays(1))->create([
            'user_id' => $employee2->id,
            'store_id' => $this->store->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson(route('attendance.calendar-events', [
                'user_id' => $employee1->id,
            ]));

        $response->assertOk();
        $response->assertJsonCount(2);
    }

    public function test_calendar_events_filters_by_date_range(): void
    {
        $employee = User::factory()->create([
            'user_id' => $this->admin->id,
            'role_id' => $this->adminRole->id,
        ]);

        AttendanceRecord::factory()->create([
            'user_id' => $employee->id,
            'store_id' => $this->store->id,
            'date' => now()->subDays(3),
        ]);

        AttendanceRecord::factory()->create([
            'user_id' => $employee->id,
            'store_id' => $this->store->id,
            'date' => now()->subDays(30),
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson(route('attendance.calendar-events', [
                'start' => now()->subDays(7)->format('Y-m-d'),
                'end' => now()->format('Y-m-d'),
            ]));

        $response->assertOk();
        $response->assertJsonCount(1);
    }
}
