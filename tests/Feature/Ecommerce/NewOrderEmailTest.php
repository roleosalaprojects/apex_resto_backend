<?php

namespace Tests\Feature\Ecommerce;

use App\Livewire\Ecommerce\CartPage;
use App\Mail\NewEcommerceOrder;
use App\Models\CustomerRelations\Customer;
use App\Models\Ecommerce\Cart;
use App\Models\Ecommerce\CartItem;
use App\Models\Employees\Role;
use App\Models\Products\Item;
use App\Models\Products\ItemStore;
use App\Models\Settings\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Whenever a customer hits checkout on /shop, every staff member with
 * the `sls` role flag should get a notification email — alongside the
 * FCM push that fires from the same flow. These tests assert the
 * mailable lands on the right recipients and never blocks the
 * checkout response.
 */
class NewOrderEmailTest extends TestCase
{
    use RefreshDatabase;

    private function readyCustomerCart(): array
    {
        $store = Store::factory()->create();
        $item = Item::factory()->create();
        ItemStore::factory()->create([
            'item_id' => $item->id,
            'store_id' => $store->id,
            'stock' => 50,
        ]);

        $customer = Customer::factory()->create([
            'phone' => '09171234567',
            'phone_verified_at' => now(),
            'terms_accepted_at' => now(),
        ]);
        $cart = Cart::create(['customer_id' => $customer->id]);
        CartItem::create([
            'cart_id' => $cart->id,
            'item_id' => $item->id,
            'qty' => 2,
            'price' => 100,
        ]);

        return compact('customer', 'cart', 'item');
    }

    public function test_checkout_queues_new_order_email_to_every_sales_admin(): void
    {
        Mail::fake();

        // Two sales admins (should receive); one warehouse admin (should NOT).
        $salesRole = Role::factory()->create(['sls' => true]);
        $nonSalesRole = Role::factory()->create(['sls' => false]);

        $salesA = User::factory()->create(['role_id' => $salesRole->id, 'email' => 'a@example.com']);
        $salesB = User::factory()->create(['role_id' => $salesRole->id, 'email' => 'b@example.com']);
        User::factory()->create(['role_id' => $nonSalesRole->id, 'email' => 'warehouse@example.com']);

        $ctx = $this->readyCustomerCart();

        Livewire::actingAs($ctx['customer'], 'customer')
            ->test(CartPage::class)
            ->call('placeOrder');

        Mail::assertQueued(NewEcommerceOrder::class, function (NewEcommerceOrder $mail) use ($salesA) {
            return $mail->hasTo($salesA->email);
        });
        Mail::assertQueued(NewEcommerceOrder::class, function (NewEcommerceOrder $mail) use ($salesB) {
            return $mail->hasTo($salesB->email);
        });
        Mail::assertQueued(NewEcommerceOrder::class, 2,
            'Exactly the two sales admins should receive the email — warehouse role gets no mail.');
    }

    public function test_checkout_with_no_sales_admins_still_succeeds(): void
    {
        // Edge case: a fresh deployment with no admin users yet — the
        // checkout must still place the order. Email dispatch is
        // best-effort, not a blocker.
        Mail::fake();
        $ctx = $this->readyCustomerCart();

        Livewire::actingAs($ctx['customer'], 'customer')
            ->test(CartPage::class)
            ->call('placeOrder');

        Mail::assertNothingQueued();
        $this->assertSame(1, \App\Models\Ecommerce\EcommerceOrder::count(),
            'Order must still be created when no sales admins are around to notify.');
    }

    public function test_admin_without_email_is_skipped(): void
    {
        // Sales role but no email on file — common for accounts that
        // only auth via the POS terminal. Skip silently rather than
        // throwing in the Mail::to($null) call.
        Mail::fake();
        $salesRole = Role::factory()->create(['sls' => true]);
        $withEmail = User::factory()->create(['role_id' => $salesRole->id, 'email' => 'realmail@example.com']);
        // Raw insert to bypass factory unique() on email — legacy POS
        // admins may have empty email strings on file.
        \DB::table('users')->insert([
            'name' => 'POS Only',
            'email' => '',
            'role_id' => $salesRole->id,
            'password' => bcrypt('x'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $ctx = $this->readyCustomerCart();

        Livewire::actingAs($ctx['customer'], 'customer')
            ->test(CartPage::class)
            ->call('placeOrder');

        Mail::assertQueued(NewEcommerceOrder::class, 1);
        Mail::assertQueued(NewEcommerceOrder::class, fn ($m) => $m->hasTo($withEmail->email));
    }
}
