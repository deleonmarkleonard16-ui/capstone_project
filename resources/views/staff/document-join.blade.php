@extends('guidance.layout')
@section('content')
<div class="card mx-auto" style="max-width:760px"><div class="card-body p-4 p-md-5">
    <div class="rounded-3 p-4 mb-4 text-white" style="background:linear-gradient(110deg,var(--accent),var(--accent-deep))"><p class="small text-uppercase mb-1">Document batch request</p><h1 class="h3 mb-1">{{ $batch->batch_name }}</h1><p class="mb-0">{{ $batch->module_type }}</p></div>
    @if(!$entry)
        <p class="muted">Enter your student ID and name exactly as they appear in the imported roster.</p>
        <form method="post" action="{{ route('document.batch.verify', $batch->batch_token) }}">@csrf<div class="row g-3">
            @foreach(['student_id'=>'Student ID','first_name'=>'First name','middle_name'=>'Middle name (optional)','last_name'=>'Last name'] as $field=>$label)<div class="col-12 col-md-6"><label class="form-label fw-semibold" for="{{ $field }}">{{ $label }}</label><input class="form-control" id="{{ $field }}" name="{{ $field }}" value="{{ old($field) }}" maxlength="100" @required($field !== 'middle_name')></div>@endforeach
        </div><button class="btn btn-primary mt-4">Verify roster identity</button></form>
    @else
        <div class="alert alert-success">Verified: <strong>{{ $entry->first_name }} {{ $entry->last_name }}</strong><br>Tracking reference: <strong>{{ $entry->reference }}</strong></div>
        @if(in_array($entry->status, ['pending','approved'], true))
            <form method="post" enctype="multipart/form-data" action="{{ route('document.batch.receipt', $batch->batch_token) }}" id="batch-receipt">@csrf
                <h2 class="h5">Submit payment or clearance receipt</h2>
                <div class="d-flex flex-wrap gap-2 mb-3"><button type="button" class="btn btn-outline-primary" id="camera-start">Take Photo of Receipt</button><button type="button" class="btn btn-outline-primary" id="file-choice">Upload Receipt File</button></div>
                <video id="receipt-camera" class="w-100 rounded-3" style="max-height:360px" playsinline autoplay muted hidden></video><button type="button" id="camera-capture" class="btn btn-secondary mb-3" hidden>Capture photo</button><canvas id="receipt-canvas" hidden></canvas>
                <label class="form-label fw-semibold" for="receipt-file">Receipt image (JPG, PNG, WebP; up to 5 MB)</label><input class="form-control mb-3" id="receipt-file" name="receipt" type="file" accept="image/*" required><img id="receipt-preview" alt="Receipt preview" class="img-fluid mb-3" hidden><p id="camera-message" role="alert"></p><button class="btn btn-primary">Submit receipt</button>
            </form>
        @else
            <div class="alert alert-info" role="status">{{ ['proof_review'=>'Receipt submitted. Awaiting staff verification.','ready'=>'Approved / Ready for Pickup at the Guidance Office.','completed'=>'Claimed / Completed.'][$entry->status] ?? ucfirst($entry->status) }}</div>
        @endif
    @endif
</div></div>
@endsection
@push('scripts')<script src="{{ asset('js/guidance-batch.js') }}" defer></script>@endpush
