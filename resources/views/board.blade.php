@extends('layouts.app')
@section('title', 'Available buses')
@section('content')
<div class="page-heading"><div><p class="eyebrow">GOOD TO GO, {{ auth()->check() ? strtoupper(explode(' ', auth()->user()->name)[0]) : 'LOKOJA' }}</p><h1>Your next ride starts here.</h1><p>Find a boarding bus, reserve a seat, and get on with your day.</p></div><span class="pill quiet"><x-icon name="pin" /> Campus to campus</span></div>
<section class="hero"><div class="hero-copy"><span class="hero-tag"><span class="dot"></span> LESS WAITING. MORE MOVING.</span><h2>Campus journeys.<br>A little easier.</h2><p>Know which bus is boarding before you leave.<br>Your seat is just a few taps away.</p><a href="#departures" class="hero-link">Find your ride <x-icon name="arrow" /></a></div>@include('partials.bus-art')</section>
<div class="board-grid">
<section id="departures">
<div class="section-heading"><div><h2>Boarding now <span class="live-dot"></span></h2><p>Live availability, straight from the terminal.</p></div><span class="refresh-note" id="refresh-status" role="status">Updates every 12 seconds</span></div>
<form method="get" action="{{ route('board') }}" class="filter-bar"><label for="origin"><x-icon name="pin" /> Leaving from</label><select name="origin" id="origin"><option value="">All terminals</option>@foreach($terminals as $terminal)<option value="{{ $terminal->id }}" @selected(request('origin') == $terminal->id)>{{ $terminal->name }}</option>@endforeach</select><button class="btn btn-small btn-light" type="submit">Find buses</button></form>
<div id="bus-list" data-poll-url="{{ route('board', ['fragment' => 1, 'origin' => request('origin')]) }}">@include('partials.bus-list')</div>
</section>
<aside class="board-aside">
@auth
@if(auth()->user()->wallet)
<div class="wallet-card"><span class="eyebrow"><x-icon name="wallet" /> YOUR TRANSPORT WALLET</span><strong>{{ \App\Support\Money::format(auth()->user()->wallet->balance_kobo) }}</strong><p>Internal campus transport credits</p><a href="{{ route('wallet') }}">View wallet & history <x-icon name="arrow" /></a></div>
@endif
@endauth
<div class="panel how-to"><span class="eyebrow">A SMOOTHER CAMPUS COMMUTE</span><h3>From here to there,<br>in three simple steps.</h3>@foreach([['Find your bus','Choose a direction and check the seats left.'],['Make it your seat','Book with your transport wallet. One booking, one seat.'],['Meet at the terminal','Show your booking code to the driver and hop on.']] as $step)<div class="step"><span>{{ $loop->iteration }}</span><div><strong>{{ $step[0] }}</strong><p>{{ $step[1] }}</p></div></div>@endforeach</div>
<div class="tip"><x-icon name="info" /><div><strong>A small heads-up</strong><p>Departure times are estimates from your driver. Arrive early: boarding may close before the estimate.</p></div></div>
</aside>
</div>
@endsection

