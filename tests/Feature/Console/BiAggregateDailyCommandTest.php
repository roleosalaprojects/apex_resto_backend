<?php

namespace Tests\Feature\Console;

use App\Models\Bi\DailyStoreMetric;
use App\Models\Pos\Sale;
use App\Models\ScheduledJob;
use Carbon\Carbon;
use Database\Seeders\ScheduledJobSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BiAggregateDailyCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $tz;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tz = config('app.timezone', 'Asia/Manila');
    }

    private function day(int $daysAgo): Carbon
    {
        return Carbon::today($this->tz)->subDays($daysAgo);
    }

    private function makeSale(int $daysAgo, array $attributes = []): Sale
    {
        return Sale::factory()->create(array_merge([
            'user_id' => 1,
            'store_id' => 1,
            'type' => 0,
            'cancelled' => 0,
            'total' => 100,
            'profit' => 30,
            'created_at' => $this->day($daysAgo)->copy()->setTime(10, 0),
        ], $attributes));
    }

    public function test_default_run_covers_only_the_trailing_window(): void
    {
        $this->makeSale(1);
        $this->makeSale(10);

        $this->artisan('bi:aggregate-daily')->assertSuccessful();

        $this->assertSame(1, DailyStoreMetric::count());
        $this->assertSame(
            $this->day(1)->toDateString(),
            DailyStoreMetric::firstOrFail()->date->toDateString(),
        );
    }

    public function test_date_option_aggregates_a_single_day(): void
    {
        $this->makeSale(5);
        $this->makeSale(6);

        $this->artisan('bi:aggregate-daily --date='.$this->day(5)->toDateString())->assertSuccessful();

        $this->assertSame(1, DailyStoreMetric::count());
        $this->assertSame(
            $this->day(5)->toDateString(),
            DailyStoreMetric::firstOrFail()->date->toDateString(),
        );
    }

    public function test_from_to_options_aggregate_a_range(): void
    {
        $this->makeSale(7);
        $this->makeSale(6);
        $this->makeSale(2);

        $this->artisan(sprintf(
            'bi:aggregate-daily --from=%s --to=%s',
            $this->day(7)->toDateString(),
            $this->day(6)->toDateString(),
        ))->assertSuccessful();

        $this->assertSame(2, DailyStoreMetric::count());
        $this->assertSame(0, DailyStoreMetric::whereDate('date', $this->day(2)->toDateString())->count());
    }

    public function test_user_option_limits_to_one_tenant(): void
    {
        $this->makeSale(1, ['user_id' => 1]);
        $this->makeSale(1, ['user_id' => 2]);

        $this->artisan('bi:aggregate-daily --user=1')->assertSuccessful();

        $this->assertSame(1, DailyStoreMetric::count());
        $this->assertSame(1, DailyStoreMetric::firstOrFail()->user_id);
    }

    public function test_backfill_reaches_the_earliest_sale(): void
    {
        $this->makeSale(45);
        $this->makeSale(1);

        $this->artisan('bi:aggregate-daily --backfill')->assertSuccessful();

        $this->assertSame(2, DailyStoreMetric::count());
        $this->assertSame(
            $this->day(45)->toDateString(),
            DailyStoreMetric::orderBy('date')->firstOrFail()->date->toDateString(),
        );
    }

    public function test_from_without_to_fails(): void
    {
        $this->artisan('bi:aggregate-daily --from='.$this->day(3)->toDateString())
            ->expectsOutputToContain('--from and --to must be used together.')
            ->assertFailed();
    }

    public function test_from_after_to_fails(): void
    {
        $this->artisan(sprintf(
            'bi:aggregate-daily --from=%s --to=%s',
            $this->day(1)->toDateString(),
            $this->day(3)->toDateString(),
        ))->assertFailed();
    }

    public function test_future_dates_are_rejected(): void
    {
        $this->artisan('bi:aggregate-daily --date='.Carbon::today($this->tz)->addDay()->toDateString())
            ->expectsOutputToContain('Cannot aggregate future dates.')
            ->assertFailed();
    }

    public function test_invalid_date_format_fails(): void
    {
        $this->artisan('bi:aggregate-daily --date=10-06-2026')->assertFailed();
    }

    public function test_seeder_registers_the_job_as_enabled(): void
    {
        $this->seed(ScheduledJobSeeder::class);

        $this->assertDatabaseHas('scheduled_jobs', [
            'key' => ScheduledJob::KEY_BI_AGGREGATE_DAILY,
            'enabled' => true,
        ]);
        $this->assertTrue(ScheduledJob::isEnabled(ScheduledJob::KEY_BI_AGGREGATE_DAILY));
    }
}
