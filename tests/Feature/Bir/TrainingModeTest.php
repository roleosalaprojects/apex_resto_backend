<?php

namespace Tests\Feature\Bir;

use App\Models\Pos\Sale;
use Laravel\Passport\Passport;

class TrainingModeTest extends BirTestCase
{
    public function test_training_terminal_marks_sales_and_uses_training_series(): void
    {
        Passport::actingAs($this->user);
        $this->pos->update(['training_mode' => true]);
        $item = $this->createItemWithStock(['price' => 100, 'cost' => 50]);

        $response = $this->postJson('/api/v1/sales', $this->buildSalePayload([
            ['item' => $item, 'qty' => 1, 'price' => 100],
        ]));

        $response->assertStatus(200);
        $sale = Sale::latest('id')->first();
        $this->assertTrue($sale->is_training);
        $this->assertStringStartsWith('TR-', $sale->son);
        $this->assertEquals(1, $this->pos->fresh()->training_counter);
    }

    public function test_training_sales_excluded_from_xreading_totals(): void
    {
        Passport::actingAs($this->user);
        $item = $this->createItemWithStock(['price' => 100, 'cost' => 50]);

        // One official cash sale.
        $this->postJson('/api/v1/sales', $this->buildSalePayload([['item' => $item, 'qty' => 1, 'price' => 100]]))
            ->assertStatus(200);

        // Switch to training, ring a training sale.
        $this->pos->update(['training_mode' => true]);
        $this->postJson('/api/v1/sales', $this->buildSalePayload([['item' => $item, 'qty' => 5, 'price' => 100]]))
            ->assertStatus(200);

        $reading = $this->getJson('/api/v1/xreadings/apex/generate/'.$this->pos->id)
            ->assertStatus(200)
            ->json('data.reading.0');

        // Only the ₱100 official sale counts; the ₱500 training sale is excluded.
        $this->assertEquals(100, (float) $reading['cash']);
        $this->assertEquals(1, (int) $reading['transactions']);
    }

    public function test_training_sales_not_linked_to_zreading(): void
    {
        Passport::actingAs($this->user);
        $item = $this->createItemWithStock(['price' => 100, 'cost' => 50]);

        $this->pos->update(['training_mode' => true]);
        $this->postJson('/api/v1/sales', $this->buildSalePayload([['item' => $item, 'qty' => 1, 'price' => 100]]))
            ->assertStatus(200);

        $trainingSale = Sale::where('is_training', true)->first();
        $this->assertNull($trainingSale->z_reading_id);
    }
}
