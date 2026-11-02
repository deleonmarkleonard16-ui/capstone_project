@extends('layouts.app')
@section('content')
<h1 class="h3 mb-3">Document Exports</h1>
<p>Select a report and its filters to review the data before printing or downloading.</p>
@foreach($modules as $module => $types)
@php
    $selected = request('module') === $module ? request()->all() : [];
    $defaultType = in_array('summary', $types, true) ? 'summary' : (in_array('queue', $types, true) ? 'queue' : $types[0]);
@endphp
<div class="card mb-3" id="export-{{ $module }}"><div class="card-body">
    <h2 class="h5">{{ ucwords(str_replace('-', ' ', $module)) }}</h2>
    <form method="GET" action="{{ route(auth()->user()->role.'.'.$module.'.export') }}" class="row g-3">
        <div class="col-md-4"><label class="form-label">Document type</label><select name="type" class="form-select">@foreach($types as $type)<option value="{{ $type }}" @selected(($selected['type'] ?? $defaultType) === $type)>{{ ucwords(str_replace('-', ' ', $type)) }}</option>@endforeach</select></div>
        <input type="hidden" name="format" value="html"><input type="hidden" name="preview" value="1">
        @if($module !== 'analytics')
            <div class="col-md-4"><label class="form-label">Course</label><select name="course" class="form-select"><option value="">All courses</option>@foreach($courses as $code => $label)<option value="{{ $code }}" @selected((string) ($selected['course'] ?? '') === (string) $code)>{{ $label }}</option>@endforeach</select></div>
        @endif
        @if($module === 'admission')
            <div class="col-md-6"><label class="form-label">Admission cycle</label><select name="cycle_id" class="form-select"><option value="">Active cycle</option>@foreach($cycles as $cycle)<option value="{{ $cycle->id }}" @selected((string) ($selected['cycle_id'] ?? '') === (string) $cycle->id)>{{ $cycle->display_name }}</option>@endforeach</select></div>
            <div class="col-md-6"><label class="form-label">Session</label><select name="session_id" class="form-select"><option value="">All sessions</option>@foreach($sessions as $session)<option value="{{ $session->id }}" @selected((string) ($selected['session_id'] ?? '') === (string) $session->id)>{{ $session->cycle?->display_name }} / {{ $session->session_name ?? $session->session_label ?? $session->id }}</option>@endforeach</select></div>
            <div class="col-md-4"><label class="form-label">Status</label><select name="status" class="form-select"><option value="">All statuses</option>@foreach(['Pending', 'Qualified', 'Not Qualified'] as $status)<option @selected(($selected['status'] ?? '') === $status)>{{ $status }}</option>@endforeach</select></div>
        @elseif($module === 'analytics')
            <div class="col-12"><p class="text-muted small mb-0">Includes all guidance testing and document requests. Review the scope and totals in the preview before exporting.</p></div>
        @else
            <div class="col-md-6"><label class="form-label">Batch</label><select name="batch_id" class="form-select"><option value="">All batches</option>@foreach($batches as $batch)<option value="{{ $batch->getKey() }}" @selected((string) ($selected['batch_id'] ?? '') === (string) $batch->getKey())>{{ $batch->batch_name }}</option>@endforeach</select></div>
            <div class="col-md-3"><label class="form-label">Request ID (individual document)</label><input type="number" min="1" name="request_id" value="{{ $selected['request_id'] ?? '' }}" class="form-control"></div>
            <div class="col-md-3"><label class="form-label">Status</label><input name="status" value="{{ $selected['status'] ?? '' }}" class="form-control" placeholder="All statuses"></div>
        @endif
        <div class="col-12"><button type="submit" class="btn btn-primary">Preview report</button></div>
    </form>
</div></div>
@endforeach
@endsection
