<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Booking extends CoreDmlModel
{
    use HasFactory;

    protected $table = 'bookings';

    protected $fillable = [
        'fk_user_id', 'fk_field_id', 'booking_date', 'team_name', 'customer_phone', 'customer_email', 'notes'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fk_user_id', 'id');
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(Field::class, 'fk_field_id', 'id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(BookingDetail::class, 'fk_booking_id', 'id');
    }

    public function attributes(): HasMany
    {
        return $this->hasMany(BookingAttribute::class, 'fk_booking_id', 'id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'fk_booking_id', 'id');
    }
}
