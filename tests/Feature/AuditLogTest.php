<?php

namespace Tests\Feature;

use App\Models\Employees\Role;
use App\Models\Reports\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Role $role;

    protected function setUp(): void
    {
        parent::setUp();

        $this->role = Role::factory()->admin()->create();
        $this->user = User::factory()->create([
            'role_id' => $this->role->id,
            'user_id' => 1,
        ]);
    }

    public function test_audit_logs_index_route_exists(): void
    {
        $this->actingAs($this->user);

        $this->assertTrue(route('audit_logs.index') !== null);
    }

    public function test_user_can_view_audit_logs_table(): void
    {
        AuditLog::factory()->count(5)->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->get(route('audit_logs.table'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.reports.audit_logs.table');
    }

    public function test_user_can_filter_audit_logs_by_event(): void
    {
        AuditLog::factory()->created()->create(['user_id' => $this->user->id]);
        AuditLog::factory()->updated()->create(['user_id' => $this->user->id]);
        AuditLog::factory()->deleted()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)->get(route('audit_logs.table', ['event' => 'created']));

        $response->assertStatus(200);
    }

    public function test_audit_log_show_route_exists(): void
    {
        $auditLog = AuditLog::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $this->actingAs($this->user);

        $this->assertTrue(route('audit_logs.show', $auditLog) !== null);
    }

    public function test_audit_log_stores_old_and_new_values(): void
    {
        $oldValues = ['name' => 'Old Name'];
        $newValues = ['name' => 'New Name'];

        $auditLog = AuditLog::factory()->create([
            'user_id' => $this->user->id,
            'event' => 'updated',
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);

        $this->assertEquals($oldValues, $auditLog->old_values);
        $this->assertEquals($newValues, $auditLog->new_values);
    }

    public function test_audit_log_calculates_changed_fields(): void
    {
        $auditLog = AuditLog::factory()->create([
            'user_id' => $this->user->id,
            'event' => 'updated',
            'old_values' => ['name' => 'Old Name', 'email' => 'old@example.com'],
            'new_values' => ['name' => 'New Name', 'email' => 'old@example.com'],
        ]);

        $changes = $auditLog->changed_fields;

        $this->assertArrayHasKey('name', $changes);
        $this->assertArrayNotHasKey('email', $changes);
        $this->assertEquals('Old Name', $changes['name']['old']);
        $this->assertEquals('New Name', $changes['name']['new']);
    }

    public function test_audit_log_belongs_to_user(): void
    {
        $auditLog = AuditLog::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $this->assertInstanceOf(User::class, $auditLog->user);
        $this->assertEquals($this->user->id, $auditLog->user->id);
    }

    public function test_audit_log_can_be_created_without_user(): void
    {
        $auditLog = AuditLog::factory()->create([
            'user_id' => null,
        ]);

        $this->assertNull($auditLog->user);
        $this->assertDatabaseHas('audit_logs', [
            'id' => $auditLog->id,
            'user_id' => null,
        ]);
    }

    public function test_audit_log_filter_by_date_range(): void
    {
        AuditLog::factory()->create([
            'user_id' => $this->user->id,
            'created_at' => now()->subDays(5),
        ]);

        AuditLog::factory()->create([
            'user_id' => $this->user->id,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->user)->get(route('audit_logs.table', [
            'date_from' => now()->subDay()->format('Y-m-d'),
            'date_to' => now()->format('Y-m-d'),
        ]));

        $response->assertStatus(200);
    }
}
