<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingCancelled extends CoreDmlModel
{
    protected $table = 'booking_cancelled';

    protected $fillable = [
        'fk_booking_detail_id',
        'fk_field_closure_id',
        'cancle_date',
        'reason',
        'status_refund',
        'approval_status',
        'sender_by',
        'rejection_reason',
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
