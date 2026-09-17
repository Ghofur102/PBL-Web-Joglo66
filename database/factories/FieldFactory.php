<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Field;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class FieldFactory extends Factory
{
    protected $model = Field::class;

    public function definition(): array
    {
        return [
            'name'                 => fake()->company() . ' Arena',
            'description'          => fake()->paragraph(),
            'image_url'            => fake()->imageUrl(800, 600, 'sports'),
            'category'             => fake()->randomElement(['futsal', 'mini soccer']),
            'fk_user_id'           => User::factory()->state(['role' => UserRole::OWNER->value]),
            'min_cancel_days'      => 3,
            'min_reschedule_days'  => 3,
            'max_reschedule_times' => 1,
        ];
    }
}
