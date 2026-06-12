<?php

namespace Tests\Feature\Customer;

use App\Models\CustomerRelations\Customer;
use App\Models\Ecommerce\Cart;
use App\Models\Ecommerce\CartItem;
use App\Models\Products\Category;
use App\Models\Products\Item;
use App\Models\Products\ItemStore;
use App\Models\Settings\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    protected Customer $customer;

    protected Item $item;

    protected Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = Customer::factory()->create();
        $this->store = Store::factory()->create(['status' => true]);
        $this->item = Item::factory()->create([
            'status' => true,
            'price' => 100.00,
            'category_id' => Category::factory()->create()->id,
        ]);

        ItemStore::factory()->create([
            'item_id' => $this->item->id,
            'store_id' => $this->store->id,
            'stock' => 50,
            'status' => true,
        ]);
    }

    public function test_customer_can_add_item_to_cart(): void
    {
        Livewire::actingAs($this->customer, 'customer')
            ->test(\App\Livewire\Ecommerce\AddToCartButton::class, ['itemId' => $this->item->id])
            ->call('addToCart')
            ->assertSet('inCart', true)
            ->assertSet('qty', 1);

        $this->assertDatabaseHas('cart_items', [
            'item_id' => $this->item->id,
            'qty' => 1,
        ]);
    }

    public function test_customer_can_increment_cart_item(): void
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
            ->call('increment')
            ->assertSet('qty', 2);
    }

    public function test_customer_can_decrement_cart_item(): void
    {
        $cart = Cart::create(['customer_id' => $this->customer->id]);
        CartItem::create([
            'cart_id' => $cart->id,
            'item_id' => $this->item->id,
            'qty' => 3,
            'price' => 100.00,
        ]);

        Livewire::actingAs($this->customer, 'customer')
            ->test(\App\Livewire\Ecommerce\AddToCartButton::class, ['itemId' => $this->item->id])
            ->call('decrement')
            ->assertSet('qty', 2);
    }

    public function test_decrement_to_zero_removes_item(): void
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

    public function test_cannot_increment_beyond_stock(): void
    {
        $cart = Cart::create(['customer_id' => $this->customer->id]);
        CartItem::create([
            'cart_id' => $cart->id,
            'item_id' => $this->item->id,
            'qty' => 50,
            'price' => 100.00,
        ]);

        Livewire::actingAs($this->customer, 'customer')
            ->test(\App\Livewire\Ecommerce\AddToCartButton::class, ['itemId' => $this->item->id])
            ->call('increment')
            ->assertSet('qty', 50);
    }

    public function test_cart_page_displays_items(): void
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
            ->assertSee($this->item->name)
            ->assertSee('200.00');
    }

    public function test_customer_can_remove_item_from_cart(): void
    {
        $cart = Cart::create(['customer_id' => $this->customer->id]);
        $cartItem = CartItem::create([
            'cart_id' => $cart->id,
            'item_id' => $this->item->id,
            'qty' => 2,
            'price' => 100.00,
        ]);

        Livewire::actingAs($this->customer, 'customer')
            ->test(\App\Livewire\Ecommerce\CartPage::class)
            ->call('removeItem', $cartItem->id);

        $this->assertDatabaseMissing('cart_items', [
            'id' => $cartItem->id,
        ]);
    }

    public function test_cart_page_requires_authentication(): void
    {
        $response = $this->get(route('shops.cart'));

        $response->assertRedirect(route('customer.login'));
    }

    public function test_cart_icon_shows_count(): void
    {
        $cart = Cart::create(['customer_id' => $this->customer->id]);
        CartItem::create([
            'cart_id' => $cart->id,
            'item_id' => $this->item->id,
            'qty' => 3,
            'price' => 100.00,
        ]);

        Livewire::actingAs($this->customer, 'customer')
            ->test(\App\Livewire\Ecommerce\CartIcon::class)
            ->assertSet('count', 3);
    }
}
