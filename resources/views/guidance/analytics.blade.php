@extends('layouts.app')
@section('content')
<div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
    <div>
        <h1 class="h3 mb-1">{{ $module['label'] }} Analytics</h1>
        <p class="text-muted mb-0">Completed {{ strtolower($module['label']) }} assessments, including archived records. Incomplete instruments are excluded from scored counts.</p>
    </div>
</div>
@include('guidance.partials.tabs')
@include('staff.partials.course-filter')
<section data-live-queue data-json="true"><p data-live-status class="text-muted small" role="status">Live updates every 5 seconds.</p><div data-live-results>@include('guidance.analytics-data')</div></section>
@endsection
@push('scripts')<script src="{{ asset('js/guidance-queue.js') }}" defer></script>@endpush
