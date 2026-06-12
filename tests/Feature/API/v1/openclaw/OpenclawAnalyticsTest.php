<?php

namespace Tests\Feature\API\v1\openclaw;

use App\Models\ApiToken;
use App\Models\Employees\AttendanceRecord;
use App\Models\Employees\Role;
use App\Models\Pos\Sale;
use App\Models\Settings\Store;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpenclawAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected User $otherOwner;

    protected Store $store;

    protected string $plainToken;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::factory()->admin()->create();

        $this->owner = User::factory()->create(['role_id' => $role->id]);
        $this->owner->forceFill(['user_id' => $this->owner->id])->save();

        $this->otherOwner = User::factory()->create(['role_id' => $role->id]);
        $this->otherOwner->forceFill(['user_id' => $this->otherOwner->id])->save();

        $this->store = Store::factory()->create(['user_id' => $this->owner->user_id]);

        $this->plainToken = ApiToken::generatePlainToken();
        ApiToken::create([
            'user_id' => $this->owner->user_id,
            'name' => 'Test Bot',
            'token' => ApiToken::hashToken($this->plainToken),
        ]);
    }

    private function authed(): self
    {
        return $this->withHeader('Authorization', "Bearer {$this->plainToken}");
    }

    public function test_peak_hours_returns_heatmap_data_for_tenant(): void
    {
        Sale::factory()->count(3)->create([
            'user_id' => $this->owner->user_id,
            'store_id' => $this->store->id,
            'sales_by' => $this->owner->id,
            'type' => 0,
            'cancelled' => 0,
            'total' => 100,
            'profit' => 30,
            'created_at' => Carbon::today(config('app.timezone'))->addHours(12),
        ]);

        $response = $this->authed()->getJson('/api/v1/openclaw/analytics/peak-hours?days=7');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['heatmap', 'peak_hours', 'slow_hours', 'busiest_day'],
            ]);
    }

    public function test_attendance_summary_scopes_by_tenant(): void
    {
        $employee = User::factory()->create([
            'role_id' => $this->owner->role_id,
            'user_id' => $this->owner->user_id,
        ]);
        $otherEmployee = User::factory()->create([
            'role_id' => $this->otherOwner->role_id,
            'user_id' => $this->otherOwner->user_id,
        ]);

        AttendanceRecord::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => $employee->id,
            'store_id' => $this->store->id,
            'date' => now()->toDateString(),
            'status' => 'present',
            'is_late' => true,
            'late_minutes' => 15,
            'hours_rendered' => 7.5,
        ]);

        AttendanceRecord::create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'user_id' => $otherEmployee->id,
            'store_id' => $this->store->id,
            'date' => now()->toDateString(),
            'status' => 'present',
            'is_late' => false,
            'late_minutes' => 0,
            'hours_rendered' => 8,
        ]);

        $response = $this->authed()->getJson('/api/v1/openclaw/attendance/summary');

        $response->assertStatus(200)
            ->assertJsonPath('data.totals.total_records', 1)
            ->assertJsonPath('data.totals.days_present', 1)
            ->assertJsonPath('data.totals.days_late', 1);

        $this->assertSame(1, count($response->json('data.by_employee')));
        $this->assertSame($employee->id, $response->json('data.by_employee.0.user_id'));
    }
}
