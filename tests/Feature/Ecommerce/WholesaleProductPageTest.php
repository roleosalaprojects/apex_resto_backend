<?php

namespace Tests\Feature\Ecommerce;

use App\Models\CustomerRelations\Customer;
use App\Models\Products\Category;
use App\Models\Products\Item;
use App\Models\Products\ItemStore;
use App\Models\Products\WholesalePriceTier;
use App\Models\Settings\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WholesaleProductPageTest extends TestCase
{
    use RefreshDatabase;

    protected Item $item;

    protected Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = Store::factory()->create(['status' => true]);
        $this->item = Item::factory()->create([
            'name' => 'TEST PRODUCT',
            'status' => true,
            'price' => 100.00,
            'category_id' => Category::factory()->create()->id,
        ]);

        ItemStore::factory()->create([
            'item_id' => $this->item->id,
            'store_id' => $this->store->id,
            'stock' => 100,
            'status' => true,
        ]);

        // discount 20 => effective 80
        WholesalePriceTier::factory()->create([
            'item_id' => $this->item->id,
            'min_qty' => 12,
            'discount' => 20.00,
        ]);

        // discount 40 => effective 60
        WholesalePriceTier::factory()->create([
            'item_id' => $this->item->id,
            'min_qty' => 50,
            'discount' => 40.00,
        ]);
    }

    public function test_customer_sees_volume_pricing(): void
    {
        $customer = Customer::factory()->create();

        Livewire::actingAs($customer, 'customer')
            ->test(\App\Livewire\Ecommerce\ProductPage::class)
            ->assertSee('Volume Pricing')
            ->assertSee('From')
            ->assertSee('60.00');
    }

    public function test_guest_sees_volume_pricing(): void
    {
        Livewire::test(\App\Livewire\Ecommerce\ProductPage::class)
            ->assertSee('Volume Pricing')
            ->assertSee('From')
            ->assertSee('60.00');
    }

    public function test_product_without_tiers_shows_standard_price(): void
    {
        $itemNoTiers = Item::factory()->create([
            'name' => 'NO TIER PRODUCT',
            'status' => true,
            'price' => 150.00,
            'category_id' => Category::factory()->create()->id,
        ]);

        ItemStore::factory()->create([
            'item_id' => $itemNoTiers->id,
            'store_id' => $this->store->id,
            'stock' => 50,
            'status' => true,
        ]);

        Livewire::test(\App\Livewire\Ecommerce\ProductPage::class)
            ->assertSee('150.00');
    }
}
