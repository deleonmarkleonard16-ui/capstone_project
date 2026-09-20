@extends('layouts.app')
@section('content')
<div class="mb-3">
    <h1 class="h3">{{ $archived ? 'Archived Requests' : 'Guidance Request Queue' }}</h1>
    <p class="text-muted">Individual Request Queue: Review receipts, inspect student profiles, and generate assessment passes. Updates automatically every 5 seconds.</p>
</div>
@include('guidance.partials.tabs')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
@include('guidance.security-feed')
<section data-live-queue data-json="true">
<form method="get" data-live-filters class="row g-3 mb-3">
    <div class="col-md-5"><label for="queue-search" class="form-label">Name, student ID or tracking reference</label><input id="queue-search" class="form-control" name="q" value="{{ $filters['q'] ?? '' }}" maxlength="100"></div>
    <div class="col-md-3"><label for="queue-status" class="form-label">Status</label><select id="queue-status" class="form-select" name="status"><option value="">All statuses</option>@foreach(\App\Models\GuidanceAppointment::STATUSES as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $status }}</option>@endforeach</select></div>
    <div class="col-md-3"><label for="queue-test" class="form-label">Assessment</label><select id="queue-test" class="form-select" name="test_type"><option value="">All assessments</option>@foreach(\App\Services\GuidanceCategories::LABELS as $key => $label)<option value="{{ $key }}" @selected(($filters['test_type'] ?? '') === $key)>{{ $label }}</option>@endforeach</select></div>
    <div class="col-md-4"><label for="queue-course" class="form-label">Course / program</label><select id="queue-course" class="form-select" name="course"><option value="">All programs</option>@foreach(\App\Support\CourseCatalog::allOptions() as $code => $label)<option value="{{ $code }}" @selected(($filters['course'] ?? '') === $code)>{{ $label }} ({{ $code }})</option>@endforeach</select></div>
    <div class="col-md-2"><label for="queue-from" class="form-label">From</label><input id="queue-from" class="form-control" type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}"></div>
    <div class="col-md-2"><label for="queue-to" class="form-label">To</label><input id="queue-to" class="form-control" type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}"></div>
    <div class="col-md-1 d-flex align-items-end"><button class="btn btn-primary">Search</button></div>
</form>
<p data-live-status class="small text-muted" role="status">Live updates every 5 seconds.</p>
<div data-live-results>@include('guidance.queue')</div>
</section>
@include('guidance.receipt-modal')
@include('guidance.review-modal')
@endsection
@push('scripts')<script src="{{ asset('js/guidance-queue.js') }}" defer></script>@endpush
