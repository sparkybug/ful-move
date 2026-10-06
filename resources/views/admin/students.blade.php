@extends('layouts.app')
@section('title', 'Student credits')
@section('content')
<div class="page-heading"><div><p class="eyebrow">HELP STUDENTS GET THERE</p><h1>A wallet ready for the journey.</h1><p>Issue internal transport credits with a clear reason and audit record.</p></div></div>
<form method="get" class="filter-bar"><label for="search">Find a student</label><input id="search" name="search" value="{{ request('search') }}" placeholder="Name, email or matriculation number" maxlength="100"><button class="btn btn-primary btn-small" type="submit">Search</button></form>
<div class="notice info"><x-icon name="info" /><span>These are demonstration transport credits, with no external monetary value.</span></div>
<div class="credit-grid">@forelse($students as $student)<section class="panel"><div class="section-heading"><div><h3>{{ $student->name }}</h3><p>{{ $student->student_identifier }}<br>{{ $student->email }}</p></div><strong>{{ \App\Support\Money::format($student->wallet->balance_kobo) }}</strong></div><form action="{{ route('admin.students.credit', $student) }}" method="post" class="form-stack">@csrf<input type="hidden" name="operation_key" value="{{ (string) Str::uuid() }}"><label>Amount (NGN)<input name="amount" inputmode="decimal" placeholder="1000.00" required></label><label>Reason<input name="reason" maxlength="255" placeholder="e.g. Coursework transport credit" required></label><button class="btn btn-primary" type="submit">Issue transport credit <x-icon name="arrow" /></button></form></section>@empty<div class="empty-state"><h3>No matching students.</h3><p>Try another name or matriculation number.</p></div>@endforelse</div>{{ $students->links() }}
@endsection

