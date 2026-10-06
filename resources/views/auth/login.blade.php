@extends('layouts.app')
@section('title', 'Sign in')
@section('content')
<div class="auth-wrap"><div class="auth-intro"><p class="eyebrow">WELCOME BACK</p><h1>Your campus.<br>Your next ride.</h1><p>Sign in to book your seat and keep moving.</p>@include('partials.bus-art')</div><section class="panel auth-panel"><h2>Good to see you.</h2><p>Sign in to your FUL Move account.</p><form method="post" action="{{ route('login') }}" class="form-stack">@csrf<label>Email address<input type="email" name="email" value="{{ old('email') }}" autocomplete="email" required autofocus></label><label>Password<input type="password" name="password" autocomplete="current-password" required></label><label class="checkbox"><input type="checkbox" name="remember" value="1"><span>Keep me signed in</span></label><button class="btn btn-primary full" type="submit">Sign in <x-icon name="arrow" /></button></form><p class="tiny text-center">New to FUL Move? <a href="{{ route('register') }}">Create an account</a></p></section></div>
@endsection

