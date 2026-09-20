@extends('guidance.layout')
@section('content')
<div class="card mx-auto" style="max-width:760px"><div class="card-body p-4 p-md-5">
    <div class="rounded-3 p-3 p-md-4 mb-4 text-white" style="background:linear-gradient(110deg,var(--accent),var(--accent-deep))">
        <p class="small mb-1 text-uppercase fw-semibold">Guidance testing · Batch registration</p>
        <h1 class="h3 mb-1">{{ $batch->batch_name }}</h1>
        <p class="mb-0">{{ $batch->test_type }}</p>
    </div>
    @if(!$appointment)
        <h2 class="h5">Verify your roster identity</h2>
        <p class="muted mb-4">Enter your student ID and name exactly as registered in the uploaded class roster. Middle name is optional when the roster does not include one.</p>
        <form method="post" action="{{ route('guidance.batch.verify', $batch->batch_token) }}">@csrf
            <div class="row g-3">
                @foreach(['student_id'=>'Student ID','first_name'=>'First name','middle_name'=>'Middle name (optional)','last_name'=>'Last name'] as $field=>$label)
                    <div class="col-12 col-md-6"><label for="{{ $field }}" class="form-label fw-semibold">{{ $label }}</label><input class="form-control" id="{{ $field }}" name="{{ $field }}" value="{{ old($field) }}" maxlength="100" autocomplete="{{ ['first_name'=>'given-name','middle_name'=>'additional-name','last_name'=>'family-name'][$field] ?? 'off' }}" @required($field !== 'middle_name')></div>
                @endforeach
            </div>
            <button class="btn btn-primary mt-4 w-100 w-md-auto">Verify roster identity</button>
        </form>
    @else
        <div class="alert alert-success" role="status">Verified: <strong>{{ $appointment->applicant->full_name }}</strong></div>
        <div class="row g-3 mb-4">
            @foreach(['course'=>'Course / Program','reason_for_request'=>'Reason for request','test_type'=>'Test category'] as $field=>$label)
                <div class="col-12 col-md-6"><label class="form-label fw-semibold" for="locked-{{ $field }}">{{ $label }}</label><input class="form-control bg-light" id="locked-{{ $field }}" value="{{ $batch->$field }}" readonly></div>
            @endforeach
        </div>
        @if($appointment->attendance_status === 'Pending Scan' && $batch->status === 'Pending Registration')
            <form method="post" enctype="multipart/form-data" action="{{ route('guidance.batch.receipt', $batch->batch_token) }}" id="batch-receipt">@csrf
                <h2 class="h5">Proof of payment</h2>
                <p class="muted">Take a photo with your camera or upload an existing receipt image.</p>
                <div class="d-flex flex-wrap gap-2 mb-3"><button type="button" class="btn btn-outline-primary" id="camera-start">Take Photo of Receipt</button><button type="button" class="btn btn-outline-primary" id="file-choice">Upload Receipt File</button></div>
                <video id="receipt-camera" class="w-100 rounded-3" style="max-height:360px" playsinline autoplay muted hidden></video><button type="button" id="camera-capture" class="btn btn-secondary mb-3" hidden>Capture photo</button><canvas id="receipt-canvas" hidden></canvas>
                <label class="form-label fw-semibold" for="receipt-file">Receipt image (JPG, PNG, WebP; up to 5 MB)</label><input id="receipt-file" name="receipt" type="file" accept="image/*" class="form-control mb-3" required><img id="receipt-preview" alt="Receipt preview" class="img-fluid rounded-3 mb-3" style="max-height:300px" hidden><p id="camera-message" role="alert"></p><button class="btn btn-primary w-100">Submit receipt and join session</button>
            </form>
        @else
            <div class="alert alert-info" id="batch-wait" data-batch-id="{{ $batch->getKey() }}" data-state="{{ route('guidance.batch.state', $batch->batch_token) }}">{{ $appointment->attendance_status === 'Ready' ? 'Ready in Session. Waiting for the proctor to start the assessment.' : $appointment->attendance_status }}</div>
        @endif
    @endif
</div></div>
@endsection
@push('scripts')<script src="{{ asset('js/guidance-batch.js') }}" defer></script>@endpush
