@extends('layouts.app')

@section('content')
<div class="card page-card mb-3"><div class="card-body">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
        <div>
            <h1 class="h3 mb-1">{{ $document['title'] }}</h1>
            <p class="text-muted mb-0">Report preview · {{ ($document['reportType'] ?? null) === 'summary' ? 'Summary rows' : 'Record count' }}: {{ isset($document['records']) ? $document['records']->count() : count($document['rows']) }}</p>
        </div>
        <a class="btn btn-outline-secondary" href="{{ route(auth()->user()->role.'.exports.index', array_merge(request()->except(['format', 'preview', 'auto_print']), ['module' => $document['module'] ?? 'analytics'])) }}#export-{{ $document['module'] ?? 'analytics' }}">Change report / filters</a>
    </div>
    <dl class="row mt-3 mb-3">
        @foreach($document['metadata'] as $label => $value)
            <div class="col-md-6 mb-2"><dt class="small text-muted">{{ $label }}</dt><dd class="mb-0">{{ $value }}</dd></div>
        @endforeach
    </dl>
    <div class="d-flex flex-wrap gap-2" aria-label="Preview export actions">
        <a class="btn btn-outline-primary" href="{{ request()->fullUrlWithQuery(['format' => 'pdf', 'preview' => 0, 'auto_print' => 0]) }}#page=1&amp;zoom=page-width" target="_blank" rel="noopener">Print preview</a>
        @foreach(['pdf' => 'View / Print PDF', 'docx' => 'Download DOCX', 'csv' => 'Download CSV'] as $format => $label)
            <a class="btn {{ $format === 'pdf' ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ request()->fullUrlWithQuery(['format' => $format, 'preview' => 0, 'auto_print' => 0]) }}" @if($format === 'pdf') target="_blank" rel="noopener" @endif>{{ $label }}</a>
        @endforeach
    </div>
</div></div>
<p class="small text-muted">Review the data below. Choose Print preview, PDF, or DOCX to apply the official format. Print preview opens the paginated PDF in a new tab; use its print button to print.</p>
<div class="card"><div class="card-body" id="report-preview">
    @if(count($document['summary']))
        <dl class="row mb-3">
            @foreach($document['summary'] as $label => $value)
                <div class="col-sm-6 col-lg-3 mb-2"><dt class="small text-muted">{{ $label }}</dt><dd class="mb-0">{{ $value }}</dd></div>
            @endforeach
        </dl>
    @endif
    @if(count($document['columns']))
        <div class="table-responsive" tabindex="0" role="region" aria-label="Report data">
            <table class="table table-striped table-bordered align-middle mb-0">
                <thead><tr>@foreach($document['columns'] as $column)<th scope="col" class="text-nowrap">{{ $column }}</th>@endforeach</tr></thead>
                <tbody>@foreach($document['rows'] as $row)<tr>@foreach($row as $value)<td style="min-width:8rem; overflow-wrap:anywhere">{{ $value ?? '-' }}</td>@endforeach</tr>@endforeach</tbody>
            </table>
        </div>
    @endif
    @foreach($document['certificates'] as $certificate)
        <dl class="row border-bottom pb-3 mb-3">
            @foreach(['name' => 'Name', 'student_number' => 'Student number', 'course' => 'Course', 'purpose' => 'Purpose', 'statement' => 'Certification', 'issued_at' => 'Issued'] as $key => $label)
                <div class="col-md-6 mb-2"><dt class="small text-muted">{{ $label }}</dt><dd class="mb-0">{{ $certificate[$key] ?? '-' }}</dd></div>
            @endforeach
        </dl>
    @endforeach
</div></div>
@endsection
