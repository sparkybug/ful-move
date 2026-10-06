<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WalletEntry extends Model
{
    protected $guarded = ['id'];

    public function transfer()
    {
        return $this->belongsTo(WalletTransfer::class, 'transfer_id');
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
