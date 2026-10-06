<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected $guarded = ['id', 'active_marker'];

    public function run()
    {
        return $this->belongsTo(Run::class);
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function payment()
    {
        return $this->belongsTo(WalletTransfer::class, 'payment_transfer_id');
    }

    public function getCanCancelAttribute(): bool
    {
        return $this->status === 'booked' && $this->run->status === 'boarding';
    }
}
