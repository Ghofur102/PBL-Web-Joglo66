<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeSalary extends CoreDmlModel
{
    protected $table = 'employee_salaries';

    protected $fillable = [
        'fk_employee_id', 'fk_expense_id', 'amount_paid',
        'period_month', 'period_year', 'payment_date',
        'bonus', 'deduction', 'notes'
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'fk_employee_id', 'id');
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class, 'fk_expense_id', 'id');
    }
}
