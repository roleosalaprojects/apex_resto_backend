<?php

namespace Tests\Feature;

use App\Models\Employees\Role;
use App\Models\Employees\Shift;
use App\Models\Employees\ShiftBreak;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Role $role;

    protected function setUp(): void
    {
        parent::setUp();

        $this->role = Role::factory()->admin()->create();
        $this->user = User::factory()->create([
            'role_id' => $this->role->id,
            'user_id' => 1,
        ]);
    }

    public function test_shifts_index_route_exists(): void
    {
        $this->actingAs($this->user);

        $this->assertTrue(route('shifts.index') !== null);
    }

    public function test_shifts_create_route_exists(): void
    {
        $this->actingAs($this->user);

        $this->assertTrue(route('shifts.create') !== null);
    }

    public function test_user_can_clock_in(): void
    {
        $response = $this->actingAs($this->user)->post(route('shifts.store'), [
            'starting_cash' => 1000.00,
            'notes' => 'Starting shift',
        ]);

        $response->assertRedirect(route('shifts.index'));
        $response->assertSessionHas('msg');

        $this->assertDatabaseHas('shifts', [
            'user_id' => $this->user->id,
            'starting_cash' => 1000.00,
            'status' => 'active',
        ]);
    }

    public function test_user_cannot_clock_in_with_active_shift(): void
    {
        Shift::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->user)->post(route('shifts.store'), [
            'starting_cash' => 1000.00,
        ]);

        $response->assertRedirect(route('shifts.index'));
        $response->assertSessionHas('error');
    }

    public function test_user_can_clock_out(): void
    {
        $shift = Shift::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'active',
            'starting_cash' => 1000.00,
        ]);

        $response = $this->actingAs($this->user)->post(route('shifts.clock-out', $shift), [
            'ending_cash' => 1500.00,
        ]);

        $response->assertRedirect(route('shifts.index'));
        $response->assertSessionHas('msg');

        $this->assertDatabaseHas('shifts', [
            'id' => $shift->id,
            'status' => 'completed',
            'ending_cash' => 1500.00,
        ]);
    }

    public function test_user_cannot_clock_out_inactive_shift(): void
    {
        $shift = Shift::factory()->completed()->create([
            'user_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)->post(route('shifts.clock-out', $shift), [
            'ending_cash' => 1500.00,
        ]);

        $response->assertRedirect(route('shifts.index'));
        $response->assertSessionHas('error');
    }

    public function test_user_can_start_break(): void
    {
        $shift = Shift::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->user)->post(route('shifts.start-break', $shift), [
            'type' => 'lunch',
            'reason' => 'Lunch time',
        ]);

        $response->assertRedirect(route('shifts.show', $shift));
        $response->assertSessionHas('msg');

        $this->assertDatabaseHas('shift_breaks', [
            'shift_id' => $shift->id,
            'type' => 'lunch',
        ]);
    }

    public function test_user_cannot_start_break_while_on_break(): void
    {
        $shift = Shift::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'active',
        ]);

        ShiftBreak::factory()->create([
            'shift_id' => $shift->id,
            'break_end' => null,
        ]);

        $response = $this->actingAs($this->user)->post(route('shifts.start-break', $shift), [
            'type' => 'short',
        ]);

        $response->assertRedirect(route('shifts.show', $shift));
        $response->assertSessionHas('error');
    }

    public function test_user_can_end_break(): void
    {
        $shift = Shift::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'active',
        ]);

        ShiftBreak::factory()->create([
            'shift_id' => $shift->id,
            'break_end' => null,
        ]);

        $response = $this->actingAs($this->user)->post(route('shifts.end-break', $shift));

        $response->assertRedirect(route('shifts.show', $shift));
        $response->assertSessionHas('msg');

        $this->assertDatabaseMissing('shift_breaks', [
            'shift_id' => $shift->id,
            'break_end' => null,
        ]);
    }

    public function test_shift_show_route_exists(): void
    {
        $shift = Shift::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $this->actingAs($this->user);

        $this->assertTrue(route('shifts.show', $shift) !== null);
    }

    public function test_shift_calculates_total_break_minutes(): void
    {
        $shift = Shift::factory()->create([
            'user_id' => $this->user->id,
        ]);

        $breakStart = now()->subMinutes(30);
        ShiftBreak::factory()->create([
            'shift_id' => $shift->id,
            'break_start' => $breakStart,
            'break_end' => $breakStart->copy()->addMinutes(15),
        ]);

        $shift->refresh();
        $shift->load('breaks');

        $this->assertEquals(15, $shift->total_break_minutes);
    }

    public function test_shift_is_active_method(): void
    {
        $activeShift = Shift::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'active',
        ]);

        $completedShift = Shift::factory()->completed()->create([
            'user_id' => $this->user->id,
        ]);

        $this->assertTrue($activeShift->isActive());
        $this->assertFalse($completedShift->isActive());
    }
}
