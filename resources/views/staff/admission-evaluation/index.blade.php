@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">Admission Evaluation & Total Marks</h1>
        <p class="text-muted mb-0">Encode GWA and Interview scores. System computes Total Marks and live rankings.</p>
    </div>
    @if($session)
    <a href="{{ route(auth()->user()->role.'.admission-evaluation.passers', ['session_id' => $session->id]) }}" class="btn btn-outline-success">
        🏆 View Ranked List of Passers
    </a>
    @endif
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

{{-- Session Selector --}}
<div class="card page-card mb-4">
    <div class="card-body p-3">
        <form method="GET" class="d-flex gap-3 align-items-end flex-wrap">
            <div>
                <label class="form-label mb-1 fw-semibold">Test Session</label>
                <select name="session_id" class="form-select" onchange="this.form.submit()">
                    @foreach($sessions as $s)
                        <option value="{{ $s->id }}" @selected($session?->id === $s->id)>
                            {{ $s->name ?? ('Session #'.$s->id) }} ({{ $s->assignments_count }} applicants)
                        </option>
                    @endforeach
                </select>
            </div>
        </form>
    </div>
</div>

@if($session)
{{-- Weights Info --}}
<div class="alert alert-info mb-4">
    <strong>Computation Weights:</strong>
    Exam Score <strong>{{ $weights['exam'] }}%</strong> +
    GWA <strong>{{ $weights['gwa'] }}%</strong> +
    Interview <strong>{{ $weights['interview'] }}%</strong> = Total Marks (100%)
</div>

{{-- Evaluation Table --}}
<div class="card page-card mb-4">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Rank</th>
                        <th>Applicant</th>
                        <th>Exam Score</th>
                        <th>GWA</th>
                        <th>Interview</th>
                        <th>Total Marks</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($applicants as $applicant)
                    @php $eval = $evaluations[$applicant->id] ?? null; @endphp
                    <tr>
                        <td>{{ $eval ? '#'.$eval->rank : '—' }}</td>
                        <td>
                            <div class="fw-semibold">{{ $applicant->last_name }}, {{ $applicant->first_name }}</div>
                            <div class="text-muted small">{{ $applicant->application_number ?? '' }}</div>
                        </td>
                        <td>{{ $eval ? number_format($eval->exam_score, 1) : '—' }}</td>
                        <td>{{ $eval ? number_format($eval->gwa, 2) : '—' }}</td>
                        <td>{{ $eval ? number_format($eval->interview_score, 1) : '—' }}</td>
                        <td>
                            @if($eval)
                                <span class="fw-bold text-success">{{ number_format($eval->total_marks, 2) }}</span>
                            @else
                                <span class="text-muted">Not yet evaluated</span>
                            @endif
                        </td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" type="button"
                                data-bs-toggle="collapse"
                                data-bs-target="#eval-form-{{ $applicant->id }}">
                                {{ $eval ? 'Edit' : 'Encode' }}
                            </button>
                        </td>
                    </tr>
                    <tr class="collapse" id="eval-form-{{ $applicant->id }}">
                        <td colspan="7" class="bg-light p-3">
                            <form method="POST" action="{{ route(auth()->user()->role.'.admission-evaluation.store') }}">
                                @csrf
                                <input type="hidden" name="test_session_id" value="{{ $session->id }}">
                                <input type="hidden" name="applicant_id" value="{{ $applicant->id }}">
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <label class="form-label">Exam Score (0–100)</label>
                                        <input type="number" step="0.01" min="0" max="100" name="exam_score"
                                            class="form-control" required
                                            value="{{ $eval ? $eval->exam_score : '' }}">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">High School GWA (1.0–100)</label>
                                        <input type="number" step="0.01" min="1" max="100" name="gwa"
                                            class="form-control" required
                                            value="{{ $eval ? $eval->gwa : '' }}">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Interview Score (0–100)</label>
                                        <input type="number" step="0.01" min="0" max="100" name="interview_score"
                                            class="form-control" required
                                            value="{{ $eval ? $eval->interview_score : '' }}">
                                    </div>
                                    <div class="col-md-3 d-flex align-items-end">
                                        <button class="btn btn-primary w-100">Save & Rank</button>
                                    </div>
                                </div>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-muted text-center py-4">No applicants assigned to this session yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif
@endsection
