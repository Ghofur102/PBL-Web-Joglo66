<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingAttribute extends CoreDmlModel
{
    protected $table = 'booking_attributes';

    protected $fillable = [
        'fk_booking_detail_id',
        'fk_attribute_id',
        'quantity',
        'price',
        'total',
        'reason',
        'transaction_date',
        'status',
        'customer_name',
        'customer_phone',
        'duration_hours',
    ];

    public function bookingDetail(): BelongsTo
    {
        return $this->belongsTo(BookingDetail::class, 'fk_booking_detail_id', 'id');
    }

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class, 'fk_attribute_id', 'id');
    }
}
