<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends CoreDmlModel
{
    protected $table = 'employees';

    protected $fillable = [
        'fk_user_id', 'name', 'phone_number', 'address',
        'position', 'base_salary', 'join_date', 'status'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fk_user_id', 'id');
    }

    public function salaries(): HasMany
    {
        return $this->hasMany(EmployeeSalary::class, 'fk_employee_id', 'id');
    }

    public function getActivePhoneAttribute()
    {
        if (!empty($this->phone_number)) {
            return $this->phone_number;
        }

        if ($this->user && !empty($this->user->phone)) {
            return $this->user->phone;
        }

        return '-';
    }
}
