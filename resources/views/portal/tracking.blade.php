@extends('portal.layout')
@section('content')
@include('portal.stub', ['tracking' => true])
@php($statusLabels = [
    'pending' => 'Awaiting payment / paid stub',
    'approved' => 'Approved — Paid/stamped stub required',
    'proof_review' => 'Receipt Uploaded / Pending Verification',
    'processing' => 'Payment verified — processing document',
    'ready' => 'Ready for pick-up at Guidance Office',
    'scheduled' => 'Verified — scheduled for testing',
    'completed' => 'Completed',
    'declined' => 'Declined',
    'cancelled' => 'Cancelled',
    'void' => 'Void (5-day window expired)',
])
@php($displayStatus = $statusLabels[$entry->status] ?? ucfirst($entry->status))
<section class="card">
    <h1>Request status</h1>
    <p>{{ \App\Models\ServiceRequest::SERVICES[$entry->service] ?? 'Service Request' }}</p>
    <p>Reference: <strong>{{ $entry->reference }}</strong></p>
    <p>Status: <strong class="tracking-status-badge" style="color:{{ in_array($entry->status, ['ready','completed']) ? '#16a34a' : '#2563eb' }};">{{ $displayStatus }}</strong></p>
    <p class="muted">Last updated {{ $entry->updated_at->format('M d, Y g:i A') }}</p>
    @if($entry->staff_message)
        <h2>Message from the guidance office</h2>
        <p style="white-space:pre-wrap">{{ $entry->staff_message }}</p>
    @endif
    <a class="button" href="{{ route('portal.index', ['service' => $entry->service]) }}">Back to services</a>
</section>
@endsection
