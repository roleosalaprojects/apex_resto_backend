<?php

namespace Tests\Feature\API\v1\mobile;

use App\Models\CustomerRelations\Customer;
use App\Models\Employees\Role;
use App\Models\Pos\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class CustomerControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Role $role;

    protected function setUp(): void
    {
        parent::setUp();

        $this->role = Role::factory()->admin()->create();
        $this->user = User::factory()->create([
            'role_id' => $this->role->id,
            'user_id' => 1,
        ]);
    }

    public function test_can_show_customer_details_with_purchase_history(): void
    {
        Passport::actingAs($this->user);

        $customer = Customer::factory()->create([
            'user_id' => $this->user->user_id,
            'points' => 150.50,
            'accumulated_points' => 500.75,
        ]);

        Sale::factory()->count(3)->forCustomer($customer->id)->create([
            'user_id' => $this->user->user_id,
        ]);

        $response = $this->getJson("/api/v1/mobile/customers/{$customer->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'id',
                'name',
                'code',
                'phone',
                'email',
                'loyalty_points' => [
                    'current_balance',
                    'accumulated_total',
                ],
                'transaction_summary' => [
                    'total_transactions',
                    'total_spent',
                    'average_transaction',
                    'last_purchase_date',
                ],
                'purchase_history',
            ],
        ]);
        $response->assertJson([
            'success' => true,
            'data' => [
                'id' => $customer->id,
                'loyalty_points' => [
                    'current_balance' => 150.50,
                    'accumulated_total' => 500.75,
                ],
                'transaction_summary' => [
                    'total_transactions' => 3,
                ],
            ],
        ]);
    }

    public function test_show_customer_returns_correct_transaction_summary(): void
    {
        Passport::actingAs($this->user);

        $customer = Customer::factory()->create([
            'user_id' => $this->user->user_id,
        ]);

        Sale::factory()->forCustomer($customer->id)->create([
            'user_id' => $this->user->user_id,
            'total' => 100.00,
        ]);
        Sale::factory()->forCustomer($customer->id)->create([
            'user_id' => $this->user->user_id,
            'total' => 200.00,
        ]);

        $response = $this->getJson("/api/v1/mobile/customers/{$customer->id}");

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'transaction_summary' => [
                    'total_transactions' => 2,
                    'total_spent' => 300.00,
                    'average_transaction' => 150.00,
                ],
            ],
        ]);
    }

    public function test_show_customer_excludes_cancelled_sales_from_summary(): void
    {
        Passport::actingAs($this->user);

        $customer = Customer::factory()->create([
            'user_id' => $this->user->user_id,
        ]);

        Sale::factory()->forCustomer($customer->id)->create([
            'user_id' => $this->user->user_id,
            'total' => 100.00,
            'cancelled' => false,
        ]);
        Sale::factory()->forCustomer($customer->id)->cancelled()->create([
            'user_id' => $this->user->user_id,
            'total' => 500.00,
        ]);

        $response = $this->getJson("/api/v1/mobile/customers/{$customer->id}");

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'transaction_summary' => [
                    'total_transactions' => 1,
                    'total_spent' => 100.00,
                ],
            ],
        ]);
    }

    public function test_show_customer_with_no_purchases(): void
    {
        Passport::actingAs($this->user);

        $customer = Customer::factory()->create([
            'user_id' => $this->user->user_id,
            'points' => 0,
            'accumulated_points' => 0,
        ]);

        $response = $this->getJson("/api/v1/mobile/customers/{$customer->id}");

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'id' => $customer->id,
                'loyalty_points' => [
                    'current_balance' => 0,
                    'accumulated_total' => 0,
                ],
                'transaction_summary' => [
                    'total_transactions' => 0,
                    'total_spent' => 0,
                    'average_transaction' => 0,
                    'last_purchase_date' => null,
                ],
                'purchase_history' => [],
            ],
        ]);
    }

    public function test_show_customer_returns_404_for_nonexistent_customer(): void
    {
        Passport::actingAs($this->user);

        $response = $this->getJson('/api/v1/mobile/customers/99999');

        $response->assertStatus(404);
    }

    public function test_unauthenticated_user_cannot_view_customer_details(): void
    {
        $customer = Customer::factory()->create();

        $response = $this->getJson("/api/v1/mobile/customers/{$customer->id}");

        $response->assertStatus(401);
    }
}
