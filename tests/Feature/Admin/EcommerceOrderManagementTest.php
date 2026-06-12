<?php

namespace Tests\Feature\Admin;

use App\Models\CustomerRelations\Customer;
use App\Models\Ecommerce\EcommerceOrder;
use App\Models\Employees\Employee;
use App\Models\Employees\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EcommerceOrderManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Role $role;

    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->role = Role::factory()->admin()->create();
        $this->user = User::factory()->create([
            'role_id' => $this->role->id,
            'user_id' => 1,
        ]);
        Employee::create([
            'phone' => '09171234567',
            'address' => 'Test Address',
            'status' => true,
            'user_id' => $this->user->id,
        ]);
        $this->customer = Customer::factory()->create();
    }

    public function test_admin_can_view_ecommerce_orders_index(): void
    {
        $response = $this->actingAs($this->user)
            ->get(route('ecommerce-orders.index'));

        $response->assertOk();
    }

    public function test_admin_can_view_ecommerce_order_detail(): void
    {
        $order = EcommerceOrder::factory()->create([
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('ecommerce-orders.show', $order));

        $response->assertOk();
    }

    public function test_admin_can_verify_pending_order(): void
    {
        $order = EcommerceOrder::factory()->create([
            'customer_id' => $this->customer->id,
            'status' => 0,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('ecommerce-orders.verify', $order));

        $response->assertRedirect();

        $this->assertDatabaseHas('ecommerce_orders', [
            'id' => $order->id,
            'status' => 1,
            'verified_by' => $this->user->id,
        ]);
    }

    public function test_admin_can_cancel_pending_order(): void
    {
        $order = EcommerceOrder::factory()->create([
            'customer_id' => $this->customer->id,
            'status' => 0,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('ecommerce-orders.cancel', $order));

        $response->assertRedirect();

        $this->assertDatabaseHas('ecommerce_orders', [
            'id' => $order->id,
            'status' => 2,
            'cancelled_by' => $this->user->id,
        ]);
    }

    public function test_cannot_verify_already_verified_order(): void
    {
        $order = EcommerceOrder::factory()->verified()->create([
            'customer_id' => $this->customer->id,
            'verified_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('ecommerce-orders.verify', $order));

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_cannot_cancel_already_cancelled_order(): void
    {
        $order = EcommerceOrder::factory()->cancelled()->create([
            'customer_id' => $this->customer->id,
            'cancelled_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('ecommerce-orders.cancel', $order));

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_cannot_verify_cancelled_order(): void
    {
        $order = EcommerceOrder::factory()->cancelled()->create([
            'customer_id' => $this->customer->id,
            'cancelled_by' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('ecommerce-orders.verify', $order));

        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_ecommerce_orders_table_endpoint(): void
    {
        EcommerceOrder::factory()->count(3)->create([
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson(route('ecommerce-orders.table'));

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'data');
    }

    public function test_unauthenticated_user_cannot_access_orders(): void
    {
        $response = $this->get(route('ecommerce-orders.index'));

        $response->assertRedirect('/admin/login');
    }
}
