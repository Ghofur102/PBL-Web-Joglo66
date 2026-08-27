<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BookingAttribute extends Model
{
    use HasFactory;
    protected $connection = 'mysql_joglo66_app';

    protected $table = 'booking_attributes';

    protected $fillable = [
        'fk_booking_detail_id',
        'fk_attribute_id',
        'quantity',
        'price',
        'total',
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
