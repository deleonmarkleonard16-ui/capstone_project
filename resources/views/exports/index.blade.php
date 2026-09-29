@extends('layouts.app')
@section('content')
<h1 class="h3 mb-3">Document Exports</h1>
<p>Select a report and its filters to download or preview an official document.</p>
@foreach($modules as $module => $types)
<div class="card mb-3"><div class="card-body">
    <h2 class="h5">{{ ucwords(str_replace('-', ' ', $module)) }}</h2>
    <form method="GET" action="{{ route(auth()->user()->role.'.'.$module.'.export') }}" class="row g-3">
        <div class="col-md-4"><label class="form-label">Document type</label><select name="type" class="form-select">@foreach($types as $type)<option value="{{ $type }}">{{ ucwords(str_replace('-', ' ', $type)) }}</option>@endforeach</select></div>
        <div class="col-md-4"><label class="form-label">Format</label><select name="format" class="form-select"><option value="html">Print-friendly preview</option><option value="pdf">PDF</option><option value="docx">DOCX</option><option value="csv">CSV</option></select></div>
        @if($module !== 'analytics')
            <div class="col-md-4"><label class="form-label">Course</label><select name="course" class="form-select"><option value="">All courses</option>@foreach($courses as $code => $label)<option value="{{ $code }}">{{ $label }}</option>@endforeach</select></div>
        @endif
        @if($module === 'admission')
            <div class="col-md-6"><label class="form-label">Admission cycle</label><select name="cycle_id" class="form-select"><option value="">Active cycle</option>@foreach($cycles as $cycle)<option value="{{ $cycle->id }}">{{ $cycle->display_name }}</option>@endforeach</select></div>
            <div class="col-md-6"><label class="form-label">Session</label><select name="session_id" class="form-select"><option value="">All sessions</option>@foreach($sessions as $session)<option value="{{ $session->id }}">{{ $session->cycle?->display_name }} / {{ $session->session_name ?? $session->session_label ?? $session->id }}</option>@endforeach</select></div>
            <div class="col-md-4"><label class="form-label">Status</label><select name="status" class="form-select"><option value="">All statuses</option>@foreach(['Pending', 'Qualified', 'Not Qualified'] as $status)<option>{{ $status }}</option>@endforeach</select></div>
        @elseif($module === 'analytics')
            @if(auth()->user()->role === 'admin')<div class="col-md-6"><label class="form-label">Admission cycle (admission metrics only)</label><select name="cycle_id" class="form-select"><option value="">All cycles</option>@foreach($cycles as $cycle)<option value="{{ $cycle->id }}">{{ $cycle->display_name }}</option>@endforeach</select></div>@endif
        @else
            <div class="col-md-6"><label class="form-label">Batch</label><select name="batch_id" class="form-select"><option value="">All batches</option>@foreach($batches as $batch)<option value="{{ $batch->getKey() }}">{{ $batch->batch_name }}</option>@endforeach</select></div>
            <div class="col-md-3"><label class="form-label">Request ID (individual document)</label><input type="number" min="1" name="request_id" class="form-control"></div>
            <div class="col-md-3"><label class="form-label">Status</label><input name="status" class="form-control" placeholder="All statuses"></div>
        @endif
        <div class="col-12"><button class="btn btn-primary">Generate document</button> <label class="ms-3"><input type="checkbox" name="auto_print" value="1"> Open print dialog for HTML</label></div>
    </form>
</div></div>
@endforeach
@endsection
