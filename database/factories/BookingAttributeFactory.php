<?php

namespace Database\Factories;

use App\Models\BookingAttribute;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BookingAttribute>
 */
class BookingAttributeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fk_booking_detail_id' => 1, // Anggap saja 1 sebagai default
            'fk_attribute_id' => 1,
            'quantity' => 1,
            'price' => 10000,
            'total' => 10000,
            'transaction_date' => now()->toDateString(),
            'status' => 'dipinjam',
            'customer_name' => $this->faker->name(),
            'customer_phone' => $this->faker->phoneNumber(),
            'duration_hours' => 1
        ];
    }
}
