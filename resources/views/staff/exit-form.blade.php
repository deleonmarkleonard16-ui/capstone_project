@extends('layouts.app')
@section('content')
<div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3"><div><h1 class="h3 mb-1">Exit Form / Clearance Requests</h1><p class="text-muted mb-0">Review receipts and issue documents without an exam.</p></div>
@if($mode === 'queue')<button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#document-batch-modal">+ Create Bundled Request</button>@endif</div>
@include('staff.partials.document-tabs')
@include('staff.partials.course-filter')
@if($mode === 'archive')<div class="card page-card mb-4"><div class="card-body p-4"><h2 class="h5 section-title">Archived Batches</h2>@forelse($archivedBatches as $batch)<div class="border rounded p-3 mb-2"><strong>{{ $batch->batch_name }}</strong> · {{ $batch->courseLabel() }} <a class="btn btn-sm btn-outline-primary ms-2" href="{{ route(auth()->user()->role.'.exit-form.batches', ['archived'=>1]) }}">Review archived batches</a></div>@empty<p class="text-muted mb-0">No archived batches.</p>@endforelse{{ $archivedBatches->links() }}</div></div>@endif
@include('staff.partials.document-queue')
@if($mode === 'queue')@include('staff.partials.document-batch-modal')@endif
@include('guidance.receipt-modal')
@endsection
