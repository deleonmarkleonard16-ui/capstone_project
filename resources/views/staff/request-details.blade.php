@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-start gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">Request Details</h1>
        <p class="text-muted mb-0">{{ $entry->reference }}</p>
    </div>
    <a class="btn btn-outline-secondary" href="{{ route(auth()->user()->role.'.archive') }}">Back to Archives</a>
</div>
<div class="card page-card"><div class="card-body p-4">
    @if(count($modules) > 1)
        <nav class="nav nav-pills gap-2 mb-4" aria-label="Requested assessments">
            @foreach($modules as $module)
                <a class="nav-link {{ $moduleType === $module ? 'active' : '' }}"
                   @if($moduleType === $module) aria-current="page" @endif
                   href="{{ route(auth()->user()->role.'.requests.details', ['serviceRequest' => $entry, 'module_type' => $module]) }}">{{ \App\Services\GuidanceCategories::LABELS[$module] }}</a>
            @endforeach
        </nav>
    @endif
    @include('staff.partials.notification-review', ['detailsPage' => true])
</div></div>
@include('guidance.receipt-modal')
@endsection
