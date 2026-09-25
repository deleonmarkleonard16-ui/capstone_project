@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <a href="{{ route('admin.admission.sessions.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left"></i>
            </a>
            <h1 class="h3 mb-0">{{ $session->session_name }}</h1>
            @if ($session->status === 'Completed' || $session->allApplicantsSubmitted())
                <span class="badge bg-success fs-6"><i class="bi bi-check-circle me-1"></i> Completed</span>
            @elseif ($session->status === 'In-Progress')
                <span class="badge bg-warning text-dark fs-6"><i class="bi bi-play-circle-fill me-1"></i> In-Progress</span>
            @else
                <span class="badge bg-info text-dark fs-6"><i class="bi bi-calendar-event me-1"></i> Scheduled</span>
            @endif
        </div>
        <p class="text-muted mb-0 small">
            Start Time: <strong>{{ optional($session->start_time)->format('M d, Y · h:i A') ?: 'Not scheduled' }}</strong> &nbsp;·&nbsp;
            Masterlist Range: <span class="badge bg-light text-primary border font-monospace">#{{ $session->start_number }} – #{{ $session->end_number }}</span> &nbsp;·&nbsp;
            Venue: <strong>{{ $session->room ?: 'Main Testing Hall' }}</strong>
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        @if($session->status !== 'Completed' && !$cycle->isCompleted())
            <form method="POST" action="{{ route('admin.admission.sessions.complete', $session) }}"
                  onsubmit="return confirm('Mark this test session as Completed?');">
                @csrf
                <button type="submit" class="btn btn-success btn-sm fw-semibold">
                    <i class="bi bi-check-circle me-1"></i> Mark as Completed
                </button>
            </form>
        @endif
        <a href="{{ route('admin.admission.sessions.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-list-ul me-1"></i> All Sessions
        </a>
    </div>
</div>

{{-- ── METRIC CARDS ── --}}
@php
    $totalAssigned  = $applicants->count();
    $submittedCount = $applicants->whereNotNull('submitted_at')->count();
    $pendingCount   = $totalAssigned - $submittedCount;
    $avgScore       = $submittedCount > 0 ? round($applicants->whereNotNull('exam_score')->avg('exam_score'), 2) : 0;
@endphp
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card page-card shadow-xs border-start border-4 border-primary h-100">
            <div class="card-body p-3">
                <div class="text-muted small text-uppercase fw-semibold">Assigned Examinees</div>
                <div class="h3 mb-0 fw-bold text-dark">{{ $totalAssigned }}</div>
                <div class="small text-muted mt-1">Range #{{ $session->start_number }} to #{{ $session->end_number }}</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card page-card shadow-xs border-start border-4 border-success h-100">
            <div class="card-body p-3">
                <div class="text-muted small text-uppercase fw-semibold">Submitted Exams</div>
                <div class="h3 mb-0 fw-bold text-success">{{ $submittedCount }}</div>
                <div class="small text-muted mt-1">
                    {{ $totalAssigned > 0 ? round(($submittedCount / $totalAssigned) * 100) : 0 }}% completion
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card page-card shadow-xs border-start border-4 border-warning h-100">
            <div class="card-body p-3">
                <div class="text-muted small text-uppercase fw-semibold">Pending Submissions</div>
                <div class="h3 mb-0 fw-bold text-warning">{{ $pendingCount }}</div>
                <div class="small text-muted mt-1">Awaiting completion</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card page-card shadow-xs border-start border-4 border-dark h-100">
            <div class="card-body p-3">
                <div class="text-muted small text-uppercase fw-semibold">Average Exam Score</div>
                <div class="h3 mb-0 fw-bold text-dark">{{ $avgScore }}</div>
                <div class="small text-muted mt-1">Out of 80 questions</div>
            </div>
        </div>
    </div>
</div>

{{-- ── EXAMINEES ROSTER ── --}}
<div class="card page-card shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-people text-primary fs-5"></i>
            <h2 class="h6 mb-0 fw-bold">Enrolled Examinees Roster</h2>
        </div>
        <span class="badge bg-light text-dark border">{{ $applicants->count() }} Examinee(s)</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3" style="width: 50px;">#</th>
                        <th>Application Number</th>
                        <th>Full Name</th>
                        <th>Course Choice</th>
                        <th>GWA</th>
                        <th>Exam Status</th>
                        <th>Raw Score / Stanine</th>
                        <th class="pe-3 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($applicants as $idx => $applicant)
                        <tr>
                            <td class="ps-3 font-monospace text-muted">{{ $idx + 1 }}</td>
                            <td>
                                <span class="fw-bold font-monospace text-dark">{{ $applicant->application_number }}</span>
                            </td>
                            <td>
                                <div class="fw-semibold text-dark">{{ $applicant->full_name }}</div>
                                <div class="text-muted small">{{ $applicant->sex ?: 'N/A' }}</div>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border">{{ $applicant->course_choice }}</span>
                            </td>
                            <td>
                                <span class="font-monospace">{{ $applicant->gwa ? number_format($applicant->gwa, 2) : '—' }}</span>
                            </td>
                            <td>
                                @if ($applicant->submitted_at)
                                    <span class="badge bg-success-subtle text-success border border-success-subtle">
                                        <i class="bi bi-check-lg me-1"></i> Submitted
                                    </span>
                                    <div class="text-muted small" style="font-size: 11px;">
                                        {{ $applicant->submitted_at->format('M d, h:i A') }}
                                    </div>
                                @else
                                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle">
                                        <i class="bi bi-hourglass-split me-1"></i> Pending
                                    </span>
                                @endif
                            </td>
                            <td>
                                @if($applicant->exam_score !== null)
                                    <div class="fw-bold text-dark font-monospace">{{ number_format($applicant->exam_score, 2) }} / 80</div>
                                    <div class="text-muted small" style="font-size: 11px;">
                                        Stanine: <strong>{{ $applicant->stanine_score ?? '—' }}</strong>
                                    </div>
                                @else
                                    <span class="text-muted small">—</span>
                                @endif
                            </td>
                            <td class="pe-3 text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('admin.admission.paper', $applicant) }}" class="btn btn-outline-secondary btn-sm" target="_blank" title="Print Bubble Sheet">
                                        <i class="bi bi-printer"></i> Paper
                                    </a>
                                    <a href="{{ route('admin.admission.encode', $applicant) }}" class="btn btn-outline-primary btn-sm" title="Encode Bubble Sheet">
                                        <i class="bi bi-pencil-square"></i> Encode
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-5">
                                No examinees currently assigned to this session.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
