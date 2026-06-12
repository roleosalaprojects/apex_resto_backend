<?php

namespace Tests\Feature\Customer;

use App\Models\CustomerRelations\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_unverified_customer_cannot_view_profile(): void
    {
        $customer = Customer::factory()->unverified()->create();

        $response = $this->actingAs($customer, 'customer')
            ->get(route('customer.profile.edit'));

        $response->assertRedirect(route('customer.verification.notice'));
    }

    public function test_guest_cannot_view_profile(): void
    {
        $response = $this->get(route('customer.profile.edit'));

        $response->assertRedirect(route('customer.login'));
    }

    public function test_customer_can_view_profile_page(): void
    {
        $customer = Customer::factory()->create();

        $response = $this->actingAs($customer, 'customer')
            ->get(route('customer.profile.edit'));

        $response->assertStatus(200);
        $response->assertViewIs('customer.profile.edit');
        $response->assertSee($customer->email);
    }

    public function test_customer_can_update_profile_details(): void
    {
        $customer = Customer::factory()->create([
            'name' => 'Old Name',
            'phone' => '09171110000',
            'phone_verified_at' => now(),
            'address' => 'Old',
        ]);

        // Note: phone is unchanged — non-phone edits don't require OTP.
        $response = $this->actingAs($customer, 'customer')
            ->put(route('customer.profile.update'), [
                'name' => 'New Name',
                'phone' => '09171110000',
                'address' => '123 New Street',
                'city' => 'Cebu',
                'zip' => '6000',
                'province' => 'Cebu',
                'country' => 'Philippines',
                'e_name' => 'Jane Doe',
                'e_phone' => '0917-555-9999',
                'e_address' => '999 Other Street',
            ]);

        $response->assertRedirect(route('customer.profile.edit'));
        $response->assertSessionHas('success');

        $customer->refresh();
        $this->assertSame('New Name', $customer->name);
        $this->assertSame('09171110000', $customer->phone);
        $this->assertSame('123 New Street', $customer->address);
        $this->assertSame('Jane Doe', $customer->e_name);
    }

    public function test_email_cannot_be_changed_via_profile_update(): void
    {
        $customer = Customer::factory()->create([
            'email' => 'original@example.com',
        ]);

        $this->actingAs($customer, 'customer')
            ->put(route('customer.profile.update'), [
                'name' => $customer->name,
                'phone' => $customer->phone,
                'email' => 'attacker@example.com',
            ]);

        $this->assertSame('original@example.com', $customer->fresh()->email);
    }

    public function test_profile_update_requires_name(): void
    {
        $customer = Customer::factory()->create();

        $response = $this->actingAs($customer, 'customer')
            ->from(route('customer.profile.edit'))
            ->put(route('customer.profile.update'), [
                'name' => '',
            ]);

        $response->assertRedirect(route('customer.profile.edit'));
        $response->assertSessionHasErrors('name');
    }

    public function test_customer_can_upload_avatar(): void
    {
        $customer = Customer::factory()->create(['image' => null]);

        $file = UploadedFile::fake()->image('avatar.jpg', 200, 200);

        try {
            $this->actingAs($customer, 'customer')
                ->put(route('customer.profile.update'), [
                    'name' => $customer->name,
                    'phone' => $customer->phone,
                    'avatar' => $file,
                ]);

            $customer->refresh();
            $this->assertNotNull($customer->image);
            $this->assertStringStartsWith('img/customers/', $customer->image);
            $this->assertFileExists(public_path($customer->image));
        } finally {
            if ($customer->image && is_file(public_path($customer->image))) {
                @unlink(public_path($customer->image));
            }
        }
    }

    public function test_customer_can_remove_avatar(): void
    {
        $location = 'img/customers/';
        $name = 'test-avatar-'.uniqid().'.png';
        $absolute = public_path($location.$name);
        @mkdir(dirname($absolute), 0777, true);
        file_put_contents($absolute, 'fake');

        $customer = Customer::factory()->create(['image' => $location.$name]);

        $this->actingAs($customer, 'customer')
            ->put(route('customer.profile.update'), [
                'name' => $customer->name,
                'phone' => $customer->phone,
                'remove_avatar' => '1',
            ]);

        $this->assertNull($customer->fresh()->image);
        $this->assertFileDoesNotExist($absolute);
    }

    public function test_avatar_upload_strips_attacker_controlled_extension(): void
    {
        $customer = Customer::factory()->create(['image' => null]);

        $jpegPath = tempnam(sys_get_temp_dir(), 'avatar').'.jpg';
        $gd = imagecreatetruecolor(10, 10);
        imagejpeg($gd, $jpegPath);
        imagedestroy($gd);

        $file = new UploadedFile($jpegPath, 'evil.html', 'image/jpeg', null, true);

        try {
            $this->actingAs($customer, 'customer')
                ->put(route('customer.profile.update'), [
                    'name' => $customer->name,
                    'phone' => $customer->phone,
                    'avatar' => $file,
                ]);

            $customer->refresh();
            $this->assertNotNull($customer->image, 'Avatar should be saved with a content-derived extension.');
            $this->assertStringEndsWith('.jpg', $customer->image, 'Saved avatar must use a content-derived extension, not the client-supplied one.');
            $this->assertStringNotContainsString('.html', $customer->image);
            $this->assertFileExists(public_path($customer->image));
        } finally {
            if ($customer->image && is_file(public_path($customer->image))) {
                @unlink(public_path($customer->image));
            }
            if (is_file($jpegPath)) {
                @unlink($jpegPath);
            }
        }
    }

    public function test_avatar_upload_rejects_non_image(): void
    {
        $customer = Customer::factory()->create();

        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $response = $this->actingAs($customer, 'customer')
            ->from(route('customer.profile.edit'))
            ->put(route('customer.profile.update'), [
                'name' => $customer->name,
                'phone' => $customer->phone,
                'avatar' => $file,
            ]);

        $response->assertSessionHasErrors('avatar');
    }

    public function test_customer_can_view_password_page(): void
    {
        $customer = Customer::factory()->create();

        $response = $this->actingAs($customer, 'customer')
            ->get(route('customer.password.edit'));

        $response->assertStatus(200);
        $response->assertViewIs('customer.profile.password');
    }

    public function test_customer_can_update_password(): void
    {
        $customer = Customer::factory()->create([
            'password' => 'old-password',
        ]);

        $response = $this->actingAs($customer, 'customer')
            ->put(route('customer.password.update'), [
                'current_password' => 'old-password',
                'password' => 'brand-new-password',
                'password_confirmation' => 'brand-new-password',
            ]);

        $response->assertRedirect(route('customer.password.edit'));
        $response->assertSessionHas('success');

        $this->assertTrue(Hash::check('brand-new-password', $customer->fresh()->password));
    }

    public function test_password_update_requires_correct_current_password(): void
    {
        $customer = Customer::factory()->create([
            'password' => 'old-password',
        ]);

        $response = $this->actingAs($customer, 'customer')
            ->from(route('customer.password.edit'))
            ->put(route('customer.password.update'), [
                'current_password' => 'wrong-password',
                'password' => 'brand-new-password',
                'password_confirmation' => 'brand-new-password',
            ]);

        $response->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check('old-password', $customer->fresh()->password));
    }

    public function test_password_update_requires_confirmation(): void
    {
        $customer = Customer::factory()->create([
            'password' => 'old-password',
        ]);

        $response = $this->actingAs($customer, 'customer')
            ->from(route('customer.password.edit'))
            ->put(route('customer.password.update'), [
                'current_password' => 'old-password',
                'password' => 'brand-new-password',
                'password_confirmation' => 'mismatch',
            ]);

        $response->assertSessionHasErrors('password');
    }

    public function test_password_update_rejects_same_password(): void
    {
        $customer = Customer::factory()->create([
            'password' => 'old-password',
        ]);

        $response = $this->actingAs($customer, 'customer')
            ->from(route('customer.password.edit'))
            ->put(route('customer.password.update'), [
                'current_password' => 'old-password',
                'password' => 'old-password',
                'password_confirmation' => 'old-password',
            ]);

        $response->assertSessionHasErrors('password');
    }
}
