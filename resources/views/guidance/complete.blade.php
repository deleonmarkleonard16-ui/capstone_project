@extends('guidance.layout')
@section('content')
<div class="card border-0 shadow-sm p-4 p-md-5 text-center mx-auto mt-4" style="max-width: 560px; border-radius: 16px;">
    <div class="mb-3">
        <span class="d-inline-flex align-items-center justify-content-center rounded-circle {{ $terminated ? 'bg-danger bg-opacity-10' : 'bg-success bg-opacity-10' }}" style="width:64px;height:64px;">
            <i class="bi {{ $terminated ? 'bi-x-circle-fill text-danger' : 'bi-check-circle-fill text-success' }}" style="font-size:2rem;"></i>
        </span>
    </div>
    <h1 class="h4 fw-bold mb-2">{{ $terminated ? 'Assessment ended by proctor' : 'Assessment completed' }}</h1>
    <p class="text-muted mb-4">{{ $terminated ? 'Your assessment session was ended by the proctor due to a detected violation.' : 'Your responses have been recorded and are now under review by the Guidance Office. Please wait for the official result, which will be provided by the Guidance Counselor after evaluation.' }}</p>
    <a class="btn btn-primary px-4" href="{{ route('portal.index') }}?service=testing#track">Return to portal</a>
</div>
@endsection
