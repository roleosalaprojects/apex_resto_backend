<?php

namespace Tests\Feature\API\v1\customer;

use App\Models\CustomerRelations\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Tests\TestCase;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $clientId = DB::table('oauth_clients')->insertGetId([
            'user_id' => null,
            'name' => 'Test Personal Access Client',
            'secret' => Str::random(40),
            'provider' => 'customers',
            'redirect' => '',
            'personal_access_client' => true,
            'password_client' => false,
            'revoked' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('oauth_personal_access_clients')->insert([
            'client_id' => $clientId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_customer_can_login_via_api(): void
    {
        $customer = Customer::factory()->create([
            'email' => 'customer@example.com',
            'password' => 'password',
        ]);

        $response = $this->postJson('/api/v1/customer/login', [
            'email' => 'customer@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'customer',
                    'token',
                    'token_type',
                ],
                'message',
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Login successful',
            ]);
    }

    public function test_customer_cannot_login_with_invalid_credentials(): void
    {
        Customer::factory()->create([
            'email' => 'customer@example.com',
            'password' => 'password',
        ]);

        $response = $this->postJson('/api/v1/customer/login', [
            'email' => 'customer@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid credentials',
            ]);
    }

    public function test_inactive_customer_cannot_login(): void
    {
        Customer::factory()->inactive()->create([
            'email' => 'customer@example.com',
            'password' => 'password',
        ]);

        $response = $this->postJson('/api/v1/customer/login', [
            'email' => 'customer@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Your account has been deactivated',
            ]);
    }

    public function test_customer_can_register_via_api(): void
    {
        $response = $this->postJson('/api/v1/customer/register', [
            'name' => 'John Doe',
            'email' => 'newcustomer@example.com',
            'phone' => '09171234567',
            'address' => '123 Test Street',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'customer',
                    'token',
                    'token_type',
                ],
                'message',
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Registration successful',
            ]);

        $this->assertDatabaseHas('customers', [
            'email' => 'newcustomer@example.com',
            'name' => 'John Doe',
        ]);
    }

    public function test_customer_cannot_register_with_existing_email(): void
    {
        Customer::factory()->create([
            'email' => 'existing@example.com',
        ]);

        $response = $this->postJson('/api/v1/customer/register', [
            'name' => 'John Doe',
            'email' => 'existing@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_authenticated_customer_can_get_profile(): void
    {
        $customer = Customer::factory()->create();

        Passport::actingAs($customer, [], 'customer-api');

        $response = $this->getJson('/api/v1/customer/me');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'name',
                    'email',
                ],
            ]);
    }

    public function test_unauthenticated_customer_cannot_get_profile(): void
    {
        $response = $this->getJson('/api/v1/customer/me');

        $response->assertStatus(401);
    }

    public function test_authenticated_customer_can_logout(): void
    {
        $customer = Customer::factory()->create();

        Passport::actingAs($customer, [], 'customer-api');

        $response = $this->postJson('/api/v1/customer/logout');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Logged out successfully',
            ]);
    }
}
