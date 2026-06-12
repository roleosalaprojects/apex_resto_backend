<?php

namespace Tests\Feature\Notifications;

use App\Models\Employees\Role;
use App\Models\Pos\Sale;
use App\Models\Pos\SaleLine;
use App\Models\Products\Item;
use App\Models\Settings\Store;
use App\Models\User;
use App\Services\FcmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class DailySalesSummaryTest extends TestCase
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

    public function test_sends_daily_summary_when_sales_exist(): void
    {
        $item = Item::factory()->create(['user_id' => $this->owner->id]);

        $sale = Sale::factory()->create([
            'user_id' => $this->owner->id,
            'store_id' => $this->store->id,
            'total' => 5000,
            'type' => 0,
            'cancelled' => 0,
            'created_at' => now(),
        ]);

        SaleLine::factory()->create([
            'sales_id' => $sale->id,
            'item_id' => $item->id,
            'qty' => 10,
            'sub_total' => 5000,
        ]);

        $this->mock(FcmService::class, function (MockInterface $mock) {
            $mock->shouldReceive('sendToUsersWithPermission')
                ->once()
                ->withArgs(function ($userId, $permission, $title, $body, $data) {
                    return $userId === $this->owner->id
                        && $permission === 'sls'
                        && $title === 'Daily Sales Summary'
                        && str_contains($body, 'P5,000.00')
                        && str_contains($body, '1 transaction(s)')
                        && $data['type'] === 'daily_sales_summary';
                })
                ->andReturn(1);
        });

        $this->artisan('notification:daily-sales-summary')
            ->assertSuccessful();
    }

    public function test_skips_when_no_sales_today(): void
    {
        Sale::factory()->create([
            'user_id' => $this->owner->id,
            'store_id' => $this->store->id,
            'total' => 5000,
            'type' => 0,
            'cancelled' => 0,
            'created_at' => now()->subDay(),
        ]);

        $this->mock(FcmService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('sendToUsersWithPermission');
        });

        $this->artisan('notification:daily-sales-summary')
            ->assertSuccessful();
    }

    public function test_excludes_cancelled_sales(): void
    {
        Sale::factory()->cancelled()->create([
            'user_id' => $this->owner->id,
            'store_id' => $this->store->id,
            'total' => 5000,
            'type' => 0,
            'created_at' => now(),
        ]);

        $this->mock(FcmService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('sendToUsersWithPermission');
        });

        $this->artisan('notification:daily-sales-summary')
            ->assertSuccessful();
    }

    public function test_excludes_refunds_from_summary(): void
    {
        Sale::factory()->create([
            'user_id' => $this->owner->id,
            'store_id' => $this->store->id,
            'total' => 3000,
            'type' => 1,
            'cancelled' => 0,
            'created_at' => now(),
        ]);

        $this->mock(FcmService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('sendToUsersWithPermission');
        });

        $this->artisan('notification:daily-sales-summary')
            ->assertSuccessful();
    }
}
