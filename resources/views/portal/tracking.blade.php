@extends('portal.layout')
@section('content')
@include('portal.stub', ['tracking' => true])
<section class="card"><h1>Request status</h1><p>{{ \App\Models\ServiceRequest::SERVICES[$entry->service] }}</p><p>Reference: <strong>{{ $entry->reference }}</strong></p><p>Status: <strong>{{ ucfirst($entry->status) }}</strong></p><p class="muted">Last updated {{ $entry->updated_at->format('M d, Y g:i A') }}</p>@if($entry->staff_message)<h2>Message from the guidance office</h2><p style="white-space:pre-wrap">{{ $entry->staff_message }}</p>@endif<a class="button" href="{{ route('portal.index', ['service' => $entry->service]) }}">Back to services</a></section>
@endsection
