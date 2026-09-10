<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Field extends CoreDmlModel
{
    use HasFactory;

    protected $table = 'fields';

    protected $fillable = [
        'name', 'description', 'image_url', 'category'
    ];

    public function fieldPrices(): HasMany
    {
        return $this->hasMany(FieldPrice::class, 'fk_field_id', 'id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'fk_field_id', 'id');
    }

    public function attributes(): HasMany
    {
        return $this->hasMany(Attribute::class, 'fk_field_id', 'id');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'fk_field_id', 'id');
    }

    public function financialReport(): HasMany
    {
        return $this->hasMany(FinancialReport::class, 'fk_field_id', 'id');
    }

    public function fieldAdmin(): HasMany
    {
        return $this->hasMany(FieldAdmin::class, 'fk_field_id', 'id');
    }
}
