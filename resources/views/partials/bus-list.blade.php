@forelse($runs as $run)
<article class="bus-card">
<div class="bus-card-top"><div class="bus-identity"><span class="bus-symbol"><x-icon name="bus" /></span><div><h3>{{ $run->bus->identifier }}</h3><p>{{ $run->bus->type }} <span>·</span> {{ $run->capacity_snapshot }} seats</p></div></div><span class="badge {{ $run->seats_left ? 'boarding' : 'pending' }}"><span class="dot"></span>{{ $run->seats_left ? 'Boarding' : 'Full' }}</span></div>
<div class="route-line"><span>{{ $run->origin->name }}</span><span class="route-connector"><i></i><b></b><x-icon name="arrow" /></span><span>{{ $run->destination->name }}</span></div>
<div class="bus-facts"><div><span class="fact-label"><x-icon name="clock" /> EST. DEPARTURE</span><strong class="{{ $run->estimated_departure_at->isPast() ? 'pending-time' : '' }}">{{ $run->estimate_label }}</strong><small>Updated {{ $run->estimate_updated_at->format('g:i A') }}</small></div><div><span class="fact-label"><x-icon name="seat" /> SEATS LEFT</span><strong>{{ $run->seats_left }} <small>/ {{ $run->capacity_snapshot }}</small></strong><span class="seat-meter"><i style="width: {{ ($run->seats_left / max(1, $run->capacity_snapshot)) * 100 }}%"></i></span></div><div class="fare-fact"><span class="fact-label">PER SEAT</span><strong>{{ \App\Support\Money::format($run->fare_snapshot_kobo) }}</strong><a class="btn btn-primary btn-small" href="{{ route('runs.show', $run) }}">View ride <x-icon name="arrow" /></a></div></div>
</article>
@empty
<div class="empty-state"><span class="empty-icon"><x-icon name="bus" /></span><h3>No buses boarding just yet.</h3><p>When a driver opens boarding at your terminal, their bus will appear here. This board refreshes automatically.</p><a href="{{ route('board') }}" class="text-link">Check all terminals <x-icon name="arrow" /></a></div>
@endforelse

