@extends('layouts.app')
@section('title', 'Ride details')
@section('content')
<a class="back-link" href="{{ route('board') }}">← Back to available buses</a>
<div class="page-heading"><div><p class="eyebrow">YOUR NEXT CAMPUS JOURNEY</p><h1>A seat with your name on it.</h1><p>Review your ride before reserving a seat.</p></div></div>
<div class="two-column">
<section class="panel ride-details"><div class="section-heading"><div class="bus-identity"><span class="bus-symbol"><x-icon name="bus" /></span><div><h2>{{ $run->bus->identifier }}</h2><p>{{ $run->bus->type }} · {{ $run->capacity_snapshot }} seats</p></div></div><span class="badge {{ $run->status }}">{{ ucfirst($run->status) }}</span></div><div class="journey-stops"><div><span class="stop-dot"></span><p>LEAVING FROM<strong>{{ $run->origin->name }}</strong></p></div><div><span class="stop-dot filled"></span><p>HEADING TO<strong>{{ $run->destination->name }}</strong></p></div></div><div class="stats-grid two"><div class="stat"><span>Estimated departure</span><strong class="small-value">{{ $run->estimate_label }}</strong><small>Updated {{ $run->estimate_updated_at->format('d M, g:i A') }} WAT</small></div><div class="stat"><span>Seats available</span><strong>{{ $run->seats_left }} <small>/ {{ $run->capacity_snapshot }}</small></strong><small>One booking reserves one seat.</small></div></div><div class="tip"><x-icon name="info" /><p>Your driver may close boarding early. Meet at the terminal and show your booking code before departure.</p></div></section>
<section class="panel booking-panel"><p class="eyebrow">YOUR BOOKING</p><h2>One seat. Ready to go.</h2><div class="price-row"><span>Transport fare</span><strong>{{ \App\Support\Money::format($run->fare_snapshot_kobo) }}</strong></div>
@if($run->status !== 'boarding' || $run->seats_left === 0)
<div class="notice error">This bus {{ $run->status !== 'boarding' ? 'is no longer boarding' : 'is full' }}.</div><a class="btn btn-primary full" href="{{ route('board') }}">Find another bus</a>
@elseif(!auth()->check())
<p>Sign in to reserve this seat with your transport credits.</p><a class="btn btn-primary full" href="{{ route('login') }}">Sign in to book <x-icon name="arrow" /></a>
@elseif(auth()->user()->role === 'student')
<div class="price-row"><span>Your wallet</span><strong>{{ \App\Support\Money::format(auth()->user()->wallet->balance_kobo) }}</strong></div><div class="price-row balance-row"><span>Balance after booking</span><strong>{{ auth()->user()->wallet->balance_kobo >= $run->fare_snapshot_kobo ? \App\Support\Money::format(auth()->user()->wallet->balance_kobo - $run->fare_snapshot_kobo) : 'Insufficient credit' }}</strong></div>
<form action="{{ route('book', $run) }}" method="post" data-book-form data-availability="{{ route('runs.availability', $run) }}">@csrf<input type="hidden" name="operation_key" value="{{ old('operation_key', (string) Str::uuid()) }}"><label class="checkbox"><input type="checkbox" name="confirmed" value="1" required><span>I confirm one seat at {{ \App\Support\Money::format($run->fare_snapshot_kobo) }} from my transport wallet.</span></label><button class="btn btn-primary full" type="submit" @disabled(auth()->user()->wallet->balance_kobo < $run->fare_snapshot_kobo)>Reserve my seat <x-icon name="arrow" /></button><p class="form-feedback" role="status"></p></form>
@else
<div class="tip"><x-icon name="info" /><p>Seat reservations are available to student accounts.</p></div>
@endif
<p class="tiny">You can cancel for a full credit refund while the bus is boarding, before the driver checks you in.</p>
</section></div>
<dialog id="booking-confirm"><h2>Ready to reserve?</h2><p id="booking-summary"></p><p class="muted">Your seat and wallet will be checked again when you confirm.</p><div class="actions"><button class="btn btn-light" data-close-dialog>Go back</button><button class="btn btn-primary" id="confirm-booking">Confirm booking</button></div></dialog>
@endsection

