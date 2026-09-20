@extends('layouts.app')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card page-card">
                <div class="card-body p-5 text-center">
                    <p class="text-uppercase text-success fw-semibold mb-2">Checked In</p>
                    <h1 class="display-6 fw-bold">Welcome, {{ $assignment->applicant->full_name }}</h1>
                    <p class="lead text-muted">Your attendance has been recorded. Please stay on this page while waiting for the staff to start the exam.</p>

                    <div class="row g-3 text-start my-4">
                        <div class="col-md-4"><div class="border rounded p-3 bg-light h-100"><div class="text-muted small">Session</div><div class="fw-semibold">{{ $assignment->testSession->title }}</div></div></div>
                        <div class="col-md-4"><div class="border rounded p-3 bg-light h-100"><div class="text-muted small">Room</div><div class="fw-semibold">{{ $assignment->testSession->room }}</div></div></div>
                        <div class="col-md-4"><div class="border rounded p-3 bg-light h-100"><div class="text-muted small">Status</div><div class="fw-semibold text-capitalize">{{ str_replace('_', ' ', $assignment->testSession->status) }}</div></div></div>
                    </div>

                    @if ($assignment->testSession->isStarted())
                        <a href="{{ route('answers.show') }}" class="btn btn-primary btn-lg">Open Answer Sheet</a>
                    @else
                        <div class="alert alert-info mb-0">Waiting for the staff to click <strong>Start Test</strong>. This page refreshes automatically every 10 seconds.</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    @if (! $assignment->testSession->isStarted())
        <script>
            setTimeout(() => window.location.reload(), 10000);
        </script>
    @endif
@endpush
