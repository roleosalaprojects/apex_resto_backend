<?php

namespace Tests\Feature\Admin;

use App\Models\Accounting\Expense;
use App\Models\Employees\Role;
use App\Models\Settings\Store;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardExpensesCardTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::factory()->admin()->create();
        $this->owner = User::factory()->create(['role_id' => $role->id]);
        $this->owner->forceFill(['user_id' => $this->owner->id])->save();
        $this->store = Store::factory()->create(['user_id' => $this->owner->user_id]);
    }

    private function makeExpense(float $amount, string $date, int $status = Expense::STATUS_ACTIVE, ?int $storeId = null): Expense
    {
        return Expense::create([
            'reference_number' => Expense::generateReferenceNumber(),
            'bank_id' => null,
            'store_id' => $storeId,
            'payee' => 'Test',
            'amount' => $amount,
            'expense_date' => $date,
            'status' => $status,
            'created_by' => $this->owner->id,
        ]);
    }

    public function test_dashboard_default_includes_total_expenses_in_window(): void
    {
        $today = Carbon::today(config('app.timezone'));
        $this->makeExpense(500, $today->toDateString());
        $this->makeExpense(1500, $today->toDateString());
        // Out of window — should be excluded.
        $this->makeExpense(9999, $today->copy()->subDays(60)->toDateString());

        $this->actingAs($this->owner);

        $response = $this->getJson(route('dashboard.default', [
            'startDate' => $today->copy()->subDays(7)->toDateString(),
            'endDate' => $today->toDateString(),
        ]));

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['expenses']]);

        $this->assertEqualsWithDelta(2000.0, $response->json('data.expenses'), 0.001);
    }

    public function test_dashboard_default_excludes_voided_expenses(): void
    {
        $today = Carbon::today(config('app.timezone'));
        $this->makeExpense(1000, $today->toDateString());
        $this->makeExpense(500, $today->toDateString(), Expense::STATUS_VOIDED);

        $this->actingAs($this->owner);

        $response = $this->getJson(route('dashboard.default', [
            'startDate' => $today->copy()->subDays(7)->toDateString(),
            'endDate' => $today->toDateString(),
        ]));

        $response->assertStatus(200);
        $this->assertEqualsWithDelta(1000.0, $response->json('data.expenses'), 0.001);
    }

    public function test_dashboard_default_respects_store_filter(): void
    {
        $today = Carbon::today(config('app.timezone'));
        $otherStore = Store::factory()->create(['user_id' => $this->owner->user_id]);

        $this->makeExpense(500, $today->toDateString(), Expense::STATUS_ACTIVE, $this->store->id);
        $this->makeExpense(1500, $today->toDateString(), Expense::STATUS_ACTIVE, $otherStore->id);

        $this->actingAs($this->owner);

        $response = $this->getJson(route('dashboard.default', [
            'startDate' => $today->copy()->subDays(7)->toDateString(),
            'endDate' => $today->toDateString(),
            'store_select' => $this->store->id,
        ]));

        $response->assertStatus(200);
        $this->assertEqualsWithDelta(500.0, $response->json('data.expenses'), 0.001);
    }

    public function test_dashboard_default_returns_zero_when_no_expenses(): void
    {
        $today = Carbon::today(config('app.timezone'));

        $this->actingAs($this->owner);

        $response = $this->getJson(route('dashboard.default', [
            'startDate' => $today->copy()->subDays(7)->toDateString(),
            'endDate' => $today->toDateString(),
        ]));

        $response->assertStatus(200);
        $this->assertSame(0.0, (float) $response->json('data.expenses'));
    }
}
