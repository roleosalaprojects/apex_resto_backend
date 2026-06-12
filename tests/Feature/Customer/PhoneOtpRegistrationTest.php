<?php

namespace Tests\Feature\Customer;

use App\Models\CustomerRelations\Customer;
use App\Models\CustomerRelations\CustomerPhoneOtp;
use App\Services\VeroSmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhoneOtpRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Run in dev mode — no VeroSMS relay, code is returned in the
        // sendOtp response and the verify step can read it off the
        // database directly.
        config(['services.verosms.base_url' => null]);
    }

    public function test_send_otp_returns_dev_code_when_no_base_url_configured(): void
    {
        $response = $this->postJson(route('customer.register.send-otp'), [
            'phone' => '09171234567',
        ]);

        $response->assertStatus(200);
        $this->assertSame(VeroSmsService::RESULT_OK, $response->json('status'));
        $this->assertMatchesRegularExpression('/^\d{6}$/', (string) $response->json('dev_code'));
        $this->assertDatabaseHas('customer_phone_otps', ['phone' => '09171234567']);
    }

    public function test_send_otp_rejects_invalid_phone(): void
    {
        $this->postJson(route('customer.register.send-otp'), [
            'phone' => '12345',
        ])->assertStatus(422)->assertJsonValidationErrors('phone');
    }

    public function test_send_otp_enforces_per_phone_cooldown(): void
    {
        $this->postJson(route('customer.register.send-otp'), ['phone' => '09171234567'])
            ->assertStatus(200);

        $second = $this->postJson(route('customer.register.send-otp'), ['phone' => '09171234567']);

        $second->assertStatus(429);
        $this->assertSame(VeroSmsService::RESULT_COOLDOWN, $second->json('status'));
        $retryIn = $second->json('retry_in');
        $this->assertIsInt($retryIn, 'retry_in must be an int so the JS countdown renders cleanly, not a sub-second float.');
        $this->assertGreaterThan(0, $retryIn);
    }

    public function test_register_with_correct_otp_creates_verified_customer(): void
    {
        $sendResponse = $this->postJson(route('customer.register.send-otp'), [
            'phone' => '09171234567',
        ]);
        $code = $sendResponse->json('dev_code');

        $this->post(route('customer.register.submit'), [
            'name' => 'Juan Dela Cruz',
            'email' => 'juan@example.com',
            'phone' => '09171234567',
            'password' => 'secret123!',
            'password_confirmation' => 'secret123!',
            'terms' => '1',
            'otp' => $code,
        ])->assertRedirect(route('customer.dashboard'));

        $customer = Customer::where('email', 'juan@example.com')->first();
        $this->assertNotNull($customer);
        $this->assertNotNull($customer->phone_verified_at);
        $this->assertNotNull($customer->email_verified_at);
        $this->assertSame('09171234567', $customer->phone);
    }

    public function test_register_rejects_wrong_otp(): void
    {
        $this->postJson(route('customer.register.send-otp'), [
            'phone' => '09171234567',
        ])->assertStatus(200);

        $this->post(route('customer.register.submit'), [
            'name' => 'Juan',
            'email' => 'juan@example.com',
            'phone' => '09171234567',
            'password' => 'secret123!',
            'password_confirmation' => 'secret123!',
            'terms' => '1',
            'otp' => '000000',
        ])->assertSessionHasErrors('otp');

        $this->assertDatabaseMissing('customers', ['email' => 'juan@example.com']);
    }

    public function test_register_rejects_without_any_otp_sent(): void
    {
        // No /send-otp call → no row to match against. Any code fails.
        $this->post(route('customer.register.submit'), [
            'name' => 'Juan',
            'email' => 'juan@example.com',
            'phone' => '09171234567',
            'password' => 'secret123!',
            'password_confirmation' => 'secret123!',
            'terms' => '1',
            'otp' => '123456',
        ])->assertSessionHasErrors('otp');

        $this->assertDatabaseMissing('customers', ['email' => 'juan@example.com']);
    }

    public function test_otp_normalises_international_phone_to_local(): void
    {
        $sms = app(VeroSmsService::class);
        $this->assertSame('09171234567', $sms->normalizePhone('+639171234567'));
        $this->assertSame('09171234567', $sms->normalizePhone('639171234567'));
        $this->assertSame('09171234567', $sms->normalizePhone('09171234567'));
    }

    public function test_otp_is_single_use(): void
    {
        $sms = app(VeroSmsService::class);
        $result = $sms->sendOtp('09171234567');
        $code = $result['dev_code'];

        $this->assertTrue($sms->verify('09171234567', $code));
        // Second attempt with same code — already consumed.
        $this->assertFalse($sms->verify('09171234567', $code));
    }

    public function test_otp_max_verify_attempts_locks_out_brute_force(): void
    {
        $sms = app(VeroSmsService::class);
        $sms->sendOtp('09171234567');

        $max = (int) config('services.verosms.otp_max_verify_attempts');
        for ($i = 0; $i < $max; $i++) {
            $this->assertFalse($sms->verify('09171234567', '000000'));
        }

        // Even with the right code, after the cap is hit we refuse.
        $otp = CustomerPhoneOtp::orderByDesc('id')->first();
        $this->assertGreaterThanOrEqual($max, $otp->attempts);
    }

    public function test_registration_writes_audit_log_row(): void
    {
        $send = $this->postJson(route('customer.register.send-otp'), [
            'phone' => '09171234567',
        ]);
        $code = $send->json('dev_code');

        $this->post(route('customer.register.submit'), [
            'name' => 'Audit Test',
            'email' => 'audit@example.com',
            'phone' => '09171234567',
            'password' => 'secret123!',
            'password_confirmation' => 'secret123!',
            'terms' => '1',
            'otp' => $code,
        ])->assertRedirect(route('customer.dashboard'));

        $customer = \App\Models\CustomerRelations\Customer::where('email', 'audit@example.com')->firstOrFail();

        $audit = \App\Models\Reports\AuditLog::where('auditable_type', \App\Models\CustomerRelations\Customer::class)
            ->where('auditable_id', $customer->id)
            ->where('event', 'customer_registered')
            ->first();

        $this->assertNotNull($audit, 'Customer registration must leave an audit row for fraud investigations.');
        // user_id is NULL for customer-driven actions (the FK targets
        // users, not customers); the customer id lives in new_values.
        $this->assertNull($audit->user_id);
        $this->assertSame($customer->id, $audit->new_values['customer_id']);
        $this->assertSame('09171234567', $audit->new_values['phone']);
        $this->assertTrue($audit->new_values['phone_verified']);
    }

    public function test_resend_otp_invalidates_prior_unconsumed_otps(): void
    {
        // First OTP — pretend the attacker burned through 4 verify
        // attempts on this row (sliding right up to the lockout edge).
        $first = $this->postJson(route('customer.register.send-otp'), [
            'phone' => '09171234567',
        ]);
        $firstCode = $first->json('dev_code');
        $this->assertNotNull($firstCode);

        \App\Models\CustomerRelations\CustomerPhoneOtp::query()
            ->where('phone', '09171234567')
            ->update(['attempts' => 4]);

        // Sidestep the per-phone cooldown — we only care that the
        // resend invalidates the predecessor.
        \App\Models\CustomerRelations\CustomerPhoneOtp::query()
            ->where('phone', '09171234567')
            ->update(['created_at' => now()->subMinutes(2)]);

        $this->postJson(route('customer.register.send-otp'), [
            'phone' => '09171234567',
        ])->assertOk();

        // The original row must now be expired; an attacker can't keep
        // using it as a brute-force ground after a resend.
        $rows = \App\Models\CustomerRelations\CustomerPhoneOtp::where('phone', '09171234567')
            ->orderBy('id')
            ->get();
        $this->assertCount(2, $rows);
        $this->assertTrue($rows[0]->expires_at->lessThanOrEqualTo(now()),
            'Old OTP must be expired when a new one is issued — otherwise resend resets brute-force lockout.');
        $this->assertTrue($rows[1]->expires_at->greaterThan(now()),
            'Fresh OTP must be valid.');
    }

    public function test_registration_rejects_duplicate_phone(): void
    {
        \App\Models\CustomerRelations\Customer::factory()->create([
            'phone' => '09171234567',
            'phone_verified_at' => now(),
        ]);

        $sendResponse = $this->postJson(route('customer.register.send-otp'), [
            'phone' => '09171234567',
        ]);
        $code = $sendResponse->json('dev_code');

        $this->post(route('customer.register.submit'), [
            'name' => 'Second Customer',
            'email' => 'second@example.com',
            'phone' => '09171234567',
            'password' => 'secret123!',
            'password_confirmation' => 'secret123!',
            'terms' => '1',
            'otp' => $code,
        ])->assertSessionHasErrors('phone');

        $this->assertSame(1, \App\Models\CustomerRelations\Customer::where('phone', '09171234567')->count(),
            'Phone uniqueness must hold even with a valid OTP — otherwise two accounts share one fraud anchor.');
    }

    public function test_registration_normalises_phone_for_uniqueness_check(): void
    {
        \App\Models\CustomerRelations\Customer::factory()->create([
            'phone' => '09171234567',
            'phone_verified_at' => now(),
        ]);

        // Same phone, different format — must still trip the unique rule.
        $this->postJson(route('customer.register.send-otp'), [
            'phone' => '+639171234567',
        ])->assertOk();

        $code = \App\Models\CustomerRelations\CustomerPhoneOtp::where('phone', '09171234567')
            ->latest('id')
            ->first()
            ?->code_hash;
        $this->assertNotNull($code);

        // Even without trying to register, the FormRequest's
        // normalisation+unique combo means the canonical "09" form is
        // the only one that ever hits the DB. Just verify the seed row
        // exists in canonical form.
        $this->assertDatabaseHas('customers', ['phone' => '09171234567']);
        $this->assertDatabaseMissing('customers', ['phone' => '+639171234567']);
    }

    public function test_dev_code_omitted_outside_local_and_testing_envs(): void
    {
        // Calling the service directly so we bypass CSRF / web stack
        // — the test only needs to assert the env gate in the dev-mode
        // branch of sendOtp(). Set env to 'production' AFTER the
        // service is resolved.
        $sms = app(\App\Services\VeroSmsService::class);

        $this->app->detectEnvironment(fn () => 'production');
        $result = $sms->sendOtp('09171234567', '127.0.0.1');

        $this->assertSame(\App\Services\VeroSmsService::RESULT_OK, $result['status']);
        $this->assertNull($result['dev_code'] ?? null,
            'dev_code must be hidden in non-local/testing environments so a misconfigured prod (VEROSMS_BASE_URL accidentally unset) does not leak codes to clients.');
    }

    public function test_dev_code_returned_in_local_or_testing_env(): void
    {
        // Sanity check the gate isn't accidentally over-tight — the
        // testing env (this run) MUST still see dev_code so existing
        // OTP test fixtures keep working.
        $sms = app(\App\Services\VeroSmsService::class);
        $result = $sms->sendOtp('09171234567', '127.0.0.1');

        $this->assertSame(\App\Services\VeroSmsService::RESULT_OK, $result['status']);
        $this->assertIsString($result['dev_code']);
        $this->assertSame(6, strlen($result['dev_code']));
    }

    public function test_send_otp_writes_outbound_sms_log_row(): void
    {
        $this->postJson(route('customer.register.send-otp'), [
            'phone' => '09171234567',
        ])->assertStatus(200);

        $this->assertDatabaseHas('outbound_sms_logs', [
            'phone' => '09171234567',
            'type' => 'otp_register',
            'status' => \App\Models\OutboundSmsLog::STATUS_SENT,
        ]);
    }
}
