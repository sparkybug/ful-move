<span class="nav-label">YOUR CAMPUS, CONNECTED</span>
<a class="nav-link {{ request()->routeIs('board', 'runs.*') ? 'active' : '' }}" href="{{ route('board') }}"><x-icon name="grid" /> Available buses <span class="nav-arrow">↗</span></a>
@auth
@if(auth()->user()->role === 'student')
<a class="nav-link {{ request()->routeIs('bookings', 'tickets.*') ? 'active' : '' }}" href="{{ route('bookings') }}"><x-icon name="ticket" /> My tickets</a>
@endif
@if(auth()->user()->role === 'driver')
<a class="nav-link {{ request()->routeIs('driver.*') ? 'active' : '' }}" href="{{ route('driver.index') }}"><x-icon name="bus" /> Driver dashboard</a>
@endif
@if(in_array(auth()->user()->role, ['student', 'driver']))
<a class="nav-link {{ request()->routeIs('wallet') ? 'active' : '' }}" href="{{ route('wallet') }}"><x-icon name="wallet" /> My wallet</a>
@endif
@if(auth()->user()->role === 'admin')
<span class="nav-label spaced">ADMINISTRATION</span>
@foreach([['admin.index','grid','Overview'], ['admin.buses','bus','Bus approvals'], ['admin.students','users','Student credits'], ['admin.settings','settings','Terminals & fares'], ['admin.reports','chart','Ledger & reports']] as [$route,$icon,$label])
<a class="nav-link {{ request()->routeIs($route) ? 'active' : '' }}" href="{{ route($route) }}"><x-icon :name="$icon" />{{ $label }}</a>
@endforeach
@endif
@endauth


<div class="mobile-account">
@auth
<p class="tiny">{{ auth()->user()->name }} · {{ ucfirst(auth()->user()->role) }}</p>
<form method="post" action="{{ route('logout') }}">@csrf<button class="btn btn-light full" type="submit">Sign out</button></form>
@else
<a class="btn btn-primary full" href="{{ route('login') }}">Sign in</a>
<a class="text-link" href="{{ route('register') }}">Create an account</a>
@endauth
</div>

