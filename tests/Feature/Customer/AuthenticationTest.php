<?php

namespace Tests\Feature\Customer;

use App\Models\CustomerRelations\Customer;
use App\Notifications\Customer\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_view_login_page(): void
    {
        $response = $this->get(route('customer.login'));

        $response->assertStatus(200);
        $response->assertViewIs('customer.auth.login');
    }

    public function test_customer_can_view_registration_page(): void
    {
        $response = $this->get(route('customer.register'));

        $response->assertStatus(200);
        $response->assertViewIs('customer.auth.register');
    }

    public function test_customer_can_login_with_valid_credentials(): void
    {
        $customer = Customer::factory()->create([
            'email' => 'customer@example.com',
            'password' => 'password',
        ]);

        $response = $this->post(route('customer.login.submit'), [
            'email' => 'customer@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('customer.dashboard'));
        $this->assertAuthenticatedAs($customer, 'customer');
    }

    public function test_customer_cannot_login_with_invalid_credentials(): void
    {
        Customer::factory()->create([
            'email' => 'customer@example.com',
            'password' => 'password',
        ]);

        $response = $this->post(route('customer.login.submit'), [
            'email' => 'customer@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest('customer');
    }

    public function test_customer_can_register(): void
    {
        Notification::fake();

        // Step 1 — request an OTP. Dev mode echoes the code back so the
        // test doesn't depend on the SMS relay.
        $sendResponse = $this->postJson(route('customer.register.send-otp'), [
            'phone' => '09171234567',
        ]);
        $code = $sendResponse->json('dev_code');

        // Step 2 — register with the matching code.
        $response = $this->post(route('customer.register.submit'), [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '09171234567',
            'address' => '123 Main St',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'terms' => '1',
            'otp' => $code,
        ]);

        // Phone OTP doubles as email proof — no verification email is
        // sent and the customer lands straight on the dashboard.
        $response->assertRedirect(route('customer.dashboard'));

        $customer = Customer::where('email', 'john@example.com')->firstOrFail();
        $this->assertNotNull($customer->terms_accepted_at);
        $this->assertNotNull($customer->phone_verified_at);
        $this->assertNotNull($customer->email_verified_at);
        $this->assertAuthenticated('customer');
        Notification::assertNotSentTo($customer, VerifyEmailNotification::class);
    }

    public function test_customer_cannot_register_without_accepting_terms(): void
    {
        $response = $this->post(route('customer.register.submit'), [
            'name' => 'John Doe',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            // terms intentionally omitted
        ]);

        $response->assertSessionHasErrors('terms');
        $this->assertDatabaseMissing('customers', ['email' => 'newuser@example.com']);
    }

    public function test_terms_page_renders(): void
    {
        $response = $this->get(route('shops.terms'));

        $response->assertOk();
        $response->assertSee('Terms and Conditions');
        $response->assertSee('Republic Act No. 10173');
    }

    public function test_unaccepted_customer_is_redirected_to_terms_on_authenticated_routes(): void
    {
        $customer = Customer::factory()->create([
            'email_verified_at' => now(),
            'terms_accepted_at' => null,
        ]);

        $response = $this->actingAs($customer, 'customer')
            ->get(route('customer.dashboard'));

        $response->assertRedirect(route('shops.terms'));
    }

    public function test_unaccepted_customer_can_view_terms_page_without_loop(): void
    {
        $customer = Customer::factory()->create([
            'email_verified_at' => now(),
            'terms_accepted_at' => null,
        ]);

        $response = $this->actingAs($customer, 'customer')
            ->get(route('shops.terms'));

        $response->assertOk();
        $response->assertSee('I have read and accept the Terms', false);
    }

    public function test_customer_can_accept_terms_and_stamps_timestamp(): void
    {
        $customer = Customer::factory()->create([
            'email_verified_at' => now(),
            'terms_accepted_at' => null,
        ]);

        $response = $this->actingAs($customer, 'customer')
            ->post(route('customer.terms.accept'));

        $response->assertRedirect();

        $customer->refresh();
        $this->assertNotNull($customer->terms_accepted_at);
    }

    public function test_accepted_customer_is_not_redirected(): void
    {
        $customer = Customer::factory()->create([
            'email_verified_at' => now(),
            'terms_accepted_at' => now(),
        ]);

        $response = $this->actingAs($customer, 'customer')
            ->get(route('customer.dashboard'));

        $response->assertOk();
    }

    public function test_customer_cannot_register_with_existing_email(): void
    {
        Customer::factory()->create([
            'email' => 'existing@example.com',
        ]);

        $response = $this->post(route('customer.register.submit'), [
            'name' => 'John Doe',
            'email' => 'existing@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'terms' => '1',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_authenticated_customer_can_logout(): void
    {
        $customer = Customer::factory()->create();

        $response = $this->actingAs($customer, 'customer')
            ->post(route('customer.logout'));

        $response->assertRedirect(route('customer.login'));
        $this->assertGuest('customer');
    }

    public function test_authenticated_customer_can_access_dashboard(): void
    {
        $customer = Customer::factory()->create();

        $response = $this->actingAs($customer, 'customer')
            ->get(route('customer.dashboard'));

        $response->assertStatus(200);
        $response->assertViewIs('customer.dashboard');
    }

    public function test_unauthenticated_customer_cannot_access_dashboard(): void
    {
        $response = $this->get(route('customer.dashboard'));

        $response->assertRedirect(route('customer.login'));
    }

    public function test_authenticated_customer_is_redirected_from_login_page(): void
    {
        $customer = Customer::factory()->create();

        $response = $this->actingAs($customer, 'customer')
            ->get(route('customer.login'));

        $response->assertRedirect(route('customer.dashboard'));
    }

    public function test_authenticated_customer_is_redirected_from_register_page(): void
    {
        $customer = Customer::factory()->create();

        $response = $this->actingAs($customer, 'customer')
            ->get(route('customer.register'));

        $response->assertRedirect(route('customer.dashboard'));
    }

    public function test_login_requires_email(): void
    {
        $response = $this->post(route('customer.login.submit'), [
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_login_requires_password(): void
    {
        $response = $this->post(route('customer.login.submit'), [
            'email' => 'customer@example.com',
        ]);

        $response->assertSessionHasErrors('password');
    }

    public function test_login_requires_valid_email_format(): void
    {
        $response = $this->post(route('customer.login.submit'), [
            'email' => 'not-an-email',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
    }

    public function test_login_shows_error_for_invalid_credentials(): void
    {
        Customer::factory()->create([
            'email' => 'customer@example.com',
            'password' => 'password',
        ]);

        $response = $this->from(route('customer.login'))
            ->post(route('customer.login.submit'), [
                'email' => 'customer@example.com',
                'password' => 'wrong-password',
            ]);

        $response->assertRedirect(route('customer.login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest('customer');
    }

    public function test_inactive_customer_cannot_login(): void
    {
        Customer::factory()->inactive()->create([
            'email' => 'inactive@example.com',
            'password' => 'password',
        ]);

        $response = $this->post(route('customer.login.submit'), [
            'email' => 'inactive@example.com',
            'password' => 'password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest('customer');
    }
}
