<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Expense extends Model
{
    use HasFactory;
    protected $connection = 'mysql_joglo66_app';

    protected $table = 'expenses';

    protected $fillable = [
        'fk_field_id',
        'fk_user_id',
        'name',
        'category',
        'quantity',
        'unit_price',
        'expense_date',
        'proof_photo',
        'note',
        'generate_at',
    ];

    protected $appends = ['amount'];

    public function getAmountAttribute(): int
    {
        return (int) ($this->quantity * $this->unit_price);
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(Field::class, 'fk_field_id', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fk_user_id', 'id');
    }

    public function salaries(): HasMany
    {
        return $this->hasMany(EmployeeSalary::class, 'fk_expense_id', 'id');
    }
}
