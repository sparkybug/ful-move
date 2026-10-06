@props(['name' => 'arrow'])
<svg {{ $attributes->merge(['class' => 'icon']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
@switch($name)
@case('bus')<rect x="4" y="3" width="16" height="16" rx="4"/><path d="M4 11h16M12 3v8M7 19v2m10-2v2"/><path d="M7 15h1m8 0h1"/>@break
@case('grid')<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>@break
@case('ticket')<path d="M3 7a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v3a2 2 0 0 0 0 4v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-3a2 2 0 0 0 0-4zM15 5v3m0 3v2m0 3v3"/>@break
@case('wallet')<path d="M20 8V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h15V8H5a3 3 0 0 1 0-5"/><path d="M20 12h-5v5h5m-3-2.5h.01"/>@break
@case('users')<circle cx="9" cy="7" r="3"/><path d="M3 21v-3a6 6 0 0 1 12 0v3m1-17a3 3 0 0 1 0 6m3 11v-3a6 6 0 0 0-2-4"/>@break
@case('settings')<path d="M4 7h16M4 17h16"/><circle cx="9" cy="7" r="3" fill="currentColor" stroke="none"/><circle cx="16" cy="17" r="3" fill="currentColor" stroke="none"/>@break
@case('chart')<path d="M4 3v18h17M8 16v-5m5 5V6m5 10v-8"/>@break
@case('clock')<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>@break
@case('pin')<path d="M19 10c0 5-7 11-7 11S5 15 5 10a7 7 0 0 1 14 0Z"/><circle cx="12" cy="10" r="2.5"/>@break
@case('seat')<path d="M7 3v10h10v5H6a3 3 0 0 1-3-3V7m4 11v3m10-3v3M10 6h6v4"/>@break
@case('check')<path d="m5 12 4 4L19 6"/>@break
@case('info')<circle cx="12" cy="12" r="9"/><path d="M12 11v6m0-10h.01"/>@break
@case('logout')<path d="M9 4H4v16h5m5-12 4 4-4 4m-5-4h12"/>@break
@case('menu')<path d="M4 6h16M4 12h16M4 18h16"/>@break
@default<path d="M4 12h16m-6-6 6 6-6 6"/>
@endswitch
</svg>

