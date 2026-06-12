<?php

namespace Tests\Feature\Notifications;

use App\Models\Employees\Role;
use App\Models\Pos\Sale;
use App\Models\Pos\SaleLine;
use App\Models\Products\Item;
use App\Models\Products\ItemStore;
use App\Models\Settings\Store;
use App\Models\User;
use App\Services\DemandForecastService;
use App\Services\FcmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class ReorderAlertNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::factory()->admin()->create();
        $this->owner = User::factory()->create([
            'role_id' => $role->id,
            'user_id' => 1,
        ]);

        $role->update(['user_id' => $this->owner->id]);
        $this->owner->update(['user_id' => $this->owner->id]);

        $this->store = Store::factory()->create([
            'user_id' => $this->owner->id,
        ]);
    }

    public function test_sends_notification_for_critical_reorder(): void
    {
        $item = Item::factory()->create(['user_id' => $this->owner->id]);

        ItemStore::create([
            'item_id' => $item->id,
            'store_id' => $this->store->id,
            'stock' => 1,
        ]);

        foreach (range(1, 10) as $day) {
            $sale = Sale::factory()->create([
                'user_id' => $this->owner->id,
                'store_id' => $this->store->id,
                'type' => 0,
                'cancelled' => 0,
                'created_at' => now()->subDays($day),
            ]);
            SaleLine::factory()->create([
                'sales_id' => $sale->id,
                'item_id' => $item->id,
                'qty' => 5,
                'sub_total' => 500,
            ]);
        }

        $this->mock(FcmService::class, function (MockInterface $mock) {
            $mock->shouldReceive('sendToUsersWithPermission')
                ->once()
                ->withArgs(function ($userId, $permission, $title, $body, $data) {
                    return $userId === $this->owner->id
                        && $permission === 'invntry'
                        && $title === 'Reorder Alert'
                        && str_contains($body, 'critical reorder suggestion')
                        && $data['type'] === 'reorder_alert';
                })
                ->andReturn(1);
        });

        $service = app(DemandForecastService::class);
        $suggestions = $service->generateReorderSuggestions($this->owner->id, $this->store->id);

        $this->assertTrue($suggestions->isNotEmpty());
    }

    public function test_no_notification_for_low_urgency_only(): void
    {
        $item = Item::factory()->create(['user_id' => $this->owner->id]);

        ItemStore::create([
            'item_id' => $item->id,
            'store_id' => $this->store->id,
            'stock' => 90,
        ]);

        foreach (range(1, 10) as $day) {
            $sale = Sale::factory()->create([
                'user_id' => $this->owner->id,
                'store_id' => $this->store->id,
                'type' => 0,
                'cancelled' => 0,
                'created_at' => now()->subDays($day),
            ]);
            SaleLine::factory()->create([
                'sales_id' => $sale->id,
                'item_id' => $item->id,
                'qty' => 5,
                'sub_total' => 500,
            ]);
        }

        $this->mock(FcmService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('sendToUsersWithPermission');
        });

        $service = app(DemandForecastService::class);
        $service->generateReorderSuggestions($this->owner->id, $this->store->id);
    }
}
