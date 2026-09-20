@extends('layouts.app')
@section('content')
<h1 class="h3 mb-4">{{ $title }}s</h1>
@forelse($requests as $entry)
<article class="card page-card mb-3"><div class="card-body p-4">
<h2 class="h5">{{ $entry->first_name }} {{ $entry->middle_name }} {{ $entry->last_name }}</h2>
<p class="text-muted">{{ ucfirst($entry->student_status) }} · {{ $entry->student_number }} · {{ $entry->course }}</p>
<p>{{ $entry->email }} · {{ $entry->contact_number }}<br>Submitted {{ $entry->created_at->format('M d, Y g:i A') }}</p>
<p><strong>Purpose:</strong> {{ $entry->purpose }}</p>
@if($entry->tests)<p><strong>Tests:</strong> {{ implode(', ', $entry->tests) }}</p>@endif
@if($entry->copies)<p><strong>Copies:</strong> {{ $entry->copies }}</p>@endif
@if($entry->guidanceAppointments->isNotEmpty())
<span class="badge text-bg-primary mb-2">{{ $entry->reference }}</span>
@foreach($entry->guidanceAppointments as $appointment)<div class="mb-2">{{ $appointment->testLabel() }} — {{ $appointment->status }} <button type="button" class="btn btn-outline-primary btn-sm" data-guidance-review="{{ route(auth()->user()->role.'.guidance-appointments.review', $appointment) }}">Review Details</button></div>@endforeach
@elseif($entry->isPsychological())<a class="btn btn-primary" href="{{ route(auth()->user()->role.($entry->archived_at ? '.psychological.archive' : '.psychological.index'), ['q'=>$entry->reference]) }}">Review psychological request</a>@else
<p><strong>Reference:</strong> <code>{{ $entry->reference }}</code> &nbsp;|&nbsp; <strong>Status:</strong> <span class="badge bg-secondary">{{ ucfirst(str_replace('_',' ',$entry->status)) }}</span></p>
@if($entry->proof_path)
<p><a class="btn btn-sm btn-outline-primary" href="{{ route(auth()->user()->role.'.psychological.proof', $entry) }}">📥 Download paid/stamped stub for verification</a></p>
@else
<p class="text-muted"><small>No paid stub uploaded yet.</small></p>
@endif
@if($entry->scheduled_at)
<p><strong>Schedule:</strong> {{ $entry->scheduled_at->timezone('Asia/Manila')->format('M d, Y g:i A') }} (Philippine time) · <strong>Venue:</strong> {{ $entry->venue }}</p>
@endif
<form method="POST" action="{{ route(auth()->user()->role.'.requests.update', $entry) }}">@csrf @method('PATCH')
<div class="row g-3"><div class="col-md-3"><label for="status-{{ $entry->id }}" class="form-label">Status</label><select id="status-{{ $entry->id }}" class="form-select" name="status">@foreach(\App\Models\ServiceRequest::STATUSES as $status)<option @selected($entry->status === $status) value="{{ $status }}">{{ ucfirst(str_replace('_', ' ', $status)) }}</option>@endforeach</select></div>
<div class="col-md-9"><label class="form-label" for="message-{{ $entry->id }}">Message visible to requester</label><textarea id="message-{{ $entry->id }}" class="form-control" name="staff_message" maxlength="2000">{{ $entry->staff_message }}</textarea></div>@if($entry->service === 'testing')<div class="col-md-6"><label class="form-label" for="time-{{ $entry->id }}">Test schedule (Philippine time; required when scheduling)</label><input id="time-{{ $entry->id }}" name="scheduled_at" type="datetime-local" class="form-control" value="{{ $entry->scheduled_at?->timezone('Asia/Manila')->format('Y-m-d\TH:i') }}"></div><div class="col-md-6"><label class="form-label" for="venue-{{ $entry->id }}">Venue</label><input id="venue-{{ $entry->id }}" name="venue" class="form-control" value="{{ $entry->venue }}"></div>@endif
<div><button class="btn btn-primary">Update request</button></div></div>
</form>@endif</div></article>
@empty<div class="card page-card"><div class="card-body">No requests yet.</div></div>@endforelse
<div class="mt-3">{{ $requests->links('pagination::bootstrap-5') }}</div>
@include('guidance.review-modal')
@endsection
