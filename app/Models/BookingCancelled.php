<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingCancelled extends CoreDmlModel
{
    protected $table = 'booking_cancelled';

    protected $fillable = [
        'fk_booking_detail_id', 'fk_field_closure_id', 'reason', 'cancle_date', 'status_refund', 'reason'
    ];

    public function bookingDetail(): BelongsTo
    {
        return $this->belongsTo(BookingDetail::class, 'fk_booking_detail_id', 'id');
    }

    public function fieldClosure(): BelongsTo
    {
        return $this->belongsTo(FieldClosure::class, 'fk_field_closure_id', 'id');
    }
}
