<?php

namespace Database\Factories;

use App\Models\Expense;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fk_field_id' => 1,
            'fk_user_id' => 1,
            'name' => $this->faker->words(3, true),
            'category' => 'operasional',
            'quantity' => 1,
            'unit_price' => 20000,
            'expense_date' => now()->toDateString(),
            'generate_at' => now(),
        ];
    }
}
