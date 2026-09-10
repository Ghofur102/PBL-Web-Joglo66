<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialReport extends CoreDmlModel
{
    protected $table = 'financial_reports';

    protected $fillable = [
        'fk_field_id', 'year', 'mont', 'total_income', 'total_expense', 'net_profit', 'generate_at',
    ];

    public function field(): BelongsTo
    {
        return $this->belongsTo(Field::class, 'fk_field_id', 'id');
    }
}
