<?php

namespace Tests\Feature\Services;

use App\Models\Pos\Sale;
use App\Services\PeakHoursAnalysisService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PeakHoursAnalysisServiceTest extends TestCase
{
    use RefreshDatabase;

    private PeakHoursAnalysisService $service;

    private string $tz;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PeakHoursAnalysisService::class);
        $this->tz = config('app.timezone', 'Asia/Manila');
    }

    /**
     * Create a local-time timestamp for the given hour.
     * Timestamps are stored in the app timezone (Asia/Manila), no CONVERT_TZ is used.
     */
    private function localTimestamp(Carbon $base, int $hour): Carbon
    {
        return $base->copy()->setTimezone($this->tz)->setHour($hour)->setMinute(0)->setSecond(0);
    }

    public function test_get_heatmap_data_returns_correct_structure(): void
    {
        $base = Carbon::today($this->tz)->subDays(2);

        Sale::factory()->create([
            'user_id' => 1,
            'store_id' => 1,
            'total' => 100,
            'cancelled' => 0,
            'created_at' => $this->localTimestamp($base, 10),
        ]);

        Sale::factory()->create([
            'user_id' => 1,
            'store_id' => 1,
            'total' => 200,
            'cancelled' => 0,
            'created_at' => $this->localTimestamp($base, 14),
        ]);

        $result = $this->service->getHeatmapData(1, 30);

        $this->assertArrayHasKey('heatmap', $result);
        $this->assertArrayHasKey('peak_hours', $result);
        $this->assertArrayHasKey('slow_hours', $result);
        $this->assertArrayHasKey('busiest_day', $result);
        $this->assertNotEmpty($result['heatmap']);

        // Verify avg fields exist on heatmap entries
        $entry = $result['heatmap'][0];
        $this->assertArrayHasKey('avg_sales', $entry);
        $this->assertArrayHasKey('avg_transactions', $entry);
    }

    public function test_get_heatmap_data_excludes_cancelled_sales(): void
    {
        $base = Carbon::today($this->tz)->subDay();

        Sale::factory()->create([
            'user_id' => 1,
            'store_id' => 1,
            'total' => 100,
            'cancelled' => 0,
            'created_at' => $this->localTimestamp($base, 10),
        ]);

        Sale::factory()->cancelled()->create([
            'user_id' => 1,
            'store_id' => 1,
            'total' => 500,
            'created_at' => $this->localTimestamp($base, 10),
        ]);

        $result = $this->service->getHeatmapData(1, 30);

        $totalAvgSales = collect($result['heatmap'])->sum('avg_sales');
        $this->assertEquals(100, $totalAvgSales);
    }

    public function test_get_heatmap_data_filtersby_store(): void
    {
        $base = Carbon::today($this->tz)->subDay();

        Sale::factory()->create([
            'user_id' => 1,
            'store_id' => 1,
            'total' => 100,
            'cancelled' => 0,
            'created_at' => $this->localTimestamp($base, 10),
        ]);

        Sale::factory()->create([
            'user_id' => 1,
            'store_id' => 2,
            'total' => 200,
            'cancelled' => 0,
            'created_at' => $this->localTimestamp($base, 10),
        ]);

        $result = $this->service->getHeatmapData(1, 30, 1);

        $totalAvgSales = collect($result['heatmap'])->sum('avg_sales');
        $this->assertEquals(100, $totalAvgSales);
    }

    public function test_get_heatmap_data_returns_empty_for_no_sales(): void
    {
        $result = $this->service->getHeatmapData(999, 30);

        $this->assertEmpty($result['heatmap']);
        $this->assertEmpty($result['peak_hours']);
        $this->assertEmpty($result['slow_hours']);
        $this->assertEmpty($result['busiest_day']);
    }

    public function test_peak_hours_identifies_highest_avg_sales_hour(): void
    {
        $base = Carbon::today($this->tz)->subDay();

        // 1 sale of 50 at 10 AM → avg_sales = 50 (1 distinct date)
        Sale::factory()->create([
            'user_id' => 1,
            'total' => 50,
            'cancelled' => 0,
            'created_at' => $this->localTimestamp($base, 10),
        ]);

        // 1 sale of 500 at 2 PM → avg_sales = 500 (1 distinct date)
        Sale::factory()->create([
            'user_id' => 1,
            'total' => 500,
            'cancelled' => 0,
            'created_at' => $this->localTimestamp($base, 14),
        ]);

        $result = $this->service->getHeatmapData(1, 30);

        $this->assertNotEmpty($result['peak_hours']);
        $this->assertEquals(14, $result['peak_hours'][0]['hour']);
        $this->assertEquals(500, $result['peak_hours'][0]['avg_sales']);
    }

    public function test_averages_normalize_across_multiple_dates(): void
    {
        // Two Mondays at 10 AM with different totals
        $week1 = Carbon::today($this->tz)->subDays(14);
        $week2 = Carbon::today($this->tz)->subDays(7);

        // Ensure both land on the same day-of-week
        while ($week1->dayOfWeek !== $week2->dayOfWeek) {
            $week2 = $week2->subDay();
        }

        Sale::factory()->create([
            'user_id' => 1,
            'total' => 100,
            'cancelled' => 0,
            'created_at' => $this->localTimestamp($week1, 10),
        ]);

        Sale::factory()->create([
            'user_id' => 1,
            'total' => 300,
            'cancelled' => 0,
            'created_at' => $this->localTimestamp($week2, 10),
        ]);

        $result = $this->service->getHeatmapData(1, 30);

        // SUM(100 + 300) / 2 distinct dates = 200 avg sales
        $entry = collect($result['heatmap'])->firstWhere('hour', 10);
        $this->assertNotNull($entry);
        $this->assertEquals(200, $entry['avg_sales']);
        // 2 transactions / 2 dates = 1.0 avg
        $this->assertEquals(1.0, $entry['avg_transactions']);
    }

    public function test_get_hourly_breakdown_returns_correct_structure(): void
    {
        $base = Carbon::today($this->tz)->subDay();

        Sale::factory()->create([
            'user_id' => 1,
            'total' => 100,
            'cancelled' => 0,
            'created_at' => $this->localTimestamp($base, 9),
        ]);

        Sale::factory()->create([
            'user_id' => 1,
            'total' => 200,
            'cancelled' => 0,
            'created_at' => $this->localTimestamp($base, 9),
        ]);

        Sale::factory()->create([
            'user_id' => 1,
            'total' => 150,
            'cancelled' => 0,
            'created_at' => $this->localTimestamp($base, 14),
        ]);

        $result = $this->service->getHourlyBreakdown(1, $base->toDateString());

        $this->assertArrayHasKey('hours', $result);
        $this->assertCount(2, $result['hours']); // 2 distinct hours

        $hour9 = collect($result['hours'])->firstWhere('hour', 9);
        $this->assertEquals(300, $hour9['sales']);
        $this->assertEquals(2, $hour9['transactions']);
        $this->assertEquals(150, $hour9['avg_ticket']);
    }

    public function test_get_hourly_breakdown_filtersby_store(): void
    {
        $base = Carbon::today($this->tz)->subDay();

        Sale::factory()->create([
            'user_id' => 1,
            'store_id' => 1,
            'total' => 100,
            'cancelled' => 0,
            'created_at' => $this->localTimestamp($base, 10),
        ]);

        Sale::factory()->create([
            'user_id' => 1,
            'store_id' => 2,
            'total' => 200,
            'cancelled' => 0,
            'created_at' => $this->localTimestamp($base, 10),
        ]);

        $result = $this->service->getHourlyBreakdown(1, $base->toDateString(), 1);

        $hour10 = collect($result['hours'])->firstWhere('hour', 10);
        $this->assertEquals(100, $hour10['sales']);
        $this->assertEquals(1, $hour10['transactions']);
    }

    public function test_busiest_day_identifies_correct_day(): void
    {
        $monday = Carbon::today($this->tz)->startOfWeek(); // Monday

        if ($monday->diffInDays(Carbon::today($this->tz)) > 30) {
            $monday = $monday->addWeek();
        }

        Sale::factory()->count(3)->create([
            'user_id' => 1,
            'total' => 300,
            'cancelled' => 0,
            'created_at' => $this->localTimestamp($monday, 10),
        ]);

        $result = $this->service->getHeatmapData(1, 30);

        $this->assertNotEmpty($result['busiest_day']);
        $this->assertArrayHasKey('day_name', $result['busiest_day']);
        $this->assertArrayHasKey('avg_daily_sales', $result['busiest_day']);
        $this->assertArrayHasKey('avg_daily_transactions', $result['busiest_day']);
    }

    public function test_get_heatmap_data_respects_date_range(): void
    {
        Sale::factory()->create([
            'user_id' => 1,
            'total' => 100,
            'cancelled' => 0,
            'created_at' => $this->localTimestamp(Carbon::today($this->tz)->subDays(5), 10),
        ]);

        Sale::factory()->create([
            'user_id' => 1,
            'total' => 500,
            'cancelled' => 0,
            'created_at' => $this->localTimestamp(Carbon::today($this->tz)->subDays(40), 10),
        ]);

        $result = $this->service->getHeatmapData(1, 30);

        $totalAvgSales = collect($result['heatmap'])->sum('avg_sales');
        $this->assertEquals(100, $totalAvgSales);
    }
}
