<?php

namespace Tests\Feature\Ecommerce;

use App\Models\CustomerRelations\Customer;
use App\Models\Ecommerce\Cart;
use App\Models\Ecommerce\CartItem;
use App\Models\Products\Category;
use App\Models\Products\Item;
use App\Models\Products\ItemStore;
use App\Models\Products\WholesalePriceTier;
use App\Models\Settings\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WholesaleCartTest extends TestCase
{
    use RefreshDatabase;

    protected Customer $customer;

    protected Item $item;

    protected Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = Store::factory()->create(['status' => true]);
        $this->item = Item::factory()->create([
            'status' => true,
            'price' => 100.00,
            'category_id' => Category::factory()->create()->id,
        ]);

        ItemStore::factory()->create([
            'item_id' => $this->item->id,
            'store_id' => $this->store->id,
            'stock' => 200,
            'status' => true,
        ]);

        // discount 20 => effective price 80
        WholesalePriceTier::factory()->create([
            'item_id' => $this->item->id,
            'min_qty' => 12,
            'discount' => 20.00,
        ]);

        // discount 40 => effective price 60
        WholesalePriceTier::factory()->create([
            'item_id' => $this->item->id,
            'min_qty' => 50,
            'discount' => 40.00,
        ]);

        $this->customer = Customer::factory()->create();
    }

    public function test_add_to_cart_starts_at_qty_one(): void
    {
        Livewire::actingAs($this->customer, 'customer')
            ->test(\App\Livewire\Ecommerce\AddToCartButton::class, ['itemId' => $this->item->id])
            ->call('addToCart')
            ->assertSet('inCart', true)
            ->assertSet('qty', 1);

        $this->assertDatabaseHas('cart_items', [
            'item_id' => $this->item->id,
            'qty' => 1,
            'price' => '100.00',
        ]);
    }

    public function test_decrement_below_one_removes_item(): void
    {
        $cart = Cart::create(['customer_id' => $this->customer->id]);
        CartItem::create([
            'cart_id' => $cart->id,
            'item_id' => $this->item->id,
            'qty' => 1,
            'price' => 100.00,
        ]);

        Livewire::actingAs($this->customer, 'customer')
            ->test(\App\Livewire\Ecommerce\AddToCartButton::class, ['itemId' => $this->item->id])
            ->call('decrement')
            ->assertSet('inCart', false)
            ->assertSet('qty', 0);

        $this->assertDatabaseMissing('cart_items', [
            'item_id' => $this->item->id,
        ]);
    }

    public function test_price_updates_when_qty_crosses_tier_threshold(): void
    {
        $cart = Cart::create(['customer_id' => $this->customer->id]);
        CartItem::create([
            'cart_id' => $cart->id,
            'item_id' => $this->item->id,
            'qty' => 49,
            'price' => 80.00,
        ]);

        Livewire::actingAs($this->customer, 'customer')
            ->test(\App\Livewire\Ecommerce\AddToCartButton::class, ['itemId' => $this->item->id])
            ->call('increment')
            ->assertSet('qty', 50);

        // discount 40 => effective price 60
        $this->assertDatabaseHas('cart_items', [
            'item_id' => $this->item->id,
            'qty' => 50,
            'price' => '60.00',
        ]);
    }

    public function test_tier_price_applies_at_threshold(): void
    {
        $cart = Cart::create(['customer_id' => $this->customer->id]);
        CartItem::create([
            'cart_id' => $cart->id,
            'item_id' => $this->item->id,
            'qty' => 11,
            'price' => 100.00,
        ]);

        Livewire::actingAs($this->customer, 'customer')
            ->test(\App\Livewire\Ecommerce\AddToCartButton::class, ['itemId' => $this->item->id])
            ->call('increment')
            ->assertSet('qty', 12);

        // discount 20 => effective price 80
        $this->assertDatabaseHas('cart_items', [
            'item_id' => $this->item->id,
            'qty' => 12,
            'price' => '80.00',
        ]);
    }

    public function test_place_order_succeeds_without_moq(): void
    {
        $cart = Cart::create(['customer_id' => $this->customer->id]);
        CartItem::create([
            'cart_id' => $cart->id,
            'item_id' => $this->item->id,
            'qty' => 2,
            'price' => 100.00,
        ]);

        Livewire::actingAs($this->customer, 'customer')
            ->test(\App\Livewire\Ecommerce\CartPage::class)
            ->call('placeOrder');

        $this->assertDatabaseHas('ecommerce_orders', [
            'customer_id' => $this->customer->id,
        ]);
    }

    public function test_cart_page_recalculates_price_on_qty_change(): void
    {
        $cart = Cart::create(['customer_id' => $this->customer->id]);
        $cartItem = CartItem::create([
            'cart_id' => $cart->id,
            'item_id' => $this->item->id,
            'qty' => 12,
            'price' => 80.00,
        ]);

        Livewire::actingAs($this->customer, 'customer')
            ->test(\App\Livewire\Ecommerce\CartPage::class)
            ->call('updateQty', $cartItem->id, 50);

        // discount 40 => effective price 60
        $this->assertDatabaseHas('cart_items', [
            'id' => $cartItem->id,
            'qty' => 50,
            'price' => '60.00',
        ]);
    }
}
