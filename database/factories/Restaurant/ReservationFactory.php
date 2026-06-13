<?php

namespace Database\Factories\Restaurant;

use App\Models\Restaurant\Reservation;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReservationFactory extends Factory
{
    protected $model = Reservation::class;

    public function definition(): array
    {
        return [
            'customer_id' => null,
            'name' => $this->faker->name(),
            'phone' => $this->faker->phoneNumber(),
            'party_size' => $this->faker->numberBetween(1, 8),
            'reserved_at' => $this->faker->dateTimeBetween('now', '+1 week'),
            'duration_minutes' => 90,
            'table_id' => null,
            'status' => Reservation::STATUS_PENDING,
            'notes' => null,
            'store_id' => null,
            'user_id' => 1,
        ];
    }
}
