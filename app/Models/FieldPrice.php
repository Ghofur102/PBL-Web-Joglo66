<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class FieldPrice extends CoreDmlModel
{
    use HasFactory;
    protected $table = 'field_prices';

    protected $fillable = [
        'fk_field_id', 'start_time', 'end_time', 'day_type', 'price',
    ];

    public function field(): BelongsTo
    {
        return $this->belongsTo(Field::class, 'fk_field_id', 'id');
    }
}
