<?php

namespace Tests\Feature\Services;

use App\Mail\DailySalesReport;
use App\Mail\WeeklySalesReport;
use App\Models\Pos\Sale;
use App\Models\Pos\SaleLine;
use App\Models\Products\Item;
use App\Models\Reports\ReportRecipient;
use App\Services\ReportService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ReportServiceTest extends TestCase
{
    use RefreshDatabase;

    private ReportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ReportService::class);
    }

    private function createSaleWithLines(array $saleOverrides = [], array $lines = []): Sale
    {
        $sale = Sale::factory()->create(array_merge([
            'user_id' => 1,
            'store_id' => 1,
            'cancelled' => 0,
            'type' => 0,
        ], $saleOverrides));

        foreach ($lines as $lineOverrides) {
            SaleLine::factory()->create(array_merge([
                'sales_id' => $sale->id,
            ], $lineOverrides));
        }

        return $sale;
    }

    public function test_get_sales_summary_returns_correct_structure(): void
    {
        $this->createSaleWithLines(
            ['total' => 500, 'profit' => 100, 'created_at' => Carbon::today()],
            [['item_id' => 1, 'price' => 100, 'qty' => 5, 'sub_total' => 500]]
        );

        $result = $this->service->getSalesSummary(1, 'daily', Carbon::today()->toDateString());

        $this->assertArrayHasKey('sales', $result);
        $this->assertArrayHasKey('refunds', $result);
        $this->assertArrayHasKey('profit', $result);
        $this->assertArrayHasKey('transactions', $result);
        $this->assertArrayHasKey('top_items', $result);
        $this->assertArrayHasKey('comparison', $result);
        $this->assertArrayHasKey('previous_period', $result['comparison']);
        $this->assertArrayHasKey('change_pct', $result['comparison']);
    }

    public function test_daily_sales_summary_aggregates_correctly(): void
    {
        // Today: 2 sales
        $this->createSaleWithLines(
            ['total' => 300, 'profit' => 80, 'type' => 0, 'created_at' => Carbon::today()->setHour(10)]
        );
        $this->createSaleWithLines(
            ['total' => 200, 'profit' => 50, 'type' => 0, 'created_at' => Carbon::today()->setHour(14)]
        );

        $result = $this->service->getSalesSummary(1, 'daily', Carbon::today()->toDateString());

        $this->assertEquals(500, $result['sales']);
        $this->assertEquals(130, $result['profit']);
        $this->assertEquals(2, $result['transactions']);
    }

    public function test_sales_summary_excludes_refunds_from_sales(): void
    {
        // Regular sale
        $this->createSaleWithLines(
            ['total' => 500, 'profit' => 100, 'type' => 0, 'created_at' => Carbon::today()]
        );

        // Refund
        $this->createSaleWithLines(
            ['total' => 100, 'profit' => 20, 'type' => 1, 'created_at' => Carbon::today()]
        );

        $result = $this->service->getSalesSummary(1, 'daily', Carbon::today()->toDateString());

        $this->assertEquals(500, $result['sales']);
        $this->assertEquals(100, $result['refunds']);
    }

    public function test_sales_summary_comparison(): void
    {
        // Yesterday
        $this->createSaleWithLines(
            ['total' => 1000, 'profit' => 200, 'type' => 0, 'created_at' => Carbon::yesterday()->setHour(10)]
        );

        // Today
        $this->createSaleWithLines(
            ['total' => 1200, 'profit' => 250, 'type' => 0, 'created_at' => Carbon::today()->setHour(10)]
        );

        $result = $this->service->getSalesSummary(1, 'daily', Carbon::today()->toDateString());

        $this->assertEquals(1200, $result['sales']);
        $this->assertEquals(1000, $result['comparison']['previous_period']['sales']);
        $this->assertEquals(20.0, $result['comparison']['change_pct']); // 20% increase
    }

    public function test_weekly_sales_summary(): void
    {
        // This week
        $this->createSaleWithLines(
            ['total' => 500, 'profit' => 100, 'type' => 0, 'created_at' => Carbon::today()->startOfWeek()->addDay()]
        );

        $result = $this->service->getSalesSummary(1, 'weekly', Carbon::today()->toDateString());

        $this->assertArrayHasKey('sales', $result);
    }

    public function test_filters_by_store(): void
    {
        $this->createSaleWithLines(
            ['total' => 300, 'profit' => 80, 'store_id' => 1, 'created_at' => Carbon::today()]
        );
        $this->createSaleWithLines(
            ['total' => 700, 'profit' => 150, 'store_id' => 2, 'created_at' => Carbon::today()]
        );

        $result = $this->service->getSalesSummary(1, 'daily', Carbon::today()->toDateString(), 1);

        $this->assertEquals(300, $result['sales']);
        $this->assertEquals(1, $result['transactions']);
    }

    public function test_top_items_returned_correctly(): void
    {
        $item1 = Item::factory()->create(['user_id' => 1, 'name' => 'Item A']);
        $item2 = Item::factory()->create(['user_id' => 1, 'name' => 'Item B']);

        $this->createSaleWithLines(
            ['total' => 500, 'type' => 0, 'created_at' => Carbon::today()],
            [
                ['item_id' => $item1->id, 'qty' => 5, 'sub_total' => 300],
                ['item_id' => $item2->id, 'qty' => 2, 'sub_total' => 200],
            ]
        );

        $result = $this->service->getSalesSummary(1, 'daily', Carbon::today()->toDateString());

        $this->assertNotEmpty($result['top_items']);
        $this->assertEquals('Item A', $result['top_items'][0]['item_name']);
    }

    public function test_generate_report_data_returns_all_sections(): void
    {
        $this->createSaleWithLines(
            ['total' => 500, 'profit' => 100, 'created_at' => Carbon::today()]
        );

        $result = $this->service->generateReportData(1, 'daily');

        $this->assertArrayHasKey('summary', $result);
        $this->assertArrayHasKey('peak_hours_summary', $result);
        $this->assertArrayHasKey('margin_alerts', $result);
    }

    public function test_report_generate_command_daily(): void
    {
        Mail::fake();

        ReportRecipient::factory()->daily()->create([
            'user_id' => 1,
            'email' => 'test@example.com',
        ]);

        $this->artisan('report:generate --type=daily')
            ->expectsOutputToContain('daily reports sent to 1 recipients')
            ->assertExitCode(0);

        Mail::assertSent(DailySalesReport::class, function ($mail) {
            return $mail->hasTo('test@example.com');
        });
    }

    public function test_report_generate_command_weekly(): void
    {
        Mail::fake();

        ReportRecipient::factory()->weekly()->create([
            'user_id' => 1,
            'email' => 'weekly@example.com',
        ]);

        $this->artisan('report:generate --type=weekly')
            ->expectsOutputToContain('weekly reports sent to 1 recipients')
            ->assertExitCode(0);

        Mail::assertSent(WeeklySalesReport::class, function ($mail) {
            return $mail->hasTo('weekly@example.com');
        });
    }

    public function test_report_generate_skips_inactive_recipients(): void
    {
        Mail::fake();

        ReportRecipient::factory()->daily()->inactive()->create([
            'user_id' => 1,
            'email' => 'inactive@example.com',
        ]);

        $this->artisan('report:generate --type=daily')
            ->expectsOutputToContain('No active recipients')
            ->assertExitCode(0);

        Mail::assertNothingSent();
    }

    public function test_report_generate_with_specific_email(): void
    {
        Mail::fake();

        $this->artisan('report:generate --type=daily --user=1 --email=direct@example.com')
            ->expectsOutputToContain('Report sent to direct@example.com')
            ->assertExitCode(0);

        Mail::assertSent(DailySalesReport::class, function ($mail) {
            return $mail->hasTo('direct@example.com');
        });
    }

    public function test_get_sold_items_returns_correct_format(): void
    {
        $item1 = Item::factory()->create(['user_id' => 1, 'name' => 'Widget A']);
        $item2 = Item::factory()->create(['user_id' => 1, 'name' => 'Widget B']);

        $this->createSaleWithLines(
            ['total' => 800, 'type' => 0, 'created_at' => Carbon::today()->setHour(10)],
            [
                ['item_id' => $item1->id, 'qty' => 3, 'unit_qty' => 1, 'cost' => 50, 'price' => 100, 'sub_total' => 300],
                ['item_id' => $item2->id, 'qty' => 5, 'unit_qty' => 1, 'cost' => 60, 'price' => 100, 'sub_total' => 500],
            ]
        );

        $result = $this->service->getSoldItems(
            1,
            Carbon::today()->startOfDay(),
            Carbon::today()->endOfDay()
        );

        $this->assertCount(2, $result);
        $this->assertEquals('Widget B', $result[0]['item']);
        $this->assertEquals(5, $result[0]['items_sold']);
        $this->assertEquals(500, $result[0]['net_sales']);
        $this->assertEquals(200, $result[0]['revenue']); // 500 - (5 * 60 * 1)
        $this->assertArrayHasKey('item', $result[0]);
        $this->assertArrayHasKey('items_sold', $result[0]);
        $this->assertArrayHasKey('net_sales', $result[0]);
        $this->assertArrayHasKey('revenue', $result[0]);
    }

    public function test_get_sold_items_handles_refunds(): void
    {
        $item = Item::factory()->create(['user_id' => 1, 'name' => 'Refund Item']);

        $this->createSaleWithLines(
            ['total' => 500, 'type' => 0, 'created_at' => Carbon::today()->setHour(10)],
            [['item_id' => $item->id, 'qty' => 5, 'unit_qty' => 1, 'cost' => 50, 'price' => 100, 'sub_total' => 500]]
        );

        $this->createSaleWithLines(
            ['total' => 100, 'type' => 1, 'created_at' => Carbon::today()->setHour(12)],
            [['item_id' => $item->id, 'qty' => 1, 'unit_qty' => 1, 'cost' => 50, 'price' => 100, 'sub_total' => 100]]
        );

        $result = $this->service->getSoldItems(
            1,
            Carbon::today()->startOfDay(),
            Carbon::today()->endOfDay()
        );

        $this->assertCount(1, $result);
        $this->assertEquals(4, $result[0]['items_sold']); // 5 - 1
        $this->assertEquals(400, $result[0]['net_sales']); // 500 - 100
    }

    public function test_generate_sold_items_csv(): void
    {
        $soldItems = [
            ['item' => 'Widget A', 'items_sold' => 10, 'net_sales' => 1000.50, 'revenue' => 300.25],
            ['item' => 'Widget B', 'items_sold' => 5, 'net_sales' => 500.00, 'revenue' => 150.00],
        ];

        $csv = $this->service->generateSoldItemsCsv($soldItems);

        $this->assertStringContainsString('Item', $csv);
        $this->assertStringContainsString('Items Sold', $csv);
        $this->assertStringContainsString('Net Sales', $csv);
        $this->assertStringContainsString('Revenue', $csv);
        $this->assertStringContainsString('Widget A', $csv);
        $this->assertStringContainsString('1000.50', $csv);
        $this->assertStringContainsString('Widget B', $csv);
        $this->assertStringContainsString('500.00', $csv);
    }

    public function test_generate_report_data_includes_sold_items(): void
    {
        $item = Item::factory()->create(['user_id' => 1, 'name' => 'Test Item']);

        $this->createSaleWithLines(
            ['total' => 500, 'profit' => 100, 'type' => 0, 'created_at' => Carbon::today()],
            [['item_id' => $item->id, 'qty' => 5, 'unit_qty' => 1, 'cost' => 50, 'price' => 100, 'sub_total' => 500]]
        );

        $result = $this->service->generateReportData(1, 'daily');

        $this->assertArrayHasKey('sold_items', $result);
        $this->assertNotEmpty($result['sold_items']);
        $this->assertEquals('Test Item', $result['sold_items'][0]['item']);
    }

    public function test_daily_report_email_has_csv_attachment(): void
    {
        $item = Item::factory()->create(['user_id' => 1, 'name' => 'Attached Item']);

        $this->createSaleWithLines(
            ['total' => 300, 'type' => 0, 'created_at' => Carbon::today()],
            [['item_id' => $item->id, 'qty' => 3, 'unit_qty' => 1, 'cost' => 50, 'price' => 100, 'sub_total' => 300]]
        );

        $reportData = $this->service->generateReportData(1, 'daily');
        $date = Carbon::yesterday()->format('M d, Y');

        $mailable = new DailySalesReport($reportData, $date);
        $attachments = $mailable->attachments();

        $this->assertCount(1, $attachments);
        $this->assertStringContainsString('sales-by-item-', $attachments[0]->as);
        $this->assertStringEndsWith('.csv', $attachments[0]->as);
    }

    public function test_daily_report_email_no_attachment_when_no_sales(): void
    {
        Mail::fake();

        ReportRecipient::factory()->daily()->create([
            'user_id' => 1,
            'email' => 'empty@example.com',
        ]);

        $this->artisan('report:generate --type=daily')
            ->assertExitCode(0);

        Mail::assertSent(DailySalesReport::class, function (DailySalesReport $mail) {
            return empty($mail->reportData['sold_items'])
                && $mail->hasTo('empty@example.com');
        });
    }

    public function test_empty_data_returns_zeros(): void
    {
        $result = $this->service->getSalesSummary(999, 'daily');

        $this->assertEquals(0, $result['sales']);
        $this->assertEquals(0, $result['refunds']);
        $this->assertEquals(0, $result['profit']);
        $this->assertEquals(0, $result['transactions']);
    }
}
