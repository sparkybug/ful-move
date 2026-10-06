@extends('layouts.app')
@section('title', 'My tickets')
@section('content')
<div class="page-heading"><div><p class="eyebrow">YOUR JOURNEYS</p><h1>One place for every ride.</h1><p>Your reserved seats and past campus journeys.</p></div><a class="btn btn-primary" href="{{ route('board') }}">Find a bus <x-icon name="arrow" /></a></div>
<div class="ticket-grid">@forelse($bookings as $booking)<a class="panel ticket-preview" href="{{ route('tickets.show', $booking) }}"><div class="section-heading"><span class="bus-symbol"><x-icon name="ticket" /></span><span class="badge {{ $booking->status }}">{{ ucfirst($booking->status) }}</span></div><p class="eyebrow">{{ $booking->run->bus->identifier }}</p><h2>{{ $booking->run->origin->name }} <span class="muted">→</span><br>{{ $booking->run->destination->name }}</h2><div class="ticket-meta"><span>{{ $booking->created_at->format('d M Y') }}</span><strong>{{ \App\Support\Money::format($booking->fare_kobo) }}</strong></div><div class="ticket-preview-footer"><code>{{ $booking->booking_code }}</code><span>View ticket ↗</span></div></a>@empty<div class="empty-state"><span class="empty-icon"><x-icon name="ticket" /></span><h3>Your first journey is waiting.</h3><p>Book a boarding bus to see your ticket here.</p><a class="btn btn-primary" href="{{ route('board') }}">Find a bus</a></div>@endforelse</div>{{ $bookings->links() }}
@endsection

