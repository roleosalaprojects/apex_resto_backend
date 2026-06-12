<?php

namespace Tests\Unit\Services;

use App\Models\CustomerRelations\Customer;
use App\Models\Products\Category;
use App\Models\Products\Item;
use App\Models\Products\WholesalePriceTier;
use App\Services\WholesalePricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WholesalePricingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected WholesalePricingService $service;

    protected Item $item;

    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new WholesalePricingService;

        $this->item = Item::factory()->create([
            'price' => 100.00,
            'category_id' => Category::factory()->create()->id,
        ]);

        $this->customer = Customer::factory()->create();
    }

    public function test_retail_price_returns_item_price(): void
    {
        $this->assertEquals(100.00, $this->service->getRetailPrice($this->item));
    }

    public function test_customer_gets_discounted_tier_price(): void
    {
        WholesalePriceTier::factory()->create([
            'item_id' => $this->item->id,
            'min_qty' => 1,
            'discount' => 10.00,
        ]);

        WholesalePriceTier::factory()->create([
            'item_id' => $this->item->id,
            'min_qty' => 12,
            'discount' => 20.00,
        ]);

        WholesalePriceTier::factory()->create([
            'item_id' => $this->item->id,
            'min_qty' => 50,
            'discount' => 30.00,
        ]);

        // item price is 100, so effective prices are 90, 80, 70
        $this->assertEquals(90.00, $this->service->getPrice($this->item, $this->customer, 1));
        $this->assertEquals(90.00, $this->service->getPrice($this->item, $this->customer, 11));
        $this->assertEquals(80.00, $this->service->getPrice($this->item, $this->customer, 12));
        $this->assertEquals(80.00, $this->service->getPrice($this->item, $this->customer, 49));
        $this->assertEquals(70.00, $this->service->getPrice($this->item, $this->customer, 50));
        $this->assertEquals(70.00, $this->service->getPrice($this->item, $this->customer, 100));
    }

    public function test_null_customer_gets_retail_price(): void
    {
        WholesalePriceTier::factory()->create([
            'item_id' => $this->item->id,
            'min_qty' => 12,
            'discount' => 20.00,
        ]);

        $price = $this->service->getPrice($this->item, null, 12);

        $this->assertEquals(100.00, $price);
    }

    public function test_customer_falls_back_to_retail_when_no_tiers(): void
    {
        $price = $this->service->getPrice($this->item, $this->customer, 5);

        $this->assertEquals(100.00, $price);
    }

    public function test_customer_gets_retail_when_below_all_tiers(): void
    {
        WholesalePriceTier::factory()->create([
            'item_id' => $this->item->id,
            'min_qty' => 12,
            'discount' => 20.00,
        ]);

        $price = $this->service->getPrice($this->item, $this->customer, 5);

        $this->assertEquals(100.00, $price);
    }

    public function test_discount_does_not_go_below_zero(): void
    {
        WholesalePriceTier::factory()->create([
            'item_id' => $this->item->id,
            'min_qty' => 1,
            'discount' => 150.00,
        ]);

        $price = $this->service->getPrice($this->item, $this->customer, 1);

        $this->assertEquals(0.00, $price);
    }

    public function test_get_applicable_tier_returns_correct_tier(): void
    {
        WholesalePriceTier::factory()->create([
            'item_id' => $this->item->id,
            'min_qty' => 1,
            'discount' => 10.00,
        ]);

        $tier12 = WholesalePriceTier::factory()->create([
            'item_id' => $this->item->id,
            'min_qty' => 12,
            'discount' => 20.00,
        ]);

        $tier = $this->service->getApplicableTier($this->item->id, 15);

        $this->assertNotNull($tier);
        $this->assertEquals($tier12->id, $tier->id);
    }

    public function test_get_applicable_tier_returns_null_when_below_all_tiers(): void
    {
        WholesalePriceTier::factory()->create([
            'item_id' => $this->item->id,
            'min_qty' => 12,
            'discount' => 20.00,
        ]);

        $tier = $this->service->getApplicableTier($this->item->id, 5);

        $this->assertNull($tier);
    }

    public function test_get_tiers_returns_all_tiers_ordered(): void
    {
        WholesalePriceTier::factory()->create([
            'item_id' => $this->item->id,
            'min_qty' => 50,
            'discount' => 30.00,
        ]);

        WholesalePriceTier::factory()->create([
            'item_id' => $this->item->id,
            'min_qty' => 1,
            'discount' => 10.00,
        ]);

        WholesalePriceTier::factory()->create([
            'item_id' => $this->item->id,
            'min_qty' => 12,
            'discount' => 20.00,
        ]);

        $tiers = $this->service->getTiers($this->item->id);

        $this->assertCount(3, $tiers);
        $this->assertEquals(1, $tiers[0]->min_qty);
        $this->assertEquals(12, $tiers[1]->min_qty);
        $this->assertEquals(50, $tiers[2]->min_qty);
    }

    public function test_moq_always_returns_one(): void
    {
        WholesalePriceTier::factory()->create([
            'item_id' => $this->item->id,
            'min_qty' => 12,
            'discount' => 20.00,
        ]);

        $this->assertEquals(1, $this->service->getMinOrderQty($this->item->id, $this->customer));
        $this->assertEquals(1, $this->service->getMinOrderQty($this->item->id, null));
    }
}
