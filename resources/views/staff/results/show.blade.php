@extends('layouts.app')

@section('content')
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1">Applicant Test Details</h1>
            <p class="text-muted mb-0">{{ $session->title }} | {{ $answerSheet->applicant->full_name }}</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route(auth()->user()->role.'.sessions.results.index', $session) }}" class="btn btn-outline-secondary">Back to Results</a>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <div class="card page-card h-100">
                <div class="card-body p-4">
                    <h2 class="h5 mb-3">Applicant Information</h2>
                    <div class="row g-3">
                        <div class="col-md-6"><div class="border rounded p-3 bg-light h-100"><div class="text-muted small">Full Name</div><div class="fw-semibold">{{ $answerSheet->applicant->full_name }}</div></div></div>
                        <div class="col-md-6"><div class="border rounded p-3 bg-light h-100"><div class="text-muted small">Application Number</div><div class="fw-semibold">{{ $answerSheet->applicant->application_number }}</div></div></div>
                        <div class="col-md-6"><div class="border rounded p-3 bg-light h-100"><div class="text-muted small">Gender</div><div class="fw-semibold">{{ $answerSheet->applicant->gender ?: '-' }}</div></div></div>
                        <div class="col-md-6"><div class="border rounded p-3 bg-light h-100"><div class="text-muted small">Email</div><div class="fw-semibold">{{ $answerSheet->applicant->email ?: '-' }}</div></div></div>
                        <div class="col-md-6"><div class="border rounded p-3 bg-light h-100"><div class="text-muted small">Contact Number</div><div class="fw-semibold">{{ $answerSheet->applicant->contact_number ?: '-' }}</div></div></div>
                        <div class="col-md-6"><div class="border rounded p-3 bg-light h-100"><div class="text-muted small">Application Status</div><div class="fw-semibold text-capitalize">{{ $answerSheet->applicant->status ?: '-' }}</div></div></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card page-card h-100">
                <div class="card-body p-4">
                    <h2 class="h5 mb-3">Test Summary</h2>
                    <div class="d-grid gap-3">
                        <div class="border rounded p-3 bg-light">
                            <div class="text-muted small">Score</div>
                            <div class="fs-4 fw-bold">{{ $score }}/80</div>
                        </div>
                        <div class="border rounded p-3 bg-light">
                            <div class="text-muted small">Result</div>
                            <div class="fw-semibold">
                                @if ($session->answerKey)
                                    <span class="badge {{ $passed ? 'text-bg-success' : 'text-bg-danger' }}">
                                        {{ $passed ? 'Passed' : 'Did Not Pass' }}
                                    </span>
                                @else
                                    <span class="badge text-bg-secondary">No Answer Key Yet</span>
                                @endif
                            </div>
                        </div>
                        <div class="border rounded p-3 bg-light">
                            <div class="text-muted small">Started At</div>
                            <div class="fw-semibold">{{ optional($answerSheet->started_at)->format('M d, Y h:i:s A') ?: '-' }}</div>
                        </div>
                        <div class="border rounded p-3 bg-light">
                            <div class="text-muted small">Submitted At</div>
                            <div class="fw-semibold">{{ optional($answerSheet->submitted_at)->format('M d, Y h:i:s A') ?: 'Locked when timer expired' }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card page-card">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
                <div>
                    <h2 class="h5 mb-1">Answer Sheet</h2>
                    <p class="text-muted mb-0">Submitted answers for all 80 items.</p>
                </div>
                @if (! $session->answerKey)
                    <span class="text-muted small">Save an answer key to compare correct answers.</span>
                @endif
            </div>

            <div class="row row-cols-1 row-cols-md-2 row-cols-xl-4 g-3">
                @foreach ($questionNumbers as $number)
                    @php
                        $question = 'q'.$number;
                        $answer = $answerSheet->{$question};
                        $correct = $session->answerKey?->{$question};
                        $isCorrect = $session->answerKey && $answer && $answer === $correct;
                        $statusLabel = ! $session->answerKey
                            ? null
                            : ($answer
                                ? ($isCorrect ? 'Correct' : 'Wrong')
                                : 'No Answer');
                        $statusClass = $statusLabel === 'Correct'
                            ? 'text-bg-success'
                            : ($statusLabel === 'No Answer' ? 'text-bg-secondary' : 'text-bg-danger');
                    @endphp
                    <div class="col">
                        <div class="question-block">
                            <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                                <div class="fw-semibold">Question {{ $number }}</div>
                                @if ($session->answerKey)
                                    <span class="badge {{ $statusClass }}">
                                        {{ $statusLabel }}
                                    </span>
                                @endif
                            </div>
                            <div class="small text-muted">Applicant Answer</div>
                            <div class="fs-5 fw-bold mb-3">{{ $answer ?: 'No answer' }}</div>

                            @if ($session->answerKey)
                                <div class="small text-muted">Correct Answer</div>
                                <div class="fw-semibold">{{ $correct ?: '-' }}</div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endsection
