<?php

namespace Tests\Feature\Bir;

use App\Models\Pos\Sale;
use App\Services\BirReportService;
use Laravel\Passport\Passport;

class BirReportServiceTest extends BirTestCase
{
    private BirReportService $reports;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reports = app(BirReportService::class);
    }

    public function test_sales_summary_excludes_training_and_reports_voids_separately(): void
    {
        Passport::actingAs($this->user);
        $item = $this->createItemWithStock(['price' => 100, 'cost' => 50]);

        // Two official sales (₱100, ₱200) + one training sale (₱500).
        $this->postJson('/api/v1/sales', $this->buildSalePayload([['item' => $item, 'qty' => 1, 'price' => 100]]))->assertStatus(200);
        $this->postJson('/api/v1/sales', $this->buildSalePayload([['item' => $item, 'qty' => 2, 'price' => 100]]))->assertStatus(200);

        $this->pos->update(['training_mode' => true]);
        $this->postJson('/api/v1/sales', $this->buildSalePayload([['item' => $item, 'qty' => 5, 'price' => 100]]))->assertStatus(200);
        $this->pos->update(['training_mode' => false]);

        // Void the ₱200 sale.
        $voided = Sale::where('is_training', false)->where('total', 200)->first();
        $this->postJson('/api/v1/sales/void/'.$voided->id)->assertStatus(200);

        $today = now()->toDateString();
        $rows = $this->reports->getBirSalesSummary($this->user->user_id, $today, $today);

        $this->assertCount(1, $rows);
        // Gross excludes training (500) and voided (200): only the ₱100 sale.
        $this->assertEquals(100, $rows[0]['gross_sales']);
        $this->assertEquals(200, $rows[0]['voids']);
        $this->assertEquals(100, $rows[0]['net_sales']);
    }

    public function test_voided_transactions_report_lists_void_numbers(): void
    {
        Passport::actingAs($this->user);
        $item = $this->createItemWithStock(['price' => 100, 'cost' => 50]);
        $this->postJson('/api/v1/sales', $this->buildSalePayload([['item' => $item, 'qty' => 1, 'price' => 100]]))->assertStatus(200);
        $sale = Sale::latest('id')->first();
        $this->postJson('/api/v1/sales/void/'.$sale->id)->assertStatus(200);

        $today = now()->toDateString();
        $rows = $this->reports->getVoidedTransactions($this->user->user_id, $today, $today);

        $this->assertCount(1, $rows);
        $this->assertEquals(1, $rows[0]['void_no']);
        $this->assertEquals($sale->son, $rows[0]['si_no']);
    }

    public function test_vat_class_report_groups_by_day(): void
    {
        Passport::actingAs($this->user);
        $item = $this->createItemWithStock(['price' => 100, 'cost' => 50]);
        $this->postJson('/api/v1/sales', $this->buildSalePayload([['item' => $item, 'qty' => 1, 'price' => 100]]))->assertStatus(200);

        $today = now()->toDateString();
        $rows = $this->reports->getDailySalesByVatClass($this->user->user_id, $today, $today);

        $this->assertCount(1, $rows);
        $this->assertArrayHasKey('vat_exempt', $rows[0]);
    }

    public function test_csv_export_route_logs_audit_entry(): void
    {
        $today = now()->toDateString();

        $this->actingAs($this->user)
            ->get(route('reports.bir.annexf.export', 'voided').'?startDate='.$today.'&endDate='.$today)
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => 'bir_report_export',
            'event' => 'exported',
        ]);
    }

    public function test_sales_summary_view_renders(): void
    {
        $this->actingAs($this->user)
            ->get(route('reports.bir.annexf.sales-summary'))
            ->assertOk();
    }
}
