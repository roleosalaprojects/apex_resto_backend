<?php

namespace Tests\Feature\Bir;

use App\Models\Pos\Sale;
use App\Models\Pos\Zreading;
use Laravel\Passport\Passport;

class ZreadingAnnexFTest extends BirTestCase
{
    public function test_zreading_computes_gross_tender_breakdown_and_void_ranges(): void
    {
        Passport::actingAs($this->user);
        $item = $this->createItemWithStock(['price' => 100, 'cost' => 50]);

        $this->ringSale($item, 1, Sale::PAYMENT_CASH);  // 100
        $this->ringSale($item, 2, Sale::PAYMENT_CARD);  // 200
        $this->ringSale($item, 5, Sale::PAYMENT_CASH);  // 500 -> voided
        $voided = Sale::latest('id')->first();
        $this->postJson('/api/v1/sales/void/'.$voided->id)->assertStatus(200);

        $reading = $this->saveZReading(present: 300);

        $this->assertEquals(300, (float) $reading['gross_sales']); // voided 500 excluded
        $this->assertEquals(200, (float) $reading['card']);
        $this->assertEquals(500, (float) $reading['void_amount']);
        $this->assertEquals(1, (int) $reading['void_count']);
        $this->assertEquals(1, (int) $reading['first_void_no']);
        $this->assertEquals(1, (int) $reading['last_void_no']);
        $this->assertEquals(1, (int) $reading['z_counter']);
    }

    public function test_zreading_links_sales_and_rolls_forward_accumulated_sales(): void
    {
        Passport::actingAs($this->user);
        $item = $this->createItemWithStock(['price' => 100, 'cost' => 50]);

        $this->ringSale($item, 1, Sale::PAYMENT_CASH); // 100
        $first = $this->saveZReading(present: 100, previous: 0);
        $this->assertEquals(1, (int) $first['z_counter']);

        // All open sales were swept into the first reading.
        $this->assertEquals(0, Sale::whereNull('z_reading_id')->count());

        // New shift: another sale, second reading rolls accumulated forward.
        $this->ringSale($item, 2, Sale::PAYMENT_CASH); // 200
        $second = $this->saveZReading(present: 300, previous: 100);

        $this->assertEquals(2, (int) $second['z_counter']);
        $this->assertEquals(200, (float) $second['gross_sales']); // only the new sale
        $this->assertEquals(100, (float) $second['previous_accumulated_sales']);
        $this->assertEquals(300, (float) $second['present_accumulated_sales']);
        $this->assertEquals(2, Zreading::count());
    }

    private function ringSale($item, int $qty, int $paymentType): void
    {
        $this->postJson('/api/v1/sales', $this->buildSalePayload(
            [['item' => $item, 'qty' => $qty, 'price' => 100]],
            $paymentType,
        ))->assertStatus(200);
    }

    /**
     * @return array<string, mixed>
     */
    private function saveZReading(float $present, float $previous = 0): array
    {
        $response = $this->postJson('/api/v1/zreadings/save/'.$this->pos->id, [
            'pos_id' => $this->pos->id,
            'previous_accumulated_sales' => $previous,
            'present_accumulated_sales' => $present,
        ]);

        $response->assertStatus(200);

        return Zreading::latest('id')->first()->toArray();
    }
}
