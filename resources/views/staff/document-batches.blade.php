@extends('layouts.app')
@section('content')
@php($moduleKey = $module)
@php($mode = $archived ? 'archive' : 'batches')
<div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
    <div><h1 class="h3 mb-1">{{ \App\Http\Controllers\DocumentRequestController::MODULES[$module] }} {{ $archived ? 'Archived Batches' : 'Bundled / Batch Queue' }}</h1><p class="text-muted mb-0">Document requests are verified and claimed without an exam.</p></div>
    @unless($archived)<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#document-batch-modal">+ Create Bundled Request</button>@endunless
</div>
@include('staff.partials.document-tabs')
@include('staff.partials.course-filter')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
<div data-module-live data-module="{{ $module }}" data-view="{{ $mode }}">
@forelse($batches as $batch)
<details class="card page-card mb-3" data-batch="{{ $batch->getKey() }}"><summary class="card-body p-3 fw-semibold">{{ $batch->batch_name }} · {{ $batch->course }} · {{ $batch->documentRequests->count() }} requests</summary>
    <div class="card-body pt-0"><div class="d-flex align-items-center gap-3 flex-wrap mb-3"><img src="{{ app(\App\Services\GuidanceQrService::class)->dataUri(route('document.batch.join', $batch->batch_token)) }}" alt="Batch join QR code" width="130" height="130"><a class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener" href="{{ route('document.batch.join', $batch->batch_token) }}">Open / Print Batch Join Link</a></div>
        <div class="table-responsive"><table class="table"><thead><tr><th>Student</th><th>ID</th><th>Reference</th><th>Status</th><th>Action</th></tr></thead><tbody>
        @foreach($batch->documentRequests as $entry)<tr><td>{{ $entry->first_name }} {{ $entry->last_name }}</td><td>{{ $entry->student_number }}</td><td>{{ $entry->reference }}</td><td>@include('staff.partials.document-status')</td><td>@include('staff.partials.document-actions')</td></tr>@endforeach
        </tbody></table></div>
    </div>
</details>
@empty <div class="card page-card"><div class="card-body text-muted">No {{ $archived ? 'archived' : 'active' }} batches for this module.</div></div> @endforelse
{{ $batches->links() }}
</div>
@unless($archived)<div class="modal fade" id="document-batch-modal" tabindex="-1" aria-labelledby="document-batch-title"><div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-header"><h2 id="document-batch-title" class="h5 modal-title">Create {{ \App\Http\Controllers\DocumentRequestController::MODULES[$module] }} Batch</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <form method="post" enctype="multipart/form-data" action="{{ route(auth()->user()->role.'.'.$module.'.batches.store') }}">@csrf<div class="modal-body">
        <label class="form-label" for="batch-name">Batch name</label><input id="batch-name" class="form-control mb-3" name="batch_name" required maxlength="255">
        <label class="form-label" for="batch-course">Course / program</label><select id="batch-course" class="form-select mb-3" name="course" required><option value="">Select a program</option>@foreach(\App\Support\CourseCatalog::activeOptions() as $code=>$title)<option value="{{ $code }}" @selected(old('course') === $code)>{{ $title }} ({{ $code }})</option>@endforeach</select>
        <label class="form-label" for="batch-section">Section name</label><input id="batch-section" class="form-control mb-3" name="year_section" value="{{ old('year_section') }}" maxlength="50">
        <label class="form-label" for="batch-reason">Reason for request</label><input id="batch-reason" class="form-control mb-3" name="reason_for_request" required maxlength="255">
        <label class="form-label" for="batch-roster">CSV roster</label><input id="batch-roster" class="form-control" type="file" name="roster" accept=".csv,text/csv" required><p class="small text-muted mt-2">Columns: student_id, first_name, middle_name (optional), last_name. fname, mname, and lname are accepted.</p>
    </div><div class="modal-footer"><button class="btn btn-primary">Import batch</button></div></form>
</div></div></div>@endunless
@include('guidance.receipt-modal')
@endsection
