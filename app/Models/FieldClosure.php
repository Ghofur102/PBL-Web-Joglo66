<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FieldClosure extends CoreDmlModel
{
    protected $table = 'field_closures';

    protected $fillable = [
        'fk_user_id', 'fk_field_id', 'field_closure_start_time', 'field_closure_end_time', 'reason',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fk_user_id', 'id');
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(Field::class, 'fk_field_id', 'id');
    }

    public function bookingReschedule(): HasMany
    {
        return $this->hasMany(BookingReschedule::class, 'fk_field_closure_id', 'id');
    }

    public function bookingCancelled(): HasMany
    {
        return $this->hasMany(BookingCancelled::class, 'fk_field_closure_id', 'id');
    }
}
