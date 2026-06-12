<?php

namespace Tests\Feature\Console;

use App\Models\CustomerRelations\Customer;
use App\Models\Employees\Role;
use App\Models\InventoryManagement\Purchase;
use App\Models\InventoryManagement\Supplier;
use App\Models\Products\Category;
use App\Models\Settings\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpenclawRepairOrphanTenancyTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected User $employee;

    protected Supplier $supplier;

    protected Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::factory()->admin()->create();

        $this->owner = User::factory()->create(['role_id' => $role->id]);
        $this->owner->forceFill(['user_id' => $this->owner->id])->save();

        $this->employee = User::factory()->create([
            'role_id' => $role->id,
            'user_id' => $this->owner->id,
        ]);

        $this->supplier = Supplier::factory()->create(['user_id' => $this->owner->id]);
        $this->store = Store::factory()->create(['user_id' => $this->owner->id]);
    }

    public function test_repairs_orphan_customers_categories_and_employee_id_purchases(): void
    {
        Customer::factory()->count(2)->create(['user_id' => 0]);
        Customer::factory()->create(['user_id' => $this->owner->id]);

        Category::factory()->create(['user_id' => 0]);
        Category::factory()->create(['user_id' => $this->owner->id]);

        Purchase::factory()->create([
            'user_id' => $this->employee->id,
            'supplier_id' => $this->supplier->id,
            'store_id' => $this->store->id,
        ]);
        Purchase::factory()->create([
            'user_id' => $this->owner->id,
            'supplier_id' => $this->supplier->id,
            'store_id' => $this->store->id,
        ]);

        $this->assertSame(2, Customer::query()->where('user_id', 0)->count());
        $this->assertSame(1, Category::query()->where('user_id', 0)->count());
        $this->assertSame(1, Purchase::query()->where('user_id', $this->employee->id)->count());

        $this->artisan('openclaw:repair-orphan-tenancy')->assertExitCode(0);

        $this->assertSame(0, Customer::query()->where('user_id', 0)->count(), 'orphan customers should be repaired');
        $this->assertSame(3, Customer::query()->where('user_id', $this->owner->id)->count());

        $this->assertSame(0, Category::query()->where('user_id', 0)->count(), 'orphan categories should be repaired');
        $this->assertSame(2, Category::query()->where('user_id', $this->owner->id)->count());

        $this->assertSame(0, Purchase::query()->where('user_id', $this->employee->id)->count(), 'employee-id purchases should move to tenant');
        $this->assertSame(2, Purchase::query()->where('user_id', $this->owner->id)->count());
    }

    public function test_dry_run_reports_anomalies_but_does_not_write(): void
    {
        Customer::factory()->count(3)->create(['user_id' => 0]);
        Category::factory()->create(['user_id' => 0]);

        $this->artisan('openclaw:repair-orphan-tenancy', ['--dry-run' => true])->assertExitCode(0);

        $this->assertSame(3, Customer::query()->where('user_id', 0)->count(), 'dry run should not modify rows');
        $this->assertSame(1, Category::query()->where('user_id', 0)->count(), 'dry run should not modify rows');
    }

    public function test_is_idempotent_when_schema_is_already_clean(): void
    {
        Customer::factory()->create(['user_id' => $this->owner->id]);

        $this->artisan('openclaw:repair-orphan-tenancy')
            ->expectsOutputToContain('Nothing to repair')
            ->assertExitCode(0);

        // Running it a second time still does nothing.
        $this->artisan('openclaw:repair-orphan-tenancy')
            ->expectsOutputToContain('Nothing to repair')
            ->assertExitCode(0);
    }

    public function test_explicit_target_user_id_overrides_auto_detect(): void
    {
        Customer::factory()->count(2)->create(['user_id' => 0]);

        $this->artisan('openclaw:repair-orphan-tenancy', [
            '--target-user-id' => $this->owner->id,
        ])->assertExitCode(0);

        $this->assertSame(0, Customer::query()->where('user_id', 0)->count());
    }

    public function test_fails_when_no_canonical_owner_can_be_detected(): void
    {
        // Wipe out the canonical owner's self-reference so auto-detect fails.
        // (The setUp owner has id == user_id; flip user_id away.)
        $this->owner->forceFill(['user_id' => 999])->save();

        $this->artisan('openclaw:repair-orphan-tenancy')
            ->expectsOutputToContain('Could not auto-detect target tenant')
            ->assertExitCode(1);
    }
}
