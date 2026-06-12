<?php

namespace Tests\Feature\API\v1;

use App\Models\CustomerRelations\Customer;
use App\Models\Ecommerce\EcommerceOrder;
use App\Models\Ecommerce\EcommerceOrderLine;
use App\Models\Employees\Role;
use App\Models\Products\Category;
use App\Models\Products\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class EcommerceOrderApiTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::factory()->admin()->create();
        $this->user = User::factory()->create([
            'role_id' => $role->id,
            'user_id' => 1,
        ]);
        $this->customer = Customer::factory()->create();
    }

    public function test_can_list_pending_and_verified_ecommerce_orders(): void
    {
        EcommerceOrder::factory()->count(2)->verified()->create([
            'customer_id' => $this->customer->id,
            'verified_by' => $this->user->id,
        ]);

        // Pending orders are surfaced to the POS too so cashiers see
        // incoming orders before admin verification.
        EcommerceOrder::factory()->create([
            'customer_id' => $this->customer->id,
            'status' => 0,
        ]);

        // Cancelled orders stay hidden.
        EcommerceOrder::factory()->create([
            'customer_id' => $this->customer->id,
            'status' => 2,
        ]);

        Passport::actingAs($this->user);

        $response = $this->getJson('/api/v1/ecommerce-orders');

        $response->assertStatus(200);
        // Response is paginated — 2 verified + 1 pending = 3, cancelled excluded.
        $response->assertJsonCount(3, 'data.data');
    }

    public function test_can_view_single_ecommerce_order(): void
    {
        $order = EcommerceOrder::factory()->verified()->create([
            'customer_id' => $this->customer->id,
            'verified_by' => $this->user->id,
        ]);

        $item = Item::factory()->create([
            'category_id' => Category::factory()->create()->id,
        ]);

        EcommerceOrderLine::factory()->create([
            'ecommerce_order_id' => $order->id,
            'item_id' => $item->id,
            'item_name' => $item->name,
        ]);

        Passport::actingAs($this->user);

        $response = $this->getJson("/api/v1/ecommerce-orders/{$order->id}");

        $response->assertStatus(200);
        $response->assertJsonFragment(['reference' => $order->reference]);
    }

    public function test_unauthenticated_user_cannot_access_api(): void
    {
        $response = $this->getJson('/api/v1/ecommerce-orders');

        $response->assertStatus(401);
    }
}
