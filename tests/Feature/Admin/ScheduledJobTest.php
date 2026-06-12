<?php

namespace Tests\Feature\Admin;

use App\Models\Employees\Role;
use App\Models\Reports\AuditLog;
use App\Models\ScheduledJob;
use App\Models\User;
use Database\Seeders\ScheduledJobSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ScheduledJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ScheduledJobSeeder::class);
    }

    private function adminUser(): User
    {
        $role = Role::factory()->admin()->create();

        return User::factory()->create(['role_id' => $role->id]);
    }

    private function nonAdminUser(): User
    {
        $role = Role::factory()->create(['sttngs' => false]);

        return User::factory()->create(['role_id' => $role->id]);
    }

    public function test_non_admin_cannot_load_index(): void
    {
        $this->actingAs($this->nonAdminUser())
            ->get(route('scheduled-jobs.index'))
            ->assertForbidden();
    }

    public function test_admin_sees_all_seeded_jobs(): void
    {
        $response = $this->actingAs($this->adminUser())
            ->get(route('scheduled-jobs.index'))
            ->assertOk();

        foreach ([
            ScheduledJob::KEY_HIGHER_ACCESS_EXPIRE,
            ScheduledJob::KEY_WEATHER_FETCH,
            ScheduledJob::KEY_REPORT_DAILY,
            ScheduledJob::KEY_REPORT_WEEKLY,
            ScheduledJob::KEY_SMS_LOGS_POLL,
            ScheduledJob::KEY_DAILY_SALES_SUMMARY,
            ScheduledJob::KEY_FIRE_ALERTS,
        ] as $key) {
            $response->assertSee($key);
        }
    }

    public function test_toggle_flips_enabled_and_stamps_updated_by(): void
    {
        $admin = $this->adminUser();
        $job = ScheduledJob::findByKey(ScheduledJob::KEY_FIRE_ALERTS);
        $this->assertTrue($job->enabled);

        $this->actingAs($admin)
            ->post(route('scheduled-jobs.toggle', $job))
            ->assertRedirect(route('scheduled-jobs.index'))
            ->assertSessionHas('success');

        $job->refresh();
        $this->assertFalse($job->enabled);
        $this->assertSame($admin->id, $job->updated_by);

        // And again — second click re-enables.
        $this->actingAs($admin)->post(route('scheduled-jobs.toggle', $job));
        $job->refresh();
        $this->assertTrue($job->enabled);
    }

    public function test_toggle_writes_audit_log_row(): void
    {
        $admin = $this->adminUser();
        $job = ScheduledJob::findByKey(ScheduledJob::KEY_WEATHER_FETCH);

        $this->actingAs($admin)
            ->post(route('scheduled-jobs.toggle', $job))
            ->assertRedirect();

        $audit = AuditLog::where('auditable_type', ScheduledJob::class)
            ->where('auditable_id', $job->id)
            ->where('event', 'scheduled_job_toggled')
            ->first();

        $this->assertNotNull($audit, 'Toggling a scheduled job must be audited.');
        $this->assertSame($admin->id, $audit->user_id);
        $this->assertTrue($audit->old_values['enabled']);
        $this->assertFalse($audit->new_values['enabled']);
        $this->assertSame(ScheduledJob::KEY_WEATHER_FETCH, $audit->new_values['key']);
    }

    public function test_non_admin_cannot_toggle(): void
    {
        $job = ScheduledJob::findByKey(ScheduledJob::KEY_FIRE_ALERTS);

        $this->actingAs($this->nonAdminUser())
            ->post(route('scheduled-jobs.toggle', $job))
            ->assertForbidden();

        $job->refresh();
        $this->assertTrue($job->enabled, 'Forbidden request must not flip the row.');
    }

    public function test_run_now_invokes_artisan_and_stamps_last_run(): void
    {
        $admin = $this->adminUser();
        $job = ScheduledJob::findByKey(ScheduledJob::KEY_HIGHER_ACCESS_EXPIRE);

        // Mock to avoid actually firing the command — we're testing the
        // controller surface, not the command body.
        Artisan::shouldReceive('call')
            ->once()
            ->with('higher-access:expire', [])
            ->andReturn(0);

        $this->actingAs($admin)
            ->post(route('scheduled-jobs.run-now', $job))
            ->assertRedirect(route('scheduled-jobs.index'))
            ->assertSessionHas('success');

        $job->refresh();
        $this->assertNotNull($job->last_run_at);
        $this->assertSame(ScheduledJob::STATUS_SUCCESS, $job->last_run_status);
    }

    public function test_run_now_parses_artisan_flags_from_key(): void
    {
        $admin = $this->adminUser();
        $job = ScheduledJob::findByKey(ScheduledJob::KEY_REPORT_DAILY);

        Artisan::shouldReceive('call')
            ->once()
            ->with('report:generate', ['--type' => 'daily'])
            ->andReturn(0);

        $this->actingAs($admin)
            ->post(route('scheduled-jobs.run-now', $job))
            ->assertRedirect()
            ->assertSessionHas('success');
    }

    public function test_run_now_failure_marks_failed_and_records_error(): void
    {
        $admin = $this->adminUser();
        $job = ScheduledJob::findByKey(ScheduledJob::KEY_HIGHER_ACCESS_EXPIRE);

        Artisan::shouldReceive('call')
            ->once()
            ->andThrow(new \RuntimeException('boom'));

        $this->actingAs($admin)
            ->post(route('scheduled-jobs.run-now', $job))
            ->assertRedirect()
            ->assertSessionHas('error');

        $job->refresh();
        $this->assertSame(ScheduledJob::STATUS_FAILED, $job->last_run_status);

        $audit = AuditLog::where('auditable_type', ScheduledJob::class)
            ->where('auditable_id', $job->id)
            ->where('event', 'scheduled_job_run_now')
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame('failed', $audit->new_values['status']);
        $this->assertSame('boom', $audit->new_values['error']);
    }

    public function test_run_now_writes_audit_log_row(): void
    {
        $admin = $this->adminUser();
        $job = ScheduledJob::findByKey(ScheduledJob::KEY_HIGHER_ACCESS_EXPIRE);

        Artisan::shouldReceive('call')->once()->andReturn(0);

        $this->actingAs($admin)
            ->post(route('scheduled-jobs.run-now', $job))
            ->assertRedirect();

        $audit = AuditLog::where('auditable_type', ScheduledJob::class)
            ->where('auditable_id', $job->id)
            ->where('event', 'scheduled_job_run_now')
            ->first();

        $this->assertNotNull($audit, 'Manual run must be audited.');
        $this->assertSame($admin->id, $audit->user_id);
        $this->assertSame('admin', $audit->new_values['triggered_by']);
        $this->assertSame('success', $audit->new_values['status']);
    }

    public function test_non_admin_cannot_run_now(): void
    {
        $job = ScheduledJob::findByKey(ScheduledJob::KEY_HIGHER_ACCESS_EXPIRE);

        Artisan::shouldReceive('call')->never();

        $this->actingAs($this->nonAdminUser())
            ->post(route('scheduled-jobs.run-now', $job))
            ->assertForbidden();
    }

    public function test_is_enabled_defaults_true_for_missing_row(): void
    {
        $this->assertTrue(
            ScheduledJob::isEnabled('made:up:command'),
            'No row → assume enabled so a fresh deploy does not silently mute scheduled work.',
        );
    }

    public function test_is_enabled_reflects_persisted_state(): void
    {
        ScheduledJob::findByKey(ScheduledJob::KEY_FIRE_ALERTS)->update(['enabled' => false]);

        $this->assertFalse(ScheduledJob::isEnabled(ScheduledJob::KEY_FIRE_ALERTS));
    }

    public function test_record_run_no_ops_for_missing_row(): void
    {
        // Should not throw even when the row doesn't exist — defensive
        // so the scheduler hook never crashes a real command run.
        ScheduledJob::recordRun('nope:nope', 'success', 12);
        $this->assertTrue(true);
    }

    public function test_seeder_is_idempotent_and_preserves_admin_edits(): void
    {
        $job = ScheduledJob::findByKey(ScheduledJob::KEY_FIRE_ALERTS);
        $job->update(['enabled' => false]);

        $this->seed(ScheduledJobSeeder::class);

        $job->refresh();
        $this->assertFalse($job->enabled, 'Re-seeding must not clobber admin toggle state.');
        $this->assertSame(
            8,
            ScheduledJob::count(),
            'All 8 canonical keys remain after re-seed.',
        );
    }
}
