<?php

namespace Tests\Feature\Customer;

use App\Models\CustomerRelations\Customer;
use App\Models\Ecommerce\EcommerceOrder;
use App\Models\Ecommerce\EcommerceOrderLine;
use App\Models\Products\Category;
use App\Models\Products\Item;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EcommerceOrderShowTest extends TestCase
{
    use RefreshDatabase;

    protected Customer $customer;

    protected EcommerceOrder $order;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = Customer::factory()->create();
        $this->order = EcommerceOrder::create([
            'reference' => 'ECO-TESTSHOW1',
            'customer_id' => $this->customer->id,
            'total' => 250,
            'qty' => 2,
            'status' => EcommerceOrder::STATUS_PAID,
            'is_wholesale' => false,
        ]);
        $item = Item::factory()->create([
            'category_id' => Category::factory()->create()->id,
        ]);
        EcommerceOrderLine::create([
            'ecommerce_order_id' => $this->order->id,
            'item_id' => $item->id,
            'item_name' => 'Test Item',
            'qty' => 2,
            'price' => 125,
            'sub_total' => 250,
        ]);
    }

    public function test_owner_can_view_their_order_detail(): void
    {
        $response = $this->actingAs($this->customer, 'customer')
            ->get(route('customer.orders.show', $this->order));

        $response->assertOk();
        $response->assertSee($this->order->reference);
        $response->assertSee('Paid');
    }

    public function test_other_customer_cannot_view_someone_elses_order(): void
    {
        $intruder = Customer::factory()->create();

        $this->actingAs($intruder, 'customer')
            ->get(route('customer.orders.show', $this->order))
            ->assertForbidden();
    }

    public function test_unauthenticated_request_is_redirected(): void
    {
        $this->get(route('customer.orders.show', $this->order))
            ->assertRedirect();
    }

    public function test_customer_order_url_uses_reference_not_id(): void
    {
        $url = route('customer.orders.show', $this->order);

        // Should embed the random reference, not the sequential id.
        $this->assertStringContainsString($this->order->reference, $url);
        $this->assertStringNotContainsString('/'.$this->order->id, $url);
    }

    public function test_admin_lookup_resolves_reference_to_id_and_redirects(): void
    {
        $role = \App\Models\Employees\Role::factory()->admin()->create();
        $admin = \App\Models\User::factory()->create([
            'role_id' => $role->id,
            'user_id' => 1,
        ]);

        $this->actingAs($admin)
            ->get(route('ecommerce-orders.lookup', $this->order->reference))
            ->assertRedirect(route('ecommerce-orders.show', $this->order->id));
    }
}
