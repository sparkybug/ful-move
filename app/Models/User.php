<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'role', 'student_identifier'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed'];
    }

    protected static function booted(): void
    {
        static::created(function (User $user) {
            if (in_array($user->role, ['student', 'driver'], true)) {
                $user->wallet()->create(['balance_kobo' => 0, 'currency' => 'NGN']);
            }
        });
    }

    public function wallet()
    {
        return $this->hasOne(Wallet::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class, 'student_id');
    }

    public function buses()
    {
        return $this->hasMany(Bus::class, 'driver_id');
    }
}
