<?php

namespace Tests\Feature\Restaurant;

use App\Models\Employees\Role;
use App\Models\Restaurant\Reservation;
use App\Models\Restaurant\RestaurantTable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class ReservationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $role = Role::factory()->admin()->create();
        $this->user = User::factory()->create(['role_id' => $role->id, 'user_id' => 1]);
    }

    public function test_can_create_reservation(): void
    {
        Passport::actingAs($this->user);
        $table = RestaurantTable::factory()->create(['user_id' => 1]);

        $response = $this->postJson('/api/v1/reservations', [
            'name' => 'Juan Dela Cruz',
            'phone' => '09171234567',
            'party_size' => 4,
            'reserved_at' => now()->addDay()->toDateTimeString(),
            'table_id' => $table->id,
        ]);

        $response->assertStatus(201)->assertJsonPath('data.status', Reservation::STATUS_PENDING);
        $this->assertEquals(90, Reservation::first()->duration_minutes);
    }

    public function test_can_update_reservation_status(): void
    {
        Passport::actingAs($this->user);
        $reservation = Reservation::factory()->create(['user_id' => 1]);

        $this->postJson("/api/v1/reservations/{$reservation->id}/status", [
            'status' => Reservation::STATUS_CONFIRMED,
        ])->assertStatus(200)->assertJsonPath('data.status', Reservation::STATUS_CONFIRMED);
    }

    public function test_invalid_status_is_rejected(): void
    {
        Passport::actingAs($this->user);
        $reservation = Reservation::factory()->create(['user_id' => 1]);

        $this->postJson("/api/v1/reservations/{$reservation->id}/status", [
            'status' => 'teleported',
        ])->assertStatus(422);
    }

    public function test_index_is_tenant_isolated(): void
    {
        Reservation::factory()->create(['user_id' => 999]);
        Reservation::factory()->create(['user_id' => 1]);

        Passport::actingAs($this->user);
        $response = $this->getJson('/api/v1/reservations');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }
}
