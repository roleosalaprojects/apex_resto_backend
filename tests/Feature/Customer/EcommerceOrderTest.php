<?php

namespace Tests\Feature\Customer;

use App\Models\CustomerRelations\Customer;
use App\Models\Ecommerce\Cart;
use App\Models\Ecommerce\CartItem;
use App\Models\Ecommerce\EcommerceOrder;
use App\Models\Products\Category;
use App\Models\Products\Item;
use App\Models\Products\ItemStore;
use App\Models\Settings\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EcommerceOrderTest extends TestCase
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
            'price' => 150.00,
            'category_id' => Category::factory()->create()->id,
        ]);

        ItemStore::factory()->create([
            'item_id' => $this->item->id,
            'store_id' => $this->store->id,
            'stock' => 50,
            'status' => true,
        ]);
    }

    public function test_payment_intent_persists_through_checkout(): void
    {
        $cart = \App\Models\Ecommerce\Cart::create(['customer_id' => $this->customer->id]);
        \App\Models\Ecommerce\CartItem::create([
            'cart_id' => $cart->id,
            'item_id' => $this->item->id,
            'qty' => 1,
            'price' => 150,
        ]);

        \Livewire\Livewire::actingAs($this->customer, 'customer')
            ->test(\App\Livewire\Ecommerce\CartPage::class)
            ->set('paymentIntent', \App\Models\Ecommerce\EcommerceOrder::PAYMENT_INTENT_CASH_ON_PICKUP)
            ->call('placeOrder');

        $this->assertDatabaseHas('ecommerce_orders', [
            'customer_id' => $this->customer->id,
            'payment_intent' => 'cash_on_pickup',
        ]);
    }

    public function test_customer_can_place_order_from_cart(): void
    {
        $cart = Cart::create(['customer_id' => $this->customer->id]);
        CartItem::create([
            'cart_id' => $cart->id,
            'item_id' => $this->item->id,
            'qty' => 2,
            'price' => 150.00,
        ]);

        Livewire::actingAs($this->customer, 'customer')
            ->test(\App\Livewire\Ecommerce\CartPage::class)
            ->call('placeOrder')
            ->assertRedirect(route('customer.orders'));

        $this->assertDatabaseHas('ecommerce_orders', [
            'customer_id' => $this->customer->id,
            'total' => 300.00,
            'qty' => 2,
            'status' => 0,
        ]);

        $this->assertDatabaseHas('ecommerce_order_lines', [
            'item_id' => $this->item->id,
            'item_name' => $this->item->name,
            'qty' => 2,
            'price' => 150.00,
            'sub_total' => 300.00,
        ]);

        // Cart should be cleared
        $this->assertDatabaseMissing('cart_items', [
            'cart_id' => $cart->id,
        ]);
    }

    public function test_cannot_place_order_with_empty_cart(): void
    {
        Cart::create(['customer_id' => $this->customer->id]);

        Livewire::actingAs($this->customer, 'customer')
            ->test(\App\Livewire\Ecommerce\CartPage::class)
            ->call('placeOrder')
            ->assertNoRedirect();

        $this->assertDatabaseCount('ecommerce_orders', 0);
    }

    public function test_cannot_place_order_when_item_out_of_stock(): void
    {
        // Set stock to 0
        ItemStore::where('item_id', $this->item->id)->update(['stock' => 0]);

        $cart = Cart::create(['customer_id' => $this->customer->id]);
        CartItem::create([
            'cart_id' => $cart->id,
            'item_id' => $this->item->id,
            'qty' => 1,
            'price' => 150.00,
        ]);

        Livewire::actingAs($this->customer, 'customer')
            ->test(\App\Livewire\Ecommerce\CartPage::class)
            ->call('placeOrder')
            ->assertNoRedirect();

        $this->assertDatabaseCount('ecommerce_orders', 0);
    }

    public function test_cannot_place_order_when_qty_exceeds_stock(): void
    {
        ItemStore::where('item_id', $this->item->id)->update(['stock' => 1]);

        $cart = Cart::create(['customer_id' => $this->customer->id]);
        CartItem::create([
            'cart_id' => $cart->id,
            'item_id' => $this->item->id,
            'qty' => 5,
            'price' => 150.00,
        ]);

        Livewire::actingAs($this->customer, 'customer')
            ->test(\App\Livewire\Ecommerce\CartPage::class)
            ->call('placeOrder')
            ->assertNoRedirect();

        $this->assertDatabaseCount('ecommerce_orders', 0);
    }

    public function test_customer_can_view_orders_page(): void
    {
        $response = $this->actingAs($this->customer, 'customer')
            ->get(route('customer.orders'));

        $response->assertStatus(200);
    }

    public function test_customer_can_see_their_orders(): void
    {
        $order = EcommerceOrder::factory()->create([
            'customer_id' => $this->customer->id,
        ]);

        Livewire::actingAs($this->customer, 'customer')
            ->test(\App\Livewire\Ecommerce\CustomerOrders::class)
            ->assertSee($order->reference);
    }

    public function test_customer_cannot_see_other_customers_orders(): void
    {
        $otherCustomer = Customer::factory()->create();
        $order = EcommerceOrder::factory()->create([
            'customer_id' => $otherCustomer->id,
        ]);

        Livewire::actingAs($this->customer, 'customer')
            ->test(\App\Livewire\Ecommerce\CustomerOrders::class)
            ->assertDontSee($order->reference);
    }

    public function test_order_with_note(): void
    {
        $cart = Cart::create(['customer_id' => $this->customer->id]);
        CartItem::create([
            'cart_id' => $cart->id,
            'item_id' => $this->item->id,
            'qty' => 1,
            'price' => 150.00,
        ]);

        Livewire::actingAs($this->customer, 'customer')
            ->test(\App\Livewire\Ecommerce\CartPage::class)
            ->set('note', 'Please deliver ASAP')
            ->call('placeOrder')
            ->assertRedirect(route('customer.orders'));

        $this->assertDatabaseHas('ecommerce_orders', [
            'customer_id' => $this->customer->id,
            'note' => 'Please deliver ASAP',
        ]);
    }

    public function test_orders_page_requires_authentication(): void
    {
        $response = $this->get(route('customer.orders'));

        $response->assertRedirect(route('customer.login'));
    }
}
