<?php

namespace Tests\Feature\Admin\Restaurant;

use App\Models\Employees\Role;
use App\Models\Restaurant\Reservation;
use App\Models\Restaurant\RestaurantTable;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarReservationsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::factory()->admin()->create();
        $this->admin = User::factory()->create(['role_id' => $role->id, 'user_id' => 1]);
    }

    public function test_reservations_feed_serves_calendar_events(): void
    {
        $table = RestaurantTable::factory()->create(['user_id' => 1, 'name' => 'M1']);
        Reservation::create([
            'name' => 'Dela Cruz',
            'party_size' => 6,
            'reserved_at' => now()->addDay()->setTime(19, 0),
            'duration_minutes' => 90,
            'table_id' => $table->id,
            'status' => Reservation::STATUS_CONFIRMED,
            'user_id' => 1,
        ]);
        // Cancelled bookings stay off the calendar.
        Reservation::create([
            'name' => 'Gone',
            'party_size' => 2,
            'reserved_at' => now()->addDays(2),
            'status' => Reservation::STATUS_CANCELLED,
            'user_id' => 1,
        ]);

        $events = $this->actingAs($this->admin)
            ->getJson(route('calendars.reservations'))
            ->assertOk()
            ->json();

        $this->assertCount(1, $events);
        $this->assertSame('Dela Cruz · 6 pax', $events[0]['title']);
        $this->assertSame('reservation', $events[0]['type']);
        $this->assertSame('M1', $events[0]['table']);
        $this->assertFalse($events[0]['editable']);
    }

    public function test_calendar_page_carries_tables_for_the_booking_dialog(): void
    {
        RestaurantTable::factory()->create(['user_id' => 1, 'name' => 'P7']);

        $this->actingAs($this->admin)
            ->get(route('dashboards.calendar'))
            ->assertOk()
            ->assertSee('P7');
    }

    public function test_ajax_store_books_a_reservation_and_returns_json(): void
    {
        $this->actingAs($this->admin)
            ->postJson(route('reservations.store'), [
                'name' => 'Santos',
                'party_size' => 4,
                'reserved_at' => '2026-07-20 19:30',
                'phone' => '0917',
            ])
            ->assertStatus(201)
            ->assertJsonPath('message', 'Reservation booked!');

        $this->assertDatabaseHas('reservations', [
            'name' => 'Santos',
            'party_size' => 4,
            'status' => Reservation::STATUS_PENDING,
        ]);
    }
}
