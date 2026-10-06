<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#183d32">
    <title>@yield('title', 'Campus shuttle') · FUL Move</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
<a href="#main" class="skip-link">Skip to content</a>
<div class="app-shell">
    <aside class="sidebar">
        <a class="brand" href="{{ route('board') }}"><span class="brand-mark"><x-icon name="bus" /></span><span>FUL<span class="brand-light">move</span><small>CAMPUS SHUTTLE</small></span></a>
        <details class="mobile-nav"><summary>Menu <x-icon name="menu" /></summary><nav>@include('partials.navigation')</nav></details>
        <nav class="desktop-nav">@include('partials.navigation')</nav>
        <div class="sidebar-bottom">
            <div class="campus-note"><span class="eyebrow">MADE FOR CAMPUS LIFE</span><p>Less waiting.<br>More moving.</p><span class="tiny">Federal University Lokoja</span></div>
            @auth
                <div class="profile"><span class="avatar">{{ mb_substr(auth()->user()->name, 0, 1) }}</span><div><strong>{{ auth()->user()->name }}</strong><small>{{ ucfirst(auth()->user()->role) }} account</small></div></div>
                <form action="{{ route('logout') }}" method="post">@csrf<button class="sign-out" type="submit"><x-icon name="logout" /> Sign out</button></form>
            @else
                <a class="btn btn-primary full" href="{{ route('login') }}">Sign in <x-icon name="arrow" /></a>
                <p class="tiny text-center">New here? <a href="{{ route('register') }}">Create an account</a></p>
            @endauth
        </div>
    </aside>
    <div class="workspace">
        <header class="topbar"><div><span class="topbar-label">FEDERAL UNIVERSITY LOKOJA</span><span class="topbar-divider">/</span><span>@yield('title', 'Campus shuttle')</span></div><div class="topbar-right"><span class="dot"></span> Campus transport <span class="date">{{ now()->format('D, d M') }}</span></div></header>
        <main id="main">
            @if(session('success'))<div class="notice success" role="status"><x-icon name="check" /><span>{{ session('success') }}</span></div>@endif
            @if($errors->any())<div class="notice error" role="alert"><x-icon name="info" /><div><strong>Please check this</strong>@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div></div>@endif
            @yield('content')
        </main>
        <footer><span>FUL Move · A simpler way between campuses.</span><span>Coursework prototype · Internal transport credits only</span></footer>
    </div>
</div>
<dialog id="action-confirm"><h2>Confirm this change</h2><p id="action-confirm-message"></p><div class="actions"><button class="btn btn-light" id="action-confirm-back" type="button">Go back</button><button class="btn btn-primary" id="action-confirm-submit" type="button">Yes, continue</button></div></dialog>
</body>
</html>


