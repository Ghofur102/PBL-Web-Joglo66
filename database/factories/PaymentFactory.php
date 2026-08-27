<?php

namespace Database\Factories;

use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
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
            'reference_id' => $this->faker->uuid(),
            'payment_type' => 'dp',
            'method' => 'cash',
            'amount' => 100000,
            'status' => 'success',
            'paid_at' => now(),
        ];
    }
}
