<?php

namespace Tests\Feature\Customer;

use App\Models\CustomerRelations\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Profile edits hold the line on the fraud-anchor: a phone-number
 * change must be re-OTP'd against the NEW number before the swap is
 * saved. Every test here is keyed off that invariant.
 */
class ProfilePhoneUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function fields(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Existing Customer',
            'phone' => '09171234567',
            'address' => '123 Old St',
            'city' => null,
            'province' => null,
            'zip' => null,
            'country' => null,
            'e_name' => null,
            'e_phone' => null,
            'e_address' => null,
        ], $overrides);
    }

    public function test_save_without_phone_change_does_not_require_otp(): void
    {
        $customer = Customer::factory()->create([
            'phone' => '09171234567',
            'phone_verified_at' => now()->subYear(),
        ]);

        $this->actingAs($customer, 'customer')
            ->put(route('customer.profile.update'), $this->fields([
                'name' => 'Updated Name',
                'phone' => '09171234567',
            ]))
            ->assertRedirect(route('customer.profile.edit'));

        $customer->refresh();
        $this->assertSame('Updated Name', $customer->name);
        $this->assertSame('09171234567', $customer->phone);
    }

    public function test_phone_change_without_otp_is_rejected(): void
    {
        $customer = Customer::factory()->create([
            'phone' => '09171234567',
            'phone_verified_at' => now()->subYear(),
        ]);

        $this->actingAs($customer, 'customer')
            ->put(route('customer.profile.update'), $this->fields([
                'phone' => '09180000000',
            ]))
            ->assertSessionHasErrors('otp');

        $customer->refresh();
        $this->assertSame('09171234567', $customer->phone, 'Phone must not change without OTP verification.');
    }

    public function test_phone_change_with_wrong_otp_is_rejected(): void
    {
        $customer = Customer::factory()->create([
            'phone' => '09171234567',
            'phone_verified_at' => now()->subYear(),
        ]);

        $this->actingAs($customer, 'customer')
            ->postJson(route('customer.profile.send-phone-otp'), ['phone' => '09180000000'])
            ->assertStatus(200);

        $this->actingAs($customer, 'customer')
            ->put(route('customer.profile.update'), $this->fields([
                'phone' => '09180000000',
                'otp' => '000000',
            ]))
            ->assertSessionHasErrors('otp');

        $customer->refresh();
        $this->assertSame('09171234567', $customer->phone);
    }

    public function test_phone_change_with_correct_otp_persists_and_restamps_verified_at(): void
    {
        $customer = Customer::factory()->create([
            'phone' => '09171234567',
            'phone_verified_at' => now()->subYear(),
        ]);
        $oldVerifiedAt = $customer->phone_verified_at;

        $sendResponse = $this->actingAs($customer, 'customer')
            ->postJson(route('customer.profile.send-phone-otp'), ['phone' => '09180000000']);
        $sendResponse->assertStatus(200);
        $code = $sendResponse->json('dev_code');
        $this->assertNotNull($code, 'Dev mode should echo the OTP since VEROSMS_BASE_URL is unset in tests.');

        $this->actingAs($customer, 'customer')
            ->put(route('customer.profile.update'), $this->fields([
                'phone' => '09180000000',
                'otp' => $code,
            ]))
            ->assertRedirect(route('customer.profile.edit'));

        $customer->refresh();
        $this->assertSame('09180000000', $customer->phone);
        $this->assertNotNull($customer->phone_verified_at);
        $this->assertTrue(
            $customer->phone_verified_at->greaterThan($oldVerifiedAt),
            'phone_verified_at must be re-stamped on a successful swap.'
        );
    }

    public function test_phone_change_to_number_owned_by_another_customer_is_rejected_at_send(): void
    {
        Customer::factory()->create(['phone' => '09180000000']);
        $owner = Customer::factory()->create(['phone' => '09171234567']);

        // The dispatch endpoint collapses "taken by another customer"
        // and "same as your own" into a single generic 'unavailable'
        // response so an authenticated attacker can't walk a phone
        // book to map who has accounts.
        $this->actingAs($owner, 'customer')
            ->postJson(route('customer.profile.send-phone-otp'), ['phone' => '09180000000'])
            ->assertStatus(422)
            ->assertJsonFragment(['status' => 'unavailable']);
    }

    public function test_phone_change_to_number_owned_by_another_customer_is_rejected_at_save(): void
    {
        Customer::factory()->create(['phone' => '09180000000']);
        $owner = Customer::factory()->create(['phone' => '09171234567']);

        $this->actingAs($owner, 'customer')
            ->put(route('customer.profile.update'), $this->fields([
                'phone' => '09180000000',
                'otp' => '123456',
            ]))
            ->assertSessionHasErrors('phone');

        $owner->refresh();
        $this->assertSame('09171234567', $owner->phone);
    }

    public function test_send_phone_otp_rejects_when_phone_unchanged(): void
    {
        $customer = Customer::factory()->create(['phone' => '09171234567']);

        $this->actingAs($customer, 'customer')
            ->postJson(route('customer.profile.send-phone-otp'), ['phone' => '09171234567'])
            ->assertStatus(422)
            ->assertJsonFragment(['status' => 'unavailable']);
    }

    public function test_send_phone_otp_validates_phone_format(): void
    {
        $customer = Customer::factory()->create(['phone' => '09171234567']);

        $this->actingAs($customer, 'customer')
            ->postJson(route('customer.profile.send-phone-otp'), ['phone' => 'not-a-phone'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('phone');
    }

    public function test_phone_change_writes_audit_log_row(): void
    {
        $customer = Customer::factory()->create([
            'phone' => '09171234567',
            'phone_verified_at' => now()->subYear(),
        ]);

        $send = $this->actingAs($customer, 'customer')
            ->postJson(route('customer.profile.send-phone-otp'), ['phone' => '09180000000']);
        $code = $send->json('dev_code');

        $this->actingAs($customer, 'customer')
            ->put(route('customer.profile.update'), $this->fields([
                'phone' => '09180000000',
                'otp' => $code,
            ]))
            ->assertRedirect(route('customer.profile.edit'));

        $audit = \App\Models\Reports\AuditLog::where('auditable_type', Customer::class)
            ->where('auditable_id', $customer->id)
            ->where('event', 'customer_phone_changed')
            ->first();

        $this->assertNotNull($audit, 'Phone change is fraud-anchor critical — must leave an audit row.');
        $this->assertSame('09171234567', $audit->old_values['phone']);
        $this->assertSame('09180000000', $audit->new_values['new_phone']);
        $this->assertTrue($audit->new_values['verified_via_otp']);
    }

    public function test_phone_change_normalises_international_format(): void
    {
        $customer = Customer::factory()->create([
            'phone' => '09171234567',
            'phone_verified_at' => now()->subYear(),
        ]);

        $sendResponse = $this->actingAs($customer, 'customer')
            ->postJson(route('customer.profile.send-phone-otp'), ['phone' => '+639180000000']);
        $sendResponse->assertStatus(200);
        $code = $sendResponse->json('dev_code');

        $this->actingAs($customer, 'customer')
            ->put(route('customer.profile.update'), $this->fields([
                'phone' => '+639180000000',
                'otp' => $code,
            ]))
            ->assertRedirect(route('customer.profile.edit'));

        $customer->refresh();
        $this->assertSame('09180000000', $customer->phone, 'Stored phone must always be in canonical 09XXX form.');
    }
}
