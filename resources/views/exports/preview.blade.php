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
        <button class="btn btn-outline-primary" type="button" onclick="document.getElementById('report-preview').contentWindow.focus(); document.getElementById('report-preview').contentWindow.print();">Print preview</button>
        @foreach(['pdf' => 'View / Print PDF', 'docx' => 'Download DOCX', 'csv' => 'Download CSV'] as $format => $label)
            <a class="btn {{ $format === 'pdf' ? 'btn-primary' : 'btn-outline-secondary' }}" href="{{ request()->fullUrlWithQuery(['format' => $format, 'preview' => 0, 'auto_print' => 0]) }}" @if($format === 'pdf') target="_blank" rel="noopener" @endif>{{ $label }}</a>
        @endforeach
    </div>
</div></div>
<p class="small text-muted">The preview uses the report's paper width. Scroll horizontally on smaller screens to inspect every column.</p>
<div class="border rounded bg-white" style="overflow:auto">
    <iframe id="report-preview" title="{{ $document['title'] }} preview" class="border-0 bg-white" style="display:block; width:{{ $document['orientation'] === 'portrait' ? '210mm' : '297mm' }}; min-width:100%; height:80vh" sandbox="allow-same-origin allow-scripts allow-modals" srcdoc="{{ $previewHtml }}"></iframe>
</div>
@endsection
