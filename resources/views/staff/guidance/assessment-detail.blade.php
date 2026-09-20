@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">Assessment Review</h1>
        <p class="text-muted mb-0">
            {{ $testTitle }} &mdash; submitted by
            <strong>{{ strtoupper(trim($entry->first_name.' '.$entry->last_name)) }}</strong>
            (Ref: <code>{{ $entry->reference }}</code>)
        </p>
    </div>
    <a href="{{ route(auth()->user()->role.'.guidance.index') }}" class="btn btn-outline-secondary">&larr; Back to Guidance Testing</a>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="row g-4">
    {{-- Scores & Counselor Notes --}}
    <div class="col-lg-5">
        <div class="card page-card mb-4">
            <div class="card-body p-4">
                <h2 class="h5 mb-3">Computed Scores</h2>
                <p class="text-muted small mb-3">Completed: {{ $submission->completed_at?->timezone('Asia/Manila')->format('M d, Y h:i A') }}</p>

                @if($submission->test_type === 'dass21')
                    @php $scores = $submission->scores ?? []; $interp = $submission->interpretation ?? []; @endphp
                    <table class="table table-sm table-bordered">
                        <thead class="table-light"><tr><th>Subscale</th><th>Raw Score</th><th>Severity</th></tr></thead>
                        <tbody>
                            <tr><td>Depression</td><td>{{ $scores['depression'] ?? '—' }}</td><td>{{ $interp['depression'] ?? '—' }}</td></tr>
                            <tr><td>Anxiety</td><td>{{ $scores['anxiety'] ?? '—' }}</td><td>{{ $interp['anxiety'] ?? '—' }}</td></tr>
                            <tr><td>Stress</td><td>{{ $scores['stress'] ?? '—' }}</td><td>{{ $interp['stress'] ?? '—' }}</td></tr>
                            <tr class="fw-bold table-light"><td>Total</td><td colspan="2">{{ $scores['total'] ?? '—' }}</td></tr>
                        </tbody>
                    </table>
                @elseif(in_array($submission->test_type, ['phq9','gad7']))
                    @php $scores = $submission->scores ?? []; $interp = $submission->interpretation ?? []; @endphp
                    <p><strong>Total Score:</strong> {{ $scores['total'] ?? '—' }}</p>
                    <p><strong>Severity:</strong> <span class="badge bg-secondary">{{ $interp['severity'] ?? '—' }}</span></p>
                @elseif($submission->test_type === 'bfpi')
                    @php $scores = $submission->scores ?? []; $interp = $submission->interpretation ?? []; @endphp
                    <p><strong>Mean Score:</strong> {{ $scores['mean'] ?? '—' }}</p>
                    <p><strong>Overall Level:</strong> <span class="badge bg-secondary">{{ $interp['overall_level'] ?? '—' }}</span></p>
                @elseif($submission->test_type === 'career')
                    @php $scores = $submission->scores ?? []; $interp = $submission->interpretation ?? []; @endphp
                    <table class="table table-sm table-bordered">
                        <thead class="table-light"><tr><th>Trait</th><th>Score</th></tr></thead>
                        <tbody>
                            @foreach($scores as $trait => $score)
                                <tr><td>{{ $trait }}</td><td>{{ $score }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                    <p class="mt-2"><strong>Top RIASEC Traits:</strong> {{ $interp['top_traits'] ?? '—' }}</p>
                @endif
            </div>
        </div>

        <div class="card page-card">
            <div class="card-body p-4">
                <h2 class="h5 mb-2">Counselor Notes</h2>
                <form method="POST" action="{{ route(auth()->user()->role.'.guidance.submission.notes', $submission) }}">
                    @csrf @method('PATCH')
                    <textarea class="form-control mb-2" name="counselor_notes" rows="5" maxlength="3000"
                        placeholder="Add clinical notes, referral remarks, follow-up actions...">{{ $submission->counselor_notes }}</textarea>
                    <button class="btn btn-primary btn-sm">Save Notes</button>
                </form>
            </div>
        </div>
    </div>

    {{-- Student Responses --}}
    <div class="col-lg-7">
        <div class="card page-card">
            <div class="card-body p-4">
                <h2 class="h5 mb-3">Student Responses</h2>
                @php $answers = $submission->answers ?? []; @endphp
                @forelse($questions as $idx => $qText)
                    <div class="mb-3 p-3 rounded border bg-light">
                        <p class="mb-1 fw-semibold small">{{ $idx }}. {{ $qText }}</p>
                        <p class="mb-0 text-primary">Answer: <strong>{{ $answers[$idx] ?? '—' }}</strong></p>
                    </div>
                @empty
                    <p class="text-muted">No questions found for this test type.</p>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
