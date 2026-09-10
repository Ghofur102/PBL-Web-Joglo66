<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attribute extends CoreDmlModel
{
    protected $table = 'attributes';

    protected $fillable = [
        'fk_field_id', 'name', 'type', 'stock', 'price_hour', 'status',
    ];

    public function field(): BelongsTo
    {
        return $this->belongsTo(Field::class, 'fk_field_id', 'id');
    }
}
