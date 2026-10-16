<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FieldRefundRule extends CoreDmlModel
{
    protected $table = 'field_refund_rules';

    protected $fillable = [
        'fk_field_id',
        'rule_type',
        'min_hours_before',
        'refund_percent',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'min_hours_before' => 'integer',
            'refund_percent' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(Field::class, 'fk_field_id', 'id');
    }
}
