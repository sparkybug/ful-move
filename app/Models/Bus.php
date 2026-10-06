<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Bus extends Model
{
    public const TYPES = ['Coach', 'Minibus', 'Shuttle', 'Van'];

    protected $guarded = ['id'];

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function runs()
    {
        return $this->hasMany(Run::class);
    }
}
