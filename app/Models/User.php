<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'role',
        'email_verified_at',
    ];

    protected $connection = 'mysql_joglo66_app';

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
        ];
    }

    public function hasRestrictedFieldAccess(): bool
    {
        return in_array($this->role, ['worker', 'treasurer'], true);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'fk_user_id', 'id');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'fk_user_id', 'id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(Log::class, 'fk_user_id', 'id');
    }

    public function fieldWorker(): HasMany
    {
        return $this->hasMany(FieldWorker::class, 'fk_user_id', 'id');
    }

    public function fieldClosure(): HasMany
    {
        return $this->hasMany(FieldClosure::class, 'fk_user_id', 'id');
    }

    public function emailVerificationTokens(): HasMany
    {
        return $this->hasMany(EmailVerificationToken::class, 'user_id', 'id');
    }

    public function employee()
    {
        return $this->hasOne(Employee::class, 'fk_user_id', 'id');
    }
}
