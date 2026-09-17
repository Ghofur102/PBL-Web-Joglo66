<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Field extends CoreDmlModel
{
    use HasFactory;

    protected $table = 'fields';

    protected $fillable = [
        'name',
        'description',
        'image_url',
        'category',
        'fk_user_id',
        'min_cancel_days',
        'min_reschedule_days',
        'max_reschedule_times',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fk_user_id', 'id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fk_user_id', 'id');
    }

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

    public function fieldWorkers(): HasMany
    {
        return $this->hasMany(FieldWorker::class, 'fk_field_id', 'id');
    }
}
