<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BookingDetail extends Model
{
    use HasFactory;
    protected $connection = 'mysql_joglo66_app';

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
}
