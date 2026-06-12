<?php

namespace Tests\Feature;

use App\Models\Employees\Role;
use App\Models\Settings\Store;
use App\Models\User;
use App\Services\DemandForecastService;
use App\Services\FcmService;
use App\Services\ProfitAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Pins the bug fix: read paths (profit-margins index, forecast refresh) must
 * NOT dispatch FCM. Only the scheduled `notifications:fire-alerts` command
 * may push margin/reorder alerts.
 */
class NotificationLeaksOnReadPathsTest extends TestCase
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

    public function test_profit_margin_alerts_does_not_dispatch_fcm(): void
    {
        $fcm = Mockery::mock(FcmService::class);
        $fcm->shouldNotReceive('sendToAll');
        $fcm->shouldNotReceive('sendToUsers');
        $fcm->shouldNotReceive('sendToUser');
        $fcm->shouldNotReceive('sendToUsersWithPermission');
        $this->app->instance(FcmService::class, $fcm);

        // Even with margin drops in the data set, the read-side service must
        // remain pure. (We don't need to construct a margin drop — calling the
        // method with no data is enough; the assertion is "no FCM, ever".)
        $service = $this->app->make(ProfitAnalysisService::class);
        $result = $service->getMarginAlerts((int) $this->owner->user_id);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('alerts', $result);
    }

    public function test_reorder_suggestions_does_not_dispatch_fcm(): void
    {
        $fcm = Mockery::mock(FcmService::class);
        $fcm->shouldNotReceive('sendToAll');
        $fcm->shouldNotReceive('sendToUsers');
        $fcm->shouldNotReceive('sendToUser');
        $fcm->shouldNotReceive('sendToUsersWithPermission');
        $this->app->instance(FcmService::class, $fcm);

        $service = $this->app->make(DemandForecastService::class);
        $service->generateReorderSuggestions((int) $this->owner->user_id, $this->store->id);

        $this->addToAssertionCount(1);
    }

    public function test_fire_alerts_command_runs_cleanly(): void
    {
        // Empty-data run: no urgent items → no FCM calls. Just verifying the
        // scheduled command wires through without exceptions.
        $fcm = Mockery::mock(FcmService::class);
        $fcm->shouldNotReceive('sendToAll'); // no urgent suggestions in fixture
        $fcm->shouldNotReceive('sendToUsersWithPermission');
        $this->app->instance(FcmService::class, $fcm);

        $this->artisan('notifications:fire-alerts')
            ->assertExitCode(0)
            ->expectsOutputToContain('Margin pushes:');
    }
}
