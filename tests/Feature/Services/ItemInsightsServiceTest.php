<?php

namespace Tests\Feature\Services;

use App\Models\Pos\Sale;
use App\Models\Pos\SaleLine;
use App\Models\Products\Item;
use App\Models\Products\ItemStore;
use App\Models\Settings\Store;
use App\Services\AiService;
use App\Services\ItemInsightsService;
use App\Services\WeatherService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ItemInsightsServiceTest extends TestCase
{
    use RefreshDatabase;

    private ItemInsightsService $service;

    private AiService $ai;

    private WeatherService $weather;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ai = Mockery::mock(AiService::class);
        $this->ai->shouldReceive('isAvailable')->andReturn(false)->byDefault();

        $this->weather = Mockery::mock(WeatherService::class);
        $this->weather->shouldReceive('isAvailable')->andReturn(false)->byDefault();

        $this->service = new ItemInsightsService($this->ai, $this->weather);
    }

    private function createSaleWithLines(int $userId, int $storeId, int $itemId, float $qty, float $price, float $cost, ?Carbon $date = null): void
    {
        $sale = Sale::factory()->create([
            'user_id' => $userId,
            'store_id' => $storeId,
            'cancelled' => 0,
            'type' => 0,
            'created_at' => $date ?? Carbon::now(),
        ]);

        SaleLine::factory()->create([
            'sales_id' => $sale->id,
            'item_id' => $itemId,
            'qty' => $qty,
            'price' => $price,
            'cost' => $cost,
            'sub_total' => $qty * $price,
            'profit' => ($price - $cost) * $qty,
        ]);
    }

    private function createItemWithSalesHistory(int $userId, int $storeId, array $overrides = [], int $salesDays = 14, float $dailyQty = 5.0, float $stock = 100): Item
    {
        $item = Item::factory()->create(array_merge([
            'user_id' => $userId,
        ], $overrides));

        ItemStore::factory()->create([
            'item_id' => $item->id,
            'store_id' => $storeId,
            'stock' => $stock,
        ]);

        for ($i = 1; $i <= $salesDays; $i++) {
            $this->createSaleWithLines(
                $userId,
                $storeId,
                $item->id,
                $dailyQty,
                $item->price,
                $item->cost,
                Carbon::today()->subDays($i)
            );
        }

        return $item;
    }

    public function test_generates_top_100_ranked_items(): void
    {
        $store = Store::factory()->create(['user_id' => 1]);

        // Create 120 items with sales history
        for ($i = 0; $i < 120; $i++) {
            $this->createItemWithSalesHistory(1, $store->id, [], 7, fake()->randomFloat(1, 1, 10));
        }

        $results = $this->service->generateTopInsights(1, Carbon::today(), $store->id);

        $this->assertCount(100, $results);
        $this->assertEquals(1, $results->first()->rank);
        $this->assertEquals(100, $results->last()->rank);

        // Verify descending score order
        $scores = $results->pluck('sellability_score')->toArray();
        for ($i = 0; $i < count($scores) - 1; $i++) {
            $this->assertGreaterThanOrEqual($scores[$i + 1], $scores[$i]);
        }
    }

    public function test_high_volume_item_ranks_above_low_volume(): void
    {
        $store = Store::factory()->create(['user_id' => 1]);

        $highVolume = $this->createItemWithSalesHistory(1, $store->id, [
            'cost' => 50, 'price' => 75,
        ], 30, 50.0);

        $lowVolume = $this->createItemWithSalesHistory(1, $store->id, [
            'cost' => 50, 'price' => 75,
        ], 30, 1.0);

        $results = $this->service->generateTopInsights(1, Carbon::today(), $store->id);

        $highVolumeInsight = $results->firstWhere('item_id', $highVolume->id);
        $lowVolumeInsight = $results->firstWhere('item_id', $lowVolume->id);

        $this->assertNotNull($highVolumeInsight);
        $this->assertNotNull($lowVolumeInsight);
        // Higher rank number = lower position, lower rank number = better
        $this->assertLessThan($lowVolumeInsight->rank, $highVolumeInsight->rank);
    }

    public function test_trending_item_scores_higher(): void
    {
        $store = Store::factory()->create(['user_id' => 1]);

        // Trending up: high recent sales, low prior sales
        $trendingItem = Item::factory()->create(['user_id' => 1, 'cost' => 50, 'price' => 75]);
        ItemStore::factory()->create(['item_id' => $trendingItem->id, 'store_id' => $store->id, 'stock' => 100]);

        // Prior 7d: 2 units/day
        for ($i = 8; $i <= 14; $i++) {
            $this->createSaleWithLines(1, $store->id, $trendingItem->id, 2, 75, 50, Carbon::today()->subDays($i));
        }
        // Recent 7d: 20 units/day
        for ($i = 1; $i <= 7; $i++) {
            $this->createSaleWithLines(1, $store->id, $trendingItem->id, 20, 75, 50, Carbon::today()->subDays($i));
        }

        // Flat: same volume throughout
        $flatItem = Item::factory()->create(['user_id' => 1, 'cost' => 50, 'price' => 75]);
        ItemStore::factory()->create(['item_id' => $flatItem->id, 'store_id' => $store->id, 'stock' => 100]);

        for ($i = 1; $i <= 14; $i++) {
            $this->createSaleWithLines(1, $store->id, $flatItem->id, 10, 75, 50, Carbon::today()->subDays($i));
        }

        $results = $this->service->generateTopInsights(1, Carbon::today(), $store->id);

        $trendingInsight = $results->firstWhere('item_id', $trendingItem->id);
        $flatInsight = $results->firstWhere('item_id', $flatItem->id);

        $this->assertNotNull($trendingInsight);
        $this->assertNotNull($flatInsight);
        $this->assertGreaterThan(
            $flatInsight->score_breakdown['trend'],
            $trendingInsight->score_breakdown['trend']
        );
    }

    public function test_high_margin_item_gets_margin_boost(): void
    {
        $store = Store::factory()->create(['user_id' => 1]);

        // High margin: cost=20, price=100 => 80% margin
        $highMargin = $this->createItemWithSalesHistory(1, $store->id, [
            'cost' => 20, 'price' => 100,
        ], 14, 5.0);

        // Low margin: cost=90, price=100 => 10% margin
        $lowMargin = $this->createItemWithSalesHistory(1, $store->id, [
            'cost' => 90, 'price' => 100,
        ], 14, 5.0);

        $results = $this->service->generateTopInsights(1, Carbon::today(), $store->id);

        $highMarginInsight = $results->firstWhere('item_id', $highMargin->id);
        $lowMarginInsight = $results->firstWhere('item_id', $lowMargin->id);

        $this->assertNotNull($highMarginInsight);
        $this->assertNotNull($lowMarginInsight);
        $this->assertGreaterThan(
            $lowMarginInsight->score_breakdown['margin'],
            $highMarginInsight->score_breakdown['margin']
        );
    }

    public function test_out_of_stock_item_penalized(): void
    {
        $store = Store::factory()->create(['user_id' => 1]);

        $inStock = $this->createItemWithSalesHistory(1, $store->id, [
            'cost' => 50, 'price' => 75,
        ], 14, 5.0, stock: 200);

        $outOfStock = $this->createItemWithSalesHistory(1, $store->id, [
            'cost' => 50, 'price' => 75,
        ], 14, 5.0, stock: 0);

        $results = $this->service->generateTopInsights(1, Carbon::today(), $store->id);

        $inStockInsight = $results->firstWhere('item_id', $inStock->id);
        $outOfStockInsight = $results->firstWhere('item_id', $outOfStock->id);

        $this->assertNotNull($inStockInsight);
        $this->assertNotNull($outOfStockInsight);
        $this->assertGreaterThan(
            $outOfStockInsight->score_breakdown['stock_readiness'],
            $inStockInsight->score_breakdown['stock_readiness']
        );
        $this->assertEquals(0, $outOfStockInsight->score_breakdown['stock_readiness']);
    }

    public function test_holiday_factor_applied(): void
    {
        $store = Store::factory()->create(['user_id' => 1]);
        $this->createItemWithSalesHistory(1, $store->id, [], 14, 5.0);

        // Christmas Eve should have holiday boost (factor 1.5)
        $christmasEve = Carbon::create(2026, 12, 24);
        $results = $this->service->generateTopInsights(1, $christmasEve, $store->id);

        $this->assertNotEmpty($results);
        $firstItem = $results->first();
        $this->assertGreaterThan(5, $firstItem->score_breakdown['seasonal']);
    }

    public function test_payday_factor_applied(): void
    {
        $store = Store::factory()->create(['user_id' => 1]);
        $item = $this->createItemWithSalesHistory(1, $store->id, [], 14, 5.0);

        // April 15, 2026 is a Wednesday (weekday payday, not a holiday)
        $payday = Carbon::create(2026, 4, 15);
        $results = $this->service->generateTopInsights(1, $payday, $store->id);

        $this->assertNotEmpty($results);
        $insight = $results->firstWhere('item_id', $item->id);
        $this->assertNotNull($insight);
        // Payday factor = 1.15 => seasonal score = (1.15 - 1.0) * 20 = 3.0
        $this->assertEquals(3.0, $insight->score_breakdown['seasonal']);
        $this->assertContains('payday_boost', $insight->factors);
    }

    public function test_weather_factor_applied(): void
    {
        $store = Store::factory()->withLocation()->create(['user_id' => 1]);
        $this->createItemWithSalesHistory(1, $store->id, [], 14, 5.0);

        $this->weather = Mockery::mock(WeatherService::class);
        $this->weather->shouldReceive('isAvailable')->andReturn(true);
        $this->weather->shouldReceive('getWeatherSalesFactor')->andReturn(0.5);
        $this->weather->shouldReceive('getWeatherInfo')->andReturn(['condition' => 'Heavy Rain']);

        $service = new ItemInsightsService($this->ai, $this->weather);
        $results = $service->generateTopInsights(1, Carbon::today(), $store->id);

        $this->assertNotEmpty($results);
        // Weather factor of 0.5 should result in weather score = 0.5 * 5 = 2.5
        $firstItem = $results->first();
        $this->assertEquals(2.5, $firstItem->score_breakdown['weather']);
    }

    public function test_batched_ai_call(): void
    {
        $store = Store::factory()->create(['user_id' => 1]);

        for ($i = 0; $i < 5; $i++) {
            $this->createItemWithSalesHistory(1, $store->id, [], 14, 5.0);
        }

        $this->ai = Mockery::mock(AiService::class);
        $this->ai->shouldReceive('isAvailable')->andReturn(true);
        $this->ai->shouldReceive('generateItemInsights')
            ->once()
            ->andReturn([
                0 => 'Great seller today.',
                1 => 'Weekend demand expected.',
                2 => 'Trending upward.',
                3 => 'Consistent performer.',
                4 => 'High margin opportunity.',
            ]);

        $service = new ItemInsightsService($this->ai, $this->weather);
        $results = $service->generateTopInsights(1, Carbon::today(), $store->id, 5);

        $this->assertCount(5, $results);
        $this->assertEquals('Great seller today.', $results->first()->ai_insight);
        $this->assertEquals('High margin opportunity.', $results->last()->ai_insight);
    }

    public function test_graceful_without_ollama(): void
    {
        $store = Store::factory()->create(['user_id' => 1]);
        $this->createItemWithSalesHistory(1, $store->id, [], 14, 5.0);

        // Ollama unavailable — default mock returns false for isAvailable
        $results = $this->service->generateTopInsights(1, Carbon::today(), $store->id);

        $this->assertNotEmpty($results);
        $this->assertNull($results->first()->ai_insight);
        $this->assertGreaterThan(0, $results->first()->sellability_score);
    }

    public function test_store_scoping(): void
    {
        $store1 = Store::factory()->create(['user_id' => 1]);
        $store2 = Store::factory()->create(['user_id' => 1]);

        $item1 = $this->createItemWithSalesHistory(1, $store1->id, [], 14, 10.0);
        $item2 = $this->createItemWithSalesHistory(1, $store2->id, [], 14, 10.0);

        // Request insights for store1 only
        $results = $this->service->generateTopInsights(1, Carbon::today(), $store1->id);

        $itemIds = $results->pluck('item_id')->toArray();
        $this->assertContains($item1->id, $itemIds);
        $this->assertNotContains($item2->id, $itemIds);
    }

    public function test_user_scoping(): void
    {
        $store = Store::factory()->create(['user_id' => 1]);

        $myItem = $this->createItemWithSalesHistory(1, $store->id, [], 14, 10.0);

        // Another user's item with sales
        $otherItem = Item::factory()->create(['user_id' => 999]);
        $otherStore = Store::factory()->create(['user_id' => 999]);
        ItemStore::factory()->create(['item_id' => $otherItem->id, 'store_id' => $otherStore->id, 'stock' => 100]);
        for ($i = 1; $i <= 14; $i++) {
            $this->createSaleWithLines(999, $otherStore->id, $otherItem->id, 10, 100, 50, Carbon::today()->subDays($i));
        }

        $results = $this->service->generateTopInsights(1, Carbon::today(), $store->id);

        $itemIds = $results->pluck('item_id')->toArray();
        $this->assertContains($myItem->id, $itemIds);
        $this->assertNotContains($otherItem->id, $itemIds);
    }

    public function test_items_with_no_sales_excluded(): void
    {
        $store = Store::factory()->create(['user_id' => 1]);

        $soldItem = $this->createItemWithSalesHistory(1, $store->id, [], 14, 5.0);

        // Item with no sales history
        $unsoldItem = Item::factory()->create(['user_id' => 1]);
        ItemStore::factory()->create(['item_id' => $unsoldItem->id, 'store_id' => $store->id, 'stock' => 100]);

        $results = $this->service->generateTopInsights(1, Carbon::today(), $store->id);

        $itemIds = $results->pluck('item_id')->toArray();
        $this->assertContains($soldItem->id, $itemIds);
        $this->assertNotContains($unsoldItem->id, $itemIds);
    }

    public function test_cached_results_returned(): void
    {
        $store = Store::factory()->create(['user_id' => 1]);
        $item = $this->createItemWithSalesHistory(1, $store->id, [], 14, 5.0);

        // First call generates
        $firstResults = $this->service->getTopInsights(1, Carbon::today(), $store->id);
        $this->assertNotEmpty($firstResults);

        // Second call should read from DB without regenerating
        $secondResults = $this->service->getTopInsights(1, Carbon::today(), $store->id);
        $this->assertNotEmpty($secondResults);
        $this->assertEquals(
            $firstResults->pluck('id')->toArray(),
            $secondResults->pluck('id')->toArray()
        );
    }

    public function test_refresh_regenerates(): void
    {
        $store = Store::factory()->create(['user_id' => 1]);
        $this->createItemWithSalesHistory(1, $store->id, [], 14, 5.0);

        // First generation
        $firstResults = $this->service->getTopInsights(1, Carbon::today(), $store->id);
        $firstIds = $firstResults->pluck('id')->toArray();

        // Refresh should create new records
        $refreshResults = $this->service->getTopInsights(1, Carbon::today(), $store->id, refresh: true);
        $refreshIds = $refreshResults->pluck('id')->toArray();

        $this->assertNotEquals($firstIds, $refreshIds);
    }
}
