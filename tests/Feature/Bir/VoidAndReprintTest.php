<?php

namespace Tests\Feature\Bir;

use App\Models\Pos\Sale;
use App\Models\Products\ItemStore;
use Laravel\Passport\Passport;

class VoidAndReprintTest extends BirTestCase
{
    public function test_void_cancels_sale_issues_void_number_and_restores_stock(): void
    {
        Passport::actingAs($this->user);
        $item = $this->createItemWithStock(['price' => 100, 'cost' => 50]);
        $startStock = (float) ItemStore::where('item_id', $item->id)->value('stock');

        $this->postJson('/api/v1/sales', $this->buildSalePayload([['item' => $item, 'qty' => 3, 'price' => 100]]))
            ->assertStatus(200);

        $sale = Sale::latest('id')->first();
        $this->assertEquals($startStock - 3, (float) ItemStore::where('item_id', $item->id)->value('stock'));

        $response = $this->postJson('/api/v1/sales/void/'.$sale->id);
        $response->assertStatus(200)->assertJsonPath('data.void_no', 1);

        $sale->refresh();
        $this->assertTrue((bool) $sale->cancelled);
        $this->assertEquals(1, $sale->void_no);
        // Stock restored.
        $this->assertEquals($startStock, (float) ItemStore::where('item_id', $item->id)->value('stock'));
    }

    public function test_void_is_rejected_for_already_voided_sale(): void
    {
        Passport::actingAs($this->user);
        $item = $this->createItemWithStock(['price' => 100, 'cost' => 50]);
        $this->postJson('/api/v1/sales', $this->buildSalePayload([['item' => $item, 'qty' => 1, 'price' => 100]]))
            ->assertStatus(200);
        $sale = Sale::latest('id')->first();

        $this->postJson('/api/v1/sales/void/'.$sale->id)->assertStatus(200);
        $this->postJson('/api/v1/sales/void/'.$sale->id)->assertStatus(422);
    }

    public function test_reprint_increments_count(): void
    {
        Passport::actingAs($this->user);
        $item = $this->createItemWithStock(['price' => 100, 'cost' => 50]);
        $this->postJson('/api/v1/sales', $this->buildSalePayload([['item' => $item, 'qty' => 1, 'price' => 100]]))
            ->assertStatus(200);
        $sale = Sale::latest('id')->first();

        $this->postJson('/api/v1/sales/reprint/'.$sale->id)
            ->assertStatus(200)
            ->assertJsonPath('data.reprint_count', 1);

        $this->postJson('/api/v1/sales/reprint/'.$sale->id)
            ->assertStatus(200)
            ->assertJsonPath('data.reprint_count', 2);
    }
}
