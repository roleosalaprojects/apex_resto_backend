<?php

namespace Tests\Feature\Admin;

use App\Models\Accounting\PosLog;
use App\Models\Employees\Employee;
use App\Models\Employees\Role;
use App\Models\Settings\Pos;
use App\Models\Settings\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosLogControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Role $adminRole;

    private Store $store;

    private Pos $terminal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::factory()->admin()->create();
        $this->admin = User::factory()->create([
            'role_id' => $this->adminRole->id,
            'user_id' => 1,
        ]);
        $this->admin->update(['user_id' => $this->admin->id]);

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

        $this->terminal = Pos::factory()->create([
            'store_id' => $this->store->id,
            'user_id' => $this->admin->id,
        ]);
    }

    public function test_can_view_pos_logs_index(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('pos-logs.index'));

        $response->assertStatus(200);
        $response->assertSee('POS Logs');
    }

    public function test_table_returns_json_data(): void
    {
        PosLog::factory()->count(3)->create([
            'pos_id' => $this->terminal->id,
            'store_id' => $this->store->id,
            'user_id' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson(route('pos-logs.table'));

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
        $response->assertJsonCount(3, 'data');
    }

    public function test_table_filters_by_type(): void
    {
        PosLog::factory()->cashOut()->count(2)->create([
            'pos_id' => $this->terminal->id,
            'store_id' => $this->store->id,
            'user_id' => $this->admin->id,
        ]);

        PosLog::factory()->cashIn()->create([
            'pos_id' => $this->terminal->id,
            'store_id' => $this->store->id,
            'user_id' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson(route('pos-logs.table', ['type' => 12]));

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
    }

    public function test_table_filters_by_store(): void
    {
        $otherStore = Store::factory()->create(['user_id' => $this->admin->id]);

        PosLog::factory()->count(2)->create([
            'pos_id' => $this->terminal->id,
            'store_id' => $this->store->id,
            'user_id' => $this->admin->id,
        ]);

        PosLog::factory()->create([
            'pos_id' => $this->terminal->id,
            'store_id' => $otherStore->id,
            'user_id' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson(route('pos-logs.table', ['store_id' => $this->store->id]));

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');
    }

    public function test_table_filters_by_date_range(): void
    {
        PosLog::factory()->create([
            'pos_id' => $this->terminal->id,
            'store_id' => $this->store->id,
            'user_id' => $this->admin->id,
            'created_at' => now()->subDays(10),
        ]);

        PosLog::factory()->create([
            'pos_id' => $this->terminal->id,
            'store_id' => $this->store->id,
            'user_id' => $this->admin->id,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson(route('pos-logs.table', [
                'date_from' => now()->subDays(2)->format('Y-m-d'),
                'date_to' => now()->format('Y-m-d'),
            ]));

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data');
    }

    public function test_export_returns_all_records_as_csv(): void
    {
        PosLog::factory()->cashOut()->count(30)->create([
            'pos_id' => $this->terminal->id,
            'store_id' => $this->store->id,
            'user_id' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('pos-logs.export'));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=utf-8');

        $lines = array_filter(explode("\n", trim($response->streamedContent())));
        $this->assertCount(31, $lines);
        $this->assertStringContainsString('Date,Type,Terminal,Store,Employee', $lines[0]);
    }

    public function test_export_applies_filters(): void
    {
        PosLog::factory()->cashOut()->count(2)->create([
            'pos_id' => $this->terminal->id,
            'store_id' => $this->store->id,
            'user_id' => $this->admin->id,
        ]);

        PosLog::factory()->cashIn()->create([
            'pos_id' => $this->terminal->id,
            'store_id' => $this->store->id,
            'user_id' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('pos-logs.export', ['type' => 12]));

        $response->assertStatus(200);

        $lines = array_filter(explode("\n", trim($response->streamedContent())));
        $this->assertCount(3, $lines);
    }

    public function test_unauthenticated_user_cannot_access_pos_logs_export(): void
    {
        $response = $this->get(route('pos-logs.export'));

        $response->assertRedirect('/admin/login');
    }

    public function test_unauthenticated_user_cannot_access_pos_logs(): void
    {
        $response = $this->get(route('pos-logs.index'));

        $response->assertRedirect('/admin/login');
    }

    public function test_unauthenticated_user_cannot_access_pos_logs_table(): void
    {
        $response = $this->getJson(route('pos-logs.table'));

        $response->assertStatus(401);
    }
}
