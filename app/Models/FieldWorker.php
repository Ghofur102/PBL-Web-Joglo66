<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FieldWorker extends Model
{
    protected $connection = 'mysql_joglo66_app';

    protected $table = 'field_workers';

    protected $fillable = [
        'fk_field_id',
        'fk_user_id',
    ];

    public function field(): BelongsTo
    {
        return $this->belongsTo(Field::class, 'fk_field_id', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'fk_user_id', 'id');
    }
}
