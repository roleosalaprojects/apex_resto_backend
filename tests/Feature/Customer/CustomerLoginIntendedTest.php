<?php

namespace Tests\Feature\Customer;

use App\Models\CustomerRelations\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression test for the cross-guard intended-url bug.
 *
 * Laravel's `url.intended` session key is global across guards. Without
 * the safeIntended() filter, a customer logging in after an
 * unauthenticated admin-page visit would get redirected back to /admin,
 * ping-pong'd into /admin/login, and look like the login was broken.
 */
class CustomerLoginIntendedTest extends TestCase
{
    use RefreshDatabase;

    private function makeCustomer(): Customer
    {
        return Customer::factory()->create([
            'email' => 'taro@example.com',
            'password' => bcrypt('password123'),
            'status' => true,
        ]);
    }

    public function test_login_lands_on_dashboard_when_no_intended_url(): void
    {
        $customer = $this->makeCustomer();

        $this->post(route('customer.login.submit'), [
            'email' => $customer->email,
            'password' => 'password123',
        ])->assertRedirect(route('customer.dashboard'));
    }

    public function test_login_honours_customer_side_intended_url(): void
    {
        $customer = $this->makeCustomer();

        $this->withSession(['url.intended' => url('/customer/orders')])
            ->post(route('customer.login.submit'), [
                'email' => $customer->email,
                'password' => 'password123',
            ])
            ->assertRedirect(url('/customer/orders'));
    }

    public function test_login_honours_shop_intended_url(): void
    {
        $customer = $this->makeCustomer();

        $this->withSession(['url.intended' => url('/shop/cart')])
            ->post(route('customer.login.submit'), [
                'email' => $customer->email,
                'password' => 'password123',
            ])
            ->assertRedirect(url('/shop/cart'));
    }

    public function test_login_ignores_admin_intended_url_and_falls_back_to_dashboard(): void
    {
        $customer = $this->makeCustomer();

        // Simulate the scenario: customer was browsing /admin (or got
        // bounced there somehow) BEFORE logging in as customer.
        // url.intended is set to an admin URL.
        $this->withSession(['url.intended' => url('/admin/ecommerce-orders/1')])
            ->post(route('customer.login.submit'), [
                'email' => $customer->email,
                'password' => 'password123',
            ])
            ->assertRedirect(route('customer.dashboard'));
    }

    public function test_login_ignores_root_intended_url(): void
    {
        $customer = $this->makeCustomer();

        // A bare '/' isn't a customer path either — fall back to dashboard.
        $this->withSession(['url.intended' => url('/')])
            ->post(route('customer.login.submit'), [
                'email' => $customer->email,
                'password' => 'password123',
            ])
            ->assertRedirect(route('customer.dashboard'));
    }
}
