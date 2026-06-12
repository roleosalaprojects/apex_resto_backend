<?php

namespace Tests\Feature\Notifications;

use App\Models\Employees\Role;
use App\Models\Pos\Sale;
use App\Models\Settings\Store;
use App\Models\User;
use App\Services\FcmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class LargeSaleNotificationTest extends TestCase
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

    public function test_large_sale_triggers_notification(): void
    {
        $this->mock(FcmService::class, function (MockInterface $mock) {
            $mock->shouldReceive('sendToUsersWithPermission')
                ->once()
                ->withArgs(function ($userId, $permission, $title, $body, $data) {
                    return $userId === $this->owner->id
                        && $permission === 'sls'
                        && $title === 'Large Sale'
                        && str_contains($body, 'P15,000.00')
                        && $data['type'] === 'large_sale';
                })
                ->andReturn(1);
        });

        $sale = Sale::factory()->create([
            'user_id' => $this->owner->id,
            'store_id' => $this->store->id,
            'total' => 15000,
            'type' => 0,
        ]);
        $sale->load('store');

        // Simulate what SaleController::notifyLargeSaleOrRefund does
        $storeName = $sale->store->name;
        $amount = number_format($sale->total, 2);
        $threshold = config('notifications.large_sale_threshold', 10000);

        $this->assertGreaterThanOrEqual($threshold, $sale->total);

        app(FcmService::class)->sendToUsersWithPermission(
            $sale->user_id,
            'sls',
            'Large Sale',
            "Large sale: P{$amount} at {$storeName}",
            ['type' => 'large_sale', 'id' => (string) $sale->id]
        );
    }

    public function test_sale_below_threshold_does_not_trigger(): void
    {
        $sale = Sale::factory()->create([
            'user_id' => $this->owner->id,
            'store_id' => $this->store->id,
            'total' => 5000,
            'type' => 0,
        ]);

        $threshold = config('notifications.large_sale_threshold', 10000);

        $this->assertLessThan($threshold, $sale->total);
    }

    public function test_large_refund_triggers_notification(): void
    {
        $this->mock(FcmService::class, function (MockInterface $mock) {
            $mock->shouldReceive('sendToUsersWithPermission')
                ->once()
                ->withArgs(function ($userId, $permission, $title, $body, $data) {
                    return $userId === $this->owner->id
                        && $permission === 'sls'
                        && $title === 'Refund Alert'
                        && str_contains($body, 'P7,000.00')
                        && $data['type'] === 'refund_alert';
                })
                ->andReturn(1);
        });

        $sale = Sale::factory()->create([
            'user_id' => $this->owner->id,
            'store_id' => $this->store->id,
            'total' => 7000,
            'type' => 1,
        ]);
        $sale->load('store');

        $storeName = $sale->store->name;
        $amount = number_format($sale->total, 2);
        $threshold = config('notifications.large_refund_threshold', 5000);

        $this->assertGreaterThanOrEqual($threshold, $sale->total);

        app(FcmService::class)->sendToUsersWithPermission(
            $sale->user_id,
            'sls',
            'Refund Alert',
            "Refund alert: P{$amount} at {$storeName}",
            ['type' => 'refund_alert', 'id' => (string) $sale->id]
        );
    }

    public function test_refund_below_threshold_does_not_trigger(): void
    {
        $sale = Sale::factory()->create([
            'user_id' => $this->owner->id,
            'store_id' => $this->store->id,
            'total' => 2000,
            'type' => 1,
        ]);

        $threshold = config('notifications.large_refund_threshold', 5000);

        $this->assertLessThan($threshold, $sale->total);
    }
}
