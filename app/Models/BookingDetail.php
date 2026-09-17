<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BookingDetail extends CoreDmlModel
{
    protected $table = 'booking_details';

    protected $fillable = [
        'fk_booking_id',
        'play_date',
        'start_play_time',
        'end_play_time',
        'price',
        'status',
    ];

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class, 'fk_booking_id', 'id');
    }

    public function attributes(): HasMany
    {
        return $this->hasMany(BookingAttribute::class, 'fk_booking_detail_id', 'id');
    }

    public function payment(): HasMany
    {
        return $this->hasMany(Payment::class, 'fk_booking_detail_id', 'id');
    }

    public function reschedules(): HasMany
    {
        return $this->hasMany(BookingReschedule::class, 'fk_booking_detail_id', 'id')->latest();
    }

    public function cancellation(): HasOne
    {
        return $this->hasOne(BookingCancelled::class, 'fk_booking_detail_id', 'id');
    }
}
