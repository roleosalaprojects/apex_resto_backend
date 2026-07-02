<?php

namespace Tests\Feature\Customer;

use App\Models\CustomerRelations\Customer;
use App\Models\Employees\Role;
use App\Models\Settings\BrandingSetting;
use App\Models\User;
use App\Notifications\Customer\VerifyEmailNotification;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unverified_customer_is_redirected_to_notice_when_visiting_dashboard(): void
    {
        $customer = Customer::factory()->unverified()->create();

        $response = $this->actingAs($customer, 'customer')
            ->get(route('customer.dashboard'));

        $response->assertRedirect(route('customer.verification.notice'));
    }

    public function test_unverified_customer_is_redirected_to_notice_when_visiting_cart(): void
    {
        $customer = Customer::factory()->unverified()->create();

        $response = $this->actingAs($customer, 'customer')
            ->get('/shop/cart');

        $response->assertRedirect(route('customer.verification.notice'));
    }

    public function test_verified_customer_visiting_notice_is_sent_to_dashboard(): void
    {
        $customer = Customer::factory()->create();

        $response = $this->actingAs($customer, 'customer')
            ->get(route('customer.verification.notice'));

        $response->assertRedirect(route('customer.dashboard'));
    }

    public function test_unverified_customer_can_view_verification_notice(): void
    {
        $customer = Customer::factory()->unverified()->create();

        $response = $this->actingAs($customer, 'customer')
            ->get(route('customer.verification.notice'));

        $response->assertStatus(200);
        $response->assertViewIs('customer.auth.verify-email');
    }

    public function test_signed_url_marks_customer_as_verified(): void
    {
        Event::fake();

        $customer = Customer::factory()->unverified()->create();

        $url = URL::temporarySignedRoute(
            'customer.verification.verify',
            Carbon::now()->addMinutes(60),
            [
                'id' => $customer->getKey(),
                'hash' => sha1($customer->getEmailForVerification()),
            ]
        );

        $response = $this->actingAs($customer, 'customer')->get($url);

        $response->assertRedirect(route('customer.dashboard').'?verified=1');
        $this->assertNotNull($customer->fresh()->email_verified_at);
        Event::assertDispatched(Verified::class);
    }

    public function test_verify_route_rejects_mismatched_hash(): void
    {
        $customer = Customer::factory()->unverified()->create();

        $url = URL::temporarySignedRoute(
            'customer.verification.verify',
            Carbon::now()->addMinutes(60),
            [
                'id' => $customer->getKey(),
                'hash' => sha1('wrong@example.com'),
            ]
        );

        $response = $this->actingAs($customer, 'customer')->get($url);

        $response->assertForbidden();
        $this->assertNull($customer->fresh()->email_verified_at);
    }

    public function test_verify_route_rejects_unsigned_url(): void
    {
        $customer = Customer::factory()->unverified()->create();

        $response = $this->actingAs($customer, 'customer')->get(
            route('customer.verification.verify', [
                'id' => $customer->getKey(),
                'hash' => sha1($customer->getEmailForVerification()),
            ])
        );

        $response->assertStatus(403);
    }

    public function test_resend_sends_a_new_verification_email(): void
    {
        Notification::fake();

        $customer = Customer::factory()->unverified()->create();

        $response = $this->actingAs($customer, 'customer')
            ->from(route('customer.verification.notice'))
            ->post(route('customer.verification.send'));

        $response->assertRedirect(route('customer.verification.notice'));
        $response->assertSessionHas('status', 'verification-link-sent');

        Notification::assertSentTo($customer, VerifyEmailNotification::class);
    }

    public function test_resend_does_not_send_for_already_verified_customer(): void
    {
        Notification::fake();

        $customer = Customer::factory()->create();

        $response = $this->actingAs($customer, 'customer')
            ->post(route('customer.verification.send'));

        $response->assertRedirect(route('customer.dashboard'));
        Notification::assertNothingSent();
    }

    public function test_guest_cannot_access_verification_notice(): void
    {
        $response = $this->get(route('customer.verification.notice'));

        $response->assertRedirect(route('customer.login'));
    }

    public function test_verification_email_uses_the_storefront_branding(): void
    {
        // Storefront branding resolves from the first active self-owned
        // tenant's BrandingSetting (falling back to APEX). Seed one so the
        // brand demonstrably flows into the subject and body.
        $role = Role::factory()->admin()->create();
        $owner = User::factory()->create(['role_id' => $role->id, 'status' => true]);
        $owner->update(['user_id' => $owner->id]);
        BrandingSetting::factory()->create([
            'user_id' => $owner->id,
            'brand_name' => 'Quick Baskets',
        ]);
        Cache::forget('branding.storefront');

        $customer = Customer::factory()->unverified()->create();

        $notification = new VerifyEmailNotification;
        $mail = $notification->toMail($customer);

        $this->assertSame('Quick Baskets - Verify Your Email Address', $mail->subject);

        $rendered = $mail->render();
        $this->assertStringContainsString('Quick Baskets', $rendered);
    }
}
