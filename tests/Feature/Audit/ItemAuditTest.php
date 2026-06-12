<?php

namespace Tests\Feature\Audit;

use App\Models\Employees\Role;
use App\Models\Products\Item;
use App\Models\Reports\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemAuditTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::factory()->admin()->create();
        $this->owner = User::factory()->create(['role_id' => $role->id]);
        $this->owner->forceFill(['user_id' => $this->owner->id])->save();
    }

    public function test_creating_an_item_writes_an_audit_row(): void
    {
        AuditLog::query()->delete();

        $item = Item::factory()->create([
            'user_id' => $this->owner->user_id,
            'name' => 'Bag of Rice 25kg',
            'cost' => 1200,
            'price' => 1500,
        ]);

        $log = AuditLog::query()
            ->where('auditable_type', Item::class)
            ->where('auditable_id', $item->id)
            ->first();
        $this->assertNotNull($log);
        $this->assertSame('created', $log->event);
        $this->assertSame('Bag of Rice 25kg', $log->new_values['name'] ?? null);

        // Excluded high-churn pricing fields are not in the payload.
        $this->assertArrayNotHasKey('cost', $log->new_values);
        $this->assertArrayNotHasKey('price', $log->new_values);
        $this->assertArrayNotHasKey('prev_cost', $log->new_values);
        $this->assertArrayNotHasKey('prev_price', $log->new_values);
        $this->assertArrayNotHasKey('markup', $log->new_values);
    }

    public function test_updating_a_meaningful_field_writes_an_updated_row(): void
    {
        $item = Item::factory()->create(['user_id' => $this->owner->user_id]);
        AuditLog::query()->delete();

        $item->update(['low_stock_threshold' => 50]);

        $log = AuditLog::query()
            ->where('auditable_type', Item::class)
            ->where('auditable_id', $item->id)
            ->where('event', 'updated')
            ->first();
        $this->assertNotNull($log);
        $this->assertSame(50, (int) $log->new_values['low_stock_threshold']);
    }

    public function test_updates_that_only_touch_excluded_pricing_fields_are_not_logged(): void
    {
        $item = Item::factory()->create(['user_id' => $this->owner->user_id, 'cost' => 100, 'price' => 150]);
        AuditLog::query()->delete();

        // POS-receiving / bulk-pricing-style update — only pricing fields move.
        $item->update([
            'cost' => 110,
            'prev_cost' => 100,
            'price' => 165,
            'prev_price' => 150,
            'markup' => 50,
        ]);

        $this->assertSame(
            0,
            AuditLog::query()
                ->where('auditable_type', Item::class)
                ->where('auditable_id', $item->id)
                ->where('event', 'updated')
                ->count(),
            'Updates that only touch excluded pricing fields should not produce audit rows'
        );
    }

    public function test_updates_that_touch_both_excluded_and_audited_fields_log_only_the_audited_diff(): void
    {
        $item = Item::factory()->create(['user_id' => $this->owner->user_id, 'name' => 'Old', 'cost' => 100]);
        AuditLog::query()->delete();

        $item->update(['name' => 'New', 'cost' => 200]);

        $log = AuditLog::query()
            ->where('auditable_type', Item::class)
            ->where('auditable_id', $item->id)
            ->where('event', 'updated')
            ->first();
        $this->assertNotNull($log);
        $this->assertSame('New', $log->new_values['name']);
        $this->assertSame('Old', $log->old_values['name']);
        $this->assertArrayNotHasKey('cost', $log->new_values);
        $this->assertArrayNotHasKey('cost', $log->old_values);
    }
}
