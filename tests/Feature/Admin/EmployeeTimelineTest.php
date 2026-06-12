<?php

namespace Tests\Feature\Admin;

use App\Models\Accounting\PosLog;
use App\Models\Employees\Employee;
use App\Models\Employees\Role;
use App\Models\Pos\Sale;
use App\Models\Settings\Pos;
use App\Models\Settings\Store;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeTimelineTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected User $employee;

    protected Store $store;

    protected Pos $terminal;

    protected function setUp(): void
    {
        parent::setUp();

        // Pin to a safe time (noon Manila) so auto-timestamps stay within
        // the UTC-converted day boundaries used by the timeline controller.
        Carbon::setTestNow(Carbon::parse('2026-01-15 12:00:00', config('app.timezone')));

        $role = Role::factory()->admin()->create();
        $this->user = User::factory()->create([
            'role_id' => $role->id,
            'user_id' => 1,
        ]);

        $this->store = Store::factory()->create(['user_id' => $this->user->user_id]);
        $this->terminal = Pos::factory()->create(['store_id' => $this->store->id]);

        $employeeRole = Role::factory()->create([
            'user_id' => $this->user->user_id,
            'emplys' => true,
        ]);
        $this->employee = User::factory()->create([
            'role_id' => $employeeRole->id,
            'user_id' => $this->user->user_id,
        ]);
        Employee::create([
            'phone' => '09171234567',
            'address' => 'Test Address',
            'status' => true,
            'user_id' => $this->employee->id,
        ]);
    }

    public function test_timeline_returns_json_response(): void
    {
        PosLog::factory()->login()->create([
            'user_id' => $this->employee->id,
            'store_id' => $this->store->id,
            'pos_id' => $this->terminal->id,
            'reason' => 'User logged in',
        ]);

        $response = $this->actingAs($this->user)->get('/admin/employees/timeline?'.http_build_query([
            'startDate' => now()->format('Y-m-d'),
            'endDate' => now()->format('Y-m-d'),
            'id' => $this->employee->id,
        ]));

        $response->assertOk();
        $response->assertJsonIsArray();
    }

    public function test_timeline_returns_correct_structure(): void
    {
        PosLog::factory()->login()->create([
            'user_id' => $this->employee->id,
            'store_id' => $this->store->id,
            'pos_id' => $this->terminal->id,
            'reason' => 'User logged in',
        ]);

        $response = $this->actingAs($this->user)->getJson('/admin/employees/timeline?'.http_build_query([
            'startDate' => now()->format('Y-m-d'),
            'endDate' => now()->format('Y-m-d'),
            'id' => $this->employee->id,
        ]));

        $response->assertOk();
        $response->assertJsonStructure([
            [
                'date_group',
                'date_raw',
                'entries' => [
                    [
                        'id',
                        'type',
                        'type_label',
                        'type_color',
                        'type_icon',
                        'reason',
                        'cash_in',
                        'cash_out',
                        'rendered',
                        'sale_son',
                        'sale_total',
                        'sale_id',
                        'pos_name',
                        'store_name',
                        'time',
                    ],
                ],
            ],
        ]);
    }

    public function test_timeline_returns_correct_type_label_and_color(): void
    {
        PosLog::factory()->login()->create([
            'user_id' => $this->employee->id,
            'store_id' => $this->store->id,
            'pos_id' => $this->terminal->id,
        ]);

        $response = $this->actingAs($this->user)->getJson('/admin/employees/timeline?'.http_build_query([
            'startDate' => now()->format('Y-m-d'),
            'endDate' => now()->format('Y-m-d'),
            'id' => $this->employee->id,
        ]));

        $response->assertOk();
        $entry = $response->json('0.entries.0');
        $this->assertEquals('Login', $entry['type_label']);
        $this->assertEquals('primary', $entry['type_color']);
        $this->assertStringContainsString('ki-outline', $entry['type_icon']);
    }

    public function test_timeline_includes_sale_data(): void
    {
        $sale = Sale::factory()->create([
            'user_id' => $this->user->user_id,
            'sales_by' => $this->employee->id,
            'store_id' => $this->store->id,
            'pos_id' => $this->terminal->id,
        ]);

        PosLog::factory()->sale()->create([
            'user_id' => $this->employee->id,
            'store_id' => $this->store->id,
            'pos_id' => $this->terminal->id,
            'so_id' => $sale->id,
            'reason' => 'Sale completed',
        ]);

        $response = $this->actingAs($this->user)->getJson('/admin/employees/timeline?'.http_build_query([
            'startDate' => now()->format('Y-m-d'),
            'endDate' => now()->format('Y-m-d'),
            'id' => $this->employee->id,
        ]));

        $response->assertOk();
        $entry = $response->json('0.entries.0');
        $this->assertEquals($sale->id, $entry['sale_id']);
        $this->assertEquals($sale->son, $entry['sale_son']);
        $this->assertNotNull($entry['sale_total']);
    }

    public function test_timeline_includes_cash_in_amounts(): void
    {
        PosLog::factory()->cashIn(1500.50)->create([
            'user_id' => $this->employee->id,
            'store_id' => $this->store->id,
            'pos_id' => $this->terminal->id,
        ]);

        $response = $this->actingAs($this->user)->getJson('/admin/employees/timeline?'.http_build_query([
            'startDate' => now()->format('Y-m-d'),
            'endDate' => now()->format('Y-m-d'),
            'id' => $this->employee->id,
        ]));

        $response->assertOk();
        $entry = $response->json('0.entries.0');
        $this->assertEquals('1,500.50', $entry['cash_in']);
        $this->assertEquals('Cash-In', $entry['type_label']);
        $this->assertEquals('success', $entry['type_color']);
    }

    public function test_timeline_includes_cash_out_amounts(): void
    {
        PosLog::factory()->cashOut(750.25)->create([
            'user_id' => $this->employee->id,
            'store_id' => $this->store->id,
            'pos_id' => $this->terminal->id,
        ]);

        $response = $this->actingAs($this->user)->getJson('/admin/employees/timeline?'.http_build_query([
            'startDate' => now()->format('Y-m-d'),
            'endDate' => now()->format('Y-m-d'),
            'id' => $this->employee->id,
        ]));

        $response->assertOk();
        $entry = $response->json('0.entries.0');
        $this->assertEquals('750.25', $entry['cash_out']);
        $this->assertEquals('Cash-Out', $entry['type_label']);
    }

    public function test_timeline_filters_by_date_range(): void
    {
        PosLog::factory()->login()->create([
            'user_id' => $this->employee->id,
            'store_id' => $this->store->id,
            'pos_id' => $this->terminal->id,
            'created_at' => now(),
        ]);

        PosLog::factory()->login()->create([
            'user_id' => $this->employee->id,
            'store_id' => $this->store->id,
            'pos_id' => $this->terminal->id,
            'created_at' => now()->subWeek(),
        ]);

        $response = $this->actingAs($this->user)->getJson('/admin/employees/timeline?'.http_build_query([
            'startDate' => now()->format('Y-m-d'),
            'endDate' => now()->format('Y-m-d'),
            'id' => $this->employee->id,
        ]));

        $response->assertOk();
        $this->assertCount(1, $response->json());
        $this->assertCount(1, $response->json('0.entries'));
    }

    public function test_timeline_returns_empty_for_no_logs(): void
    {
        $response = $this->actingAs($this->user)->getJson('/admin/employees/timeline?'.http_build_query([
            'startDate' => now()->format('Y-m-d'),
            'endDate' => now()->format('Y-m-d'),
            'id' => $this->employee->id,
        ]));

        $response->assertOk();
        $response->assertJson([]);
    }

    public function test_timeline_groups_by_date(): void
    {
        PosLog::factory()->login()->create([
            'user_id' => $this->employee->id,
            'store_id' => $this->store->id,
            'pos_id' => $this->terminal->id,
            'created_at' => now(),
        ]);

        PosLog::factory()->login()->create([
            'user_id' => $this->employee->id,
            'store_id' => $this->store->id,
            'pos_id' => $this->terminal->id,
            'created_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($this->user)->getJson('/admin/employees/timeline?'.http_build_query([
            'startDate' => now()->subDay()->format('Y-m-d'),
            'endDate' => now()->format('Y-m-d'),
            'id' => $this->employee->id,
        ]));

        $response->assertOk();
        $this->assertCount(2, $response->json());
    }

    public function test_timeline_includes_store_and_terminal_names(): void
    {
        PosLog::factory()->login()->create([
            'user_id' => $this->employee->id,
            'store_id' => $this->store->id,
            'pos_id' => $this->terminal->id,
        ]);

        $response = $this->actingAs($this->user)->getJson('/admin/employees/timeline?'.http_build_query([
            'startDate' => now()->format('Y-m-d'),
            'endDate' => now()->format('Y-m-d'),
            'id' => $this->employee->id,
        ]));

        $response->assertOk();
        $entry = $response->json('0.entries.0');
        $this->assertEquals($this->store->name, $entry['store_name']);
        $this->assertEquals($this->terminal->name, $entry['pos_name']);
    }

    public function test_show_page_loads_without_timeline_data(): void
    {
        $response = $this->actingAs($this->user)->get('/admin/employees/'.$this->employee->id);

        $response->assertOk();
        $response->assertSee($this->employee->name);
        $response->assertSee('Activity Log');
        $response->assertSee('Attendance');
    }
}
