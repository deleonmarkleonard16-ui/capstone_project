@extends('layouts.app')

@section('content')
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card page-card">
                <div class="card-body p-5 text-center">
                    <p class="text-uppercase text-success fw-semibold mb-2">Submission Complete</p>
                    <h1 class="display-6 fw-bold">Thank you, {{ $assignment->applicant->full_name }}</h1>
                    <p class="lead text-muted">Your answer sheet has been received or locked based on the session timer.</p>

                    <div class="row g-3 text-start my-4">
                        <div class="col-md-6"><div class="border rounded p-3 bg-light h-100"><div class="text-muted small">Session</div><div class="fw-semibold">{{ $assignment->testSession->title }}</div></div></div>
                        <div class="col-md-6"><div class="border rounded p-3 bg-light h-100"><div class="text-muted small">Submitted At</div><div class="fw-semibold">{{ optional($answerSheet?->submitted_at)->format('M d, Y h:i:s A') ?: 'Locked when timer expired' }}</div></div></div>
                    </div>

                    <div class="alert alert-secondary mb-0">You may now wait for the next instruction from the admissions staff.</div>
                </div>
            </div>
        </div>
    </div>
@endsection
