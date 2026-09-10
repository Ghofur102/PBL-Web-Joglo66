<?php

namespace Database\Factories;

use App\Models\FieldPrice;
use App\Models\Field;
use Illuminate\Database\Eloquent\Factories\Factory;

class FieldPriceFactory extends Factory
{
    protected $model = FieldPrice::class;

    public function definition(): array
    {
        return [
            'fk_field_id' => Field::factory(),
            'day_type'    => 'saturday',
            'start_time'  => '08:00:00',
            'end_time'    => '22:00:00',
            'price'       => 150000,
        ];
    }
}
