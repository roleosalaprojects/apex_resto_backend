<?php

namespace Tests\Feature\API\v1\openclaw;

use App\Models\ApiToken;
use App\Models\Employees\AttendanceRecord;
use App\Models\Employees\Role;
use App\Models\Settings\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class OpenclawAttendanceRecordsTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected User $otherOwner;

    protected User $employeeJoel;

    protected User $employeeElvie;

    protected Store $store;

    protected string $readToken;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::factory()->admin()->create();

        $this->owner = User::factory()->create(['role_id' => $role->id]);
        $this->owner->forceFill(['user_id' => $this->owner->id])->save();

        $this->otherOwner = User::factory()->create(['role_id' => $role->id]);
        $this->otherOwner->forceFill(['user_id' => $this->otherOwner->id])->save();

        $this->employeeJoel = User::factory()->create([
            'role_id' => $role->id,
            'user_id' => $this->owner->user_id,
            'name' => 'JOEL DURAY',
        ]);
        $this->employeeElvie = User::factory()->create([
            'role_id' => $role->id,
            'user_id' => $this->owner->user_id,
            'name' => 'ELVIE BERNABA',
        ]);

        $this->store = Store::factory()->create(['user_id' => $this->owner->user_id]);

        $this->readToken = $this->mintToken(['openclaw:read']);
    }

    private function mintToken(array $abilities): string
    {
        $plain = ApiToken::generatePlainToken();
        ApiToken::create([
            'user_id' => $this->owner->user_id,
            'name' => 'Test',
            'token' => ApiToken::hashToken($plain),
            'abilities' => $abilities,
        ]);

        return $plain;
    }

    private function makeRecord(User $employee, string $date, bool $late = false, int $lateMinutes = 0, string $status = 'present'): AttendanceRecord
    {
        return AttendanceRecord::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $employee->id,
            'store_id' => $this->store->id,
            'date' => $date,
            'status' => $status,
            'is_late' => $late,
            'late_minutes' => $lateMinutes,
            'hours_rendered' => 8,
        ]);
    }

    public function test_records_returns_row_per_day_with_employee_name(): void
    {
        $this->makeRecord($this->employeeJoel, '2026-04-26', late: true, lateMinutes: 6);
        $this->makeRecord($this->employeeJoel, '2026-04-29', late: true, lateMinutes: 3);
        $this->makeRecord($this->employeeJoel, '2026-05-02');
        $this->makeRecord($this->employeeElvie, '2026-04-26');

        $response = $this->withHeader('Authorization', "Bearer {$this->readToken}")
            ->getJson('/api/v1/openclaw/attendance/records?date_from=2026-04-01&date_to=2026-05-10');

        $response->assertStatus(200)
            ->assertJsonPath('data.count', 4)
            ->assertJsonStructure([
                'data' => [
                    'records' => [
                        '*' => [
                            'id', 'user_id', 'employee_name', 'date',
                            'is_late', 'late_minutes', 'status', 'hours_rendered',
                        ],
                    ],
                ],
            ]);

        $rows = collect($response->json('data.records'));
        $joelLates = $rows->where('employee_name', 'JOEL DURAY')->where('is_late', true);
        $this->assertSame(2, $joelLates->count());
        $this->assertSame([3, 6], $joelLates->pluck('late_minutes')->sort()->values()->all());
        $this->assertSame(['2026-04-26', '2026-04-29'], $joelLates->pluck('date')->sort()->values()->all());
    }

    public function test_only_late_filter_excludes_on_time_records(): void
    {
        $this->makeRecord($this->employeeJoel, '2026-04-26', late: true, lateMinutes: 6);
        $this->makeRecord($this->employeeJoel, '2026-05-02', late: false, lateMinutes: 0);

        $response = $this->withHeader('Authorization', "Bearer {$this->readToken}")
            ->getJson('/api/v1/openclaw/attendance/records?date_from=2026-04-01&date_to=2026-05-10&only_late=1');

        $response->assertStatus(200)->assertJsonPath('data.count', 1);
        $this->assertSame('2026-04-26', $response->json('data.records.0.date'));
    }

    public function test_user_id_filter_scopes_to_one_employee(): void
    {
        $this->makeRecord($this->employeeJoel, '2026-04-26', late: true, lateMinutes: 6);
        $this->makeRecord($this->employeeElvie, '2026-04-26', late: true, lateMinutes: 5);

        $response = $this->withHeader('Authorization', "Bearer {$this->readToken}")
            ->getJson("/api/v1/openclaw/attendance/records?date_from=2026-04-01&date_to=2026-05-10&user_id={$this->employeeJoel->id}");

        $response->assertStatus(200)->assertJsonPath('data.count', 1);
        $this->assertSame('JOEL DURAY', $response->json('data.records.0.employee_name'));
    }

    public function test_records_excludes_other_tenant_employees(): void
    {
        $foreignEmployee = User::factory()->create([
            'role_id' => $this->employeeJoel->role_id,
            'user_id' => $this->otherOwner->user_id,
            'name' => 'OUTSIDER',
        ]);

        $this->makeRecord($this->employeeJoel, '2026-04-26', late: true, lateMinutes: 6);
        AttendanceRecord::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $foreignEmployee->id,
            'store_id' => $this->store->id,
            'date' => '2026-04-26',
            'status' => 'present',
            'is_late' => true,
            'late_minutes' => 999,
            'hours_rendered' => 8,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->readToken}")
            ->getJson('/api/v1/openclaw/attendance/records?date_from=2026-04-01&date_to=2026-05-10');

        $response->assertStatus(200);
        $names = collect($response->json('data.records'))->pluck('employee_name')->all();
        $this->assertContains('JOEL DURAY', $names);
        $this->assertNotContains('OUTSIDER', $names);
    }

    public function test_unauthenticated_returns_401(): void
    {
        $this->getJson('/api/v1/openclaw/attendance/records')->assertStatus(401);
    }
}
