<?php

namespace Database\Factories;

use App\Models\BookingDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingDetail>
 */
class BookingDetailFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fk_booking_id' => 1,
            'play_date' => now()->toDateString(),
            'start_play_time' => '10:00:00',
            'end_play_time' => '11:00:00',
            'price' => 100000,
            'status' => 'waiting',
        ];
    }
}
