<?php

namespace Database\Factories;

use App\Models\Attribute;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attribute>
 */
class AttributeFactory extends Factory
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
            'name' => $this->faker->word(),
            'stock' => 10,
            'price_hour' => 10000,
            'status' => 'active',
        ];
    }
}
