<?php

namespace Tests\Feature\Bir;

use App\Models\Pos\Sale;
use Laravel\Passport\Passport;

class XreadingAnnexFTest extends BirTestCase
{
    public function test_reading_breaks_down_sales_by_tender_type(): void
    {
        Passport::actingAs($this->user);
        $item = $this->createItemWithStock(['price' => 100, 'cost' => 50]);

        $this->ringSale($item, 1, Sale::PAYMENT_CASH);          // 100 cash
        $this->ringSale($item, 2, Sale::PAYMENT_CARD);          // 200 card
        $this->ringSale($item, 3, Sale::PAYMENT_GIFT_CERT);     // 300 gift cert
        $this->ringSale($item, 4, Sale::PAYMENT_BANK_TRANSFER); // 400 bank transfer

        $reading = $this->getJson('/api/v1/xreadings/apex/generate/'.$this->pos->id)
            ->assertStatus(200)
            ->json('data.reading.0');

        $this->assertEquals(100, (float) $reading['cash']);
        $this->assertEquals(200, (float) $reading['card']);
        $this->assertEquals(300, (float) $reading['gift_cert']);
        $this->assertEquals(400, (float) $reading['bank_transfer']);
    }

    public function test_reading_reports_void_aggregates_and_excludes_them_from_totals(): void
    {
        Passport::actingAs($this->user);
        $item = $this->createItemWithStock(['price' => 100, 'cost' => 50]);

        $this->ringSale($item, 1, Sale::PAYMENT_CASH); // kept
        $this->ringSale($item, 5, Sale::PAYMENT_CASH); // will void
        $voided = Sale::latest('id')->first();
        $this->postJson('/api/v1/sales/void/'.$voided->id)->assertStatus(200);

        $reading = $this->getJson('/api/v1/xreadings/apex/generate/'.$this->pos->id)
            ->assertStatus(200)
            ->json('data.reading.0');

        $this->assertEquals(100, (float) $reading['cash']);
        $this->assertEquals(1, (int) $reading['transactions']);
        $this->assertEquals(500, (float) $reading['void_amount']);
        $this->assertEquals(1, (int) $reading['void_count']);
    }

    private function ringSale($item, int $qty, int $paymentType): void
    {
        $this->postJson('/api/v1/sales', $this->buildSalePayload(
            [['item' => $item, 'qty' => $qty, 'price' => 100]],
            $paymentType,
        ))->assertStatus(200);
    }
}
