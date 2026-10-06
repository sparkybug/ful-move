@extends('layouts.app')
@section('title', 'Bus approvals')
@section('content')
<div class="page-heading"><div><p class="eyebrow">THE CAMPUS FLEET</p><h1>Ready for the road.</h1><p>Review bus registrations before they become available for boarding.</p></div></div>
<section class="panel"><div class="table-scroll"><table><thead><tr><th>Bus</th><th>Driver</th><th>Ownership</th><th>Capacity</th><th>Status</th><th>Review</th></tr></thead><tbody>@foreach($buses as $bus)<tr><td><strong>{{ $bus->identifier }}</strong><small>{{ $bus->type }}</small></td><td>{{ $bus->driver->name }}<small>{{ $bus->driver->email }}</small></td><td>{{ $bus->ownership_details }}</td><td>{{ $bus->capacity }} seats</td><td><span class="badge {{ $bus->approval_status }}">{{ ucfirst($bus->approval_status) }}</span></td><td><form method="post" action="{{ route('admin.buses.approve', $bus) }}" class="actions">@csrf @method('PATCH')@if($bus->approval_status !== 'approved')<button class="btn btn-small btn-primary" name="approval_status" value="approved" type="submit">Approve</button>@endif @if($bus->approval_status !== 'rejected')<button class="btn btn-small btn-danger" name="approval_status" value="rejected" type="submit">Reject</button>@endif</form></td></tr>@endforeach</tbody></table></div>{{ $buses->links() }}</section>
@endsection

