<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Run extends Model
{
    protected $guarded = ['id', 'active_driver_id', 'active_bus_id'];

    protected function casts(): array
    {
        return ['estimated_departure_at' => 'datetime', 'estimate_updated_at' => 'datetime', 'fare_snapshot_kobo' => 'integer'];
    }

    public function bus()
    {
        return $this->belongsTo(Bus::class);
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function origin()
    {
        return $this->belongsTo(Terminal::class, 'origin_id');
    }

    public function destination()
    {
        return $this->belongsTo(Terminal::class, 'destination_id');
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function activeBookings()
    {
        return $this->bookings()->whereIn('status', ['booked', 'boarded']);
    }

    public function getSeatsLeftAttribute(): int
    {
        return max(0, $this->capacity_snapshot - ($this->active_bookings_count ?? $this->activeBookings()->count()) - $this->walk_in_count);
    }

    public function getEstimateLabelAttribute(): string
    {
        return $this->status === 'boarding' && $this->estimated_departure_at->isPast()
            ? 'Departure time pending update'
            : $this->estimated_departure_at->format('g:i A');
    }
}
