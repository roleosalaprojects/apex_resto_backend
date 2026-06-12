<?php

namespace Tests\Feature\Services;

use App\Models\Pos\Sale;
use App\Models\Pos\SaleLine;
use App\Models\Products\Item;
use App\Services\ProfitAnalysisService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfitAnalysisServiceTest extends TestCase
{
    use RefreshDatabase;

    private ProfitAnalysisService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ProfitAnalysisService::class);
    }

    private function createSaleWithLine(array $saleOverrides = [], array $lineOverrides = []): array
    {
        $sale = Sale::factory()->create(array_merge([
            'user_id' => 1,
            'store_id' => 1,
            'cancelled' => 0,
        ], $saleOverrides));

        $line = SaleLine::factory()->create(array_merge([
            'sales_id' => $sale->id,
        ], $lineOverrides));

        return [$sale, $line];
    }

    public function test_get_profit_margins_returns_correct_structure(): void
    {
        $item = Item::factory()->create(['user_id' => 1]);

        $this->createSaleWithLine(
            ['created_at' => Carbon::today()->subDays(5)],
            ['item_id' => $item->id, 'price' => 100, 'cost' => 70, 'qty' => 2, 'profit' => 60]
        );

        $result = $this->service->getProfitMargins(1, 30);

        $this->assertArrayHasKey('data', $result);
        $this->assertNotEmpty($result['data']);

        $first = $result['data'][0];
        $this->assertArrayHasKey('item_id', $first);
        $this->assertArrayHasKey('item_name', $first);
        $this->assertArrayHasKey('current_margin_pct', $first);
        $this->assertArrayHasKey('previous_margin_pct', $first);
        $this->assertArrayHasKey('margin_change', $first);
        $this->assertArrayHasKey('current_cost', $first);
        $this->assertArrayHasKey('current_price', $first);
        $this->assertArrayHasKey('total_sold', $first);
        $this->assertArrayHasKey('total_profit', $first);
    }

    public function test_margin_calculation_is_correct(): void
    {
        $item = Item::factory()->create(['user_id' => 1]);

        // Price=100, Cost=70 => Margin = (100-70)/100 = 30%
        $this->createSaleWithLine(
            ['created_at' => Carbon::today()->subDays(5)],
            ['item_id' => $item->id, 'price' => 100, 'cost' => 70, 'qty' => 1, 'profit' => 30]
        );

        $result = $this->service->getProfitMargins(1, 30);

        $this->assertEquals(30.0, $result['data'][0]['current_margin_pct']);
    }

    public function test_margin_change_calculation(): void
    {
        $item = Item::factory()->create(['user_id' => 1]);

        // Previous period: margin 30%
        $this->createSaleWithLine(
            ['created_at' => Carbon::today()->subDays(45)],
            ['item_id' => $item->id, 'price' => 100, 'cost' => 70, 'qty' => 1, 'profit' => 30]
        );

        // Current period: margin 20%
        $this->createSaleWithLine(
            ['created_at' => Carbon::today()->subDays(5)],
            ['item_id' => $item->id, 'price' => 100, 'cost' => 80, 'qty' => 1, 'profit' => 20]
        );

        $result = $this->service->getProfitMargins(1, 30);

        $itemData = collect($result['data'])->firstWhere('item_id', $item->id);
        $this->assertNotNull($itemData);
        $this->assertEquals(20.0, $itemData['current_margin_pct']);
        $this->assertEquals(30.0, $itemData['previous_margin_pct']);
        $this->assertEquals(-10.0, $itemData['margin_change']);
    }

    public function test_excludes_cancelled_sales(): void
    {
        $item = Item::factory()->create(['user_id' => 1]);

        $this->createSaleWithLine(
            ['created_at' => Carbon::today()->subDays(5), 'cancelled' => 0],
            ['item_id' => $item->id, 'price' => 100, 'cost' => 70, 'qty' => 1, 'profit' => 30]
        );

        $this->createSaleWithLine(
            ['created_at' => Carbon::today()->subDays(5), 'cancelled' => 1],
            ['item_id' => $item->id, 'price' => 100, 'cost' => 90, 'qty' => 5, 'profit' => 50]
        );

        $result = $this->service->getProfitMargins(1, 30);

        $itemData = collect($result['data'])->firstWhere('item_id', $item->id);
        $this->assertEquals(1, $itemData['total_sold']);
    }

    public function test_filters_by_store(): void
    {
        $item = Item::factory()->create(['user_id' => 1]);

        $this->createSaleWithLine(
            ['created_at' => Carbon::today()->subDays(5), 'store_id' => 1],
            ['item_id' => $item->id, 'price' => 100, 'cost' => 70, 'qty' => 1, 'profit' => 30]
        );

        $this->createSaleWithLine(
            ['created_at' => Carbon::today()->subDays(5), 'store_id' => 2],
            ['item_id' => $item->id, 'price' => 100, 'cost' => 60, 'qty' => 3, 'profit' => 120]
        );

        $result = $this->service->getProfitMargins(1, 30, 1);

        $itemData = collect($result['data'])->firstWhere('item_id', $item->id);
        $this->assertEquals(1, $itemData['total_sold']);
    }

    public function test_get_margin_trend_returns_data_points(): void
    {
        $item = Item::factory()->create(['user_id' => 1]);

        $this->createSaleWithLine(
            ['created_at' => Carbon::today()->subDays(10)],
            ['item_id' => $item->id, 'price' => 100, 'cost' => 70, 'qty' => 1, 'profit' => 30]
        );

        $this->createSaleWithLine(
            ['created_at' => Carbon::today()->subDays(5)],
            ['item_id' => $item->id, 'price' => 100, 'cost' => 80, 'qty' => 2, 'profit' => 40]
        );

        $result = $this->service->getMarginTrend(1, $item->id, 30);

        $this->assertArrayHasKey('item_name', $result);
        $this->assertArrayHasKey('data_points', $result);
        $this->assertCount(2, $result['data_points']);
        $this->assertEquals($item->name, $result['item_name']);

        $point = $result['data_points'][0];
        $this->assertArrayHasKey('date', $point);
        $this->assertArrayHasKey('margin_pct', $point);
        $this->assertArrayHasKey('cost', $point);
        $this->assertArrayHasKey('avg_price', $point);
        $this->assertArrayHasKey('qty_sold', $point);
    }

    public function test_get_margin_alerts_identifies_drops(): void
    {
        $item = Item::factory()->create(['user_id' => 1]);

        // Previous period: margin 40%
        $this->createSaleWithLine(
            ['created_at' => Carbon::today()->subDays(45)],
            ['item_id' => $item->id, 'price' => 100, 'cost' => 60, 'qty' => 1, 'profit' => 40]
        );

        // Current period: margin 10% (30% drop > 5% threshold)
        $this->createSaleWithLine(
            ['created_at' => Carbon::today()->subDays(5)],
            ['item_id' => $item->id, 'price' => 100, 'cost' => 90, 'qty' => 1, 'profit' => 10]
        );

        $result = $this->service->getMarginAlerts(1);

        $this->assertArrayHasKey('alerts', $result);
        $this->assertNotEmpty($result['alerts']);

        $alert = $result['alerts'][0];
        $this->assertEquals($item->id, $alert['item_id']);
        $this->assertGreaterThan(5, $alert['margin_drop_pct']);
    }

    public function test_get_margin_alerts_ignores_small_drops(): void
    {
        $item = Item::factory()->create(['user_id' => 1]);

        // Previous period: margin 30%
        $this->createSaleWithLine(
            ['created_at' => Carbon::today()->subDays(45)],
            ['item_id' => $item->id, 'price' => 100, 'cost' => 70, 'qty' => 1, 'profit' => 30]
        );

        // Current period: margin 28% (2% drop < 5% threshold)
        $this->createSaleWithLine(
            ['created_at' => Carbon::today()->subDays(5)],
            ['item_id' => $item->id, 'price' => 100, 'cost' => 72, 'qty' => 1, 'profit' => 28]
        );

        $result = $this->service->getMarginAlerts(1);

        $this->assertEmpty($result['alerts']);
    }

    public function test_empty_data_returns_empty_results(): void
    {
        $result = $this->service->getProfitMargins(999, 30);

        $this->assertEmpty($result['data']);
    }
}
