@extends('layouts.app')

@section('content')
<div class="container-fluid px-0">

    {{-- ── MONITOR HEADER & PRIMARY CONTROLS ── --}}
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                <a href="{{ route('admin.admission.sessions.index') }}" class="btn btn-outline-secondary btn-sm" title="Back to All Sessions">
                    <i class="bi bi-arrow-left"></i>
                </a>
                <h1 class="h3 mb-0 fw-bold">{{ $session->session_name }}</h1>
                <span id="header-status-badge">
                    @if ($session->status === 'Completed')
                        <span class="badge bg-success fs-6"><i class="bi bi-check-circle me-1"></i> Completed</span>
                    @elseif ($session->status === 'In-Progress')
                        <span class="badge bg-warning text-dark fs-6">
                            <span class="spinner-grow spinner-grow-sm me-1 text-dark" role="status" style="width: 0.75rem; height: 0.75rem;"></span>
                            In-Progress (Live)
                        </span>
                    @else
                        <span class="badge bg-secondary fs-6"><i class="bi bi-calendar-event me-1"></i> Scheduled</span>
                    @endif
                </span>
            </div>
            <p class="text-muted mb-0 small">
                Start Time: <strong>{{ optional($session->start_time)?->setTimezone('Asia/Manila')->format('M d, Y · h:i A') ?: 'Not scheduled' }}</strong> &nbsp;·&nbsp;
                Masterlist Range: <span class="badge bg-light text-primary border font-monospace">#{{ $session->start_number }} – #{{ $session->end_number }}</span> &nbsp;·&nbsp;
                Venue: <strong>{{ $session->room ?: 'Main Testing Hall' }}</strong>
            </p>
        </div>

        {{-- ── LAUNCH / END SESSION & UTILITY ACTION BUTTONS ── --}}
        <div class="d-flex gap-2 flex-wrap align-items-center">
            {{-- 1. START TEST / LAUNCH SESSION (Inside Monitor) --}}
            @if($session->status === 'Scheduled' && !$cycle->isCompleted())
                <form method="POST" action="{{ route('admin.admission.sessions.status', $session) }}" class="d-inline">
                    @csrf
                    <input type="hidden" name="status" value="In-Progress">
                    <button type="submit" class="btn btn-success btn-sm fw-bold shadow-sm px-3"
                            onclick="return confirm('Start test and launch session \'{{ $session->session_name }}\'? Examinees in the waiting room will immediately begin their exam, and late arrivals can still sign in and take the test.');">
                        <i class="bi bi-play-circle-fill me-1"></i> Start Test / Launch Session
                    </button>
                </form>
            @endif

            {{-- 2. END TEST / END SESSION (Inside Monitor) --}}
            @if($session->status === 'In-Progress' && !$cycle->isCompleted())
                <form method="POST" action="{{ route('admin.admission.sessions.complete', $session) }}" class="d-inline"
                      onsubmit="return confirm('End this test session now? All active exams will be concluded and marked as Completed.');">
                    @csrf
                    <button type="submit" class="btn btn-danger btn-sm fw-bold shadow-sm px-3">
                        <i class="bi bi-stop-circle-fill me-1"></i> End Test / End Session
                    </button>
                </form>
            @endif

            {{-- 3. REASSIGN ABSENT TO ANOTHER BATCH (Bulk Action Trigger) --}}
            @if(!$cycle->isCompleted() && $session->status !== 'Completed')
                <button type="button" class="btn btn-outline-warning text-dark btn-sm fw-semibold"
                        data-bs-toggle="modal" data-bs-target="#reassignBulkModal">
                    <i class="bi bi-arrow-left-right me-1"></i> Reassign Absent to Another Batch
                </button>
            @endif

            {{-- 4. QR Code Modal --}}
            <button type="button" class="btn btn-outline-dark btn-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#sessionQrModal{{ $session->id }}">
                <i class="bi bi-qr-code me-1"></i> Session QR
            </button>

            {{-- 5. Print Masterlist --}}
            <a href="{{ route('admin.admission.sessions.print-masterlist', $session) }}" target="_blank" rel="noopener"
               class="btn btn-outline-dark btn-sm fw-semibold">
                <i class="bi bi-printer me-1"></i> Print Masterlist
            </a>

            {{-- 6. OMR Scanner --}}
            @if($session->status !== 'Completed' && !$cycle->isCompleted())
                <button type="button" class="btn btn-outline-primary btn-sm fw-semibold js-open-omr" data-application-number="">
                    <i class="bi bi-camera me-1"></i> OMR Scanner
                </button>
            @endif

            {{-- 7. Live Polling Indicator & Manual Refresh --}}
            <button type="button" id="btn-manual-poll" class="btn btn-light border btn-sm" title="Refresh Live Monitor Now">
                <i class="bi bi-arrow-repeat" id="poll-spinner"></i> <span class="d-none d-md-inline small">Live Feed</span>
            </button>
        </div>
    </div>

    {{-- ── FLASH ALERTS ── --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm mb-4" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if(session('warning'))
        <div class="alert alert-warning alert-dismissible fade show shadow-sm mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> {{ session('warning') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm mb-4" role="alert">
            <i class="bi bi-x-octagon-fill me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- ── MONITOR CONTEXT BANNER ── --}}
    @if ($session->status === 'Scheduled')
        <div class="alert alert-info bg-info bg-opacity-10 border-info border-opacity-25 rounded-3 py-2 px-3 mb-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2 small">
                <i class="bi bi-info-circle-fill text-info fs-5"></i>
                <div>
                    <strong>Session Scheduled:</strong> Examinees scanning the venue QR are placed into the synchronized Waiting Room as <span class="badge bg-success-subtle text-success border border-success-subtle">Ready</span>.
                    Click <strong>Start Test / Launch Session</strong> above when you are ready to administer the exam.
                </div>
            </div>
            <span class="badge bg-white text-info border font-monospace">Auto-refresh active</span>
        </div>
    @elseif ($session->status === 'In-Progress')
        <div class="alert alert-warning bg-warning bg-opacity-10 border-warning border-opacity-25 rounded-3 py-2 px-3 mb-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2 small">
                <span class="spinner-grow spinner-grow-sm text-warning" role="status"></span>
                <div>
                    <strong>Test Session is Live:</strong> Examinees are actively taking the exam.
                    <strong>Late examinees</strong> can still scan the QR or be signed in manually and proceed directly to take the test.
                    Monitor prohibited rules and strikes in real time below.
                </div>
            </div>
            <span class="badge bg-warning text-dark font-monospace"><i class="bi bi-shield-shaded me-1"></i>Proctoring Active</span>
        </div>
    @else
        <div class="alert alert-secondary bg-secondary bg-opacity-10 border-secondary border-opacity-25 rounded-3 py-2 px-3 mb-4 d-flex align-items-center gap-2 small">
            <i class="bi bi-check-all text-secondary fs-5"></i>
            <div>
                <strong>Session Concluded:</strong> This session has been marked as Completed. Examination results and answer sheets are finalized.
            </div>
        </div>
    @endif

    {{-- ── LIVE MONITOR KPI CARDS ── --}}
    <div class="row g-3 mb-4">
        {{-- Total Enrolled --}}
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card page-card shadow-xs border-start border-4 border-primary h-100">
                <div class="card-body p-3">
                    <div class="text-muted small text-uppercase fw-semibold" style="font-size: 11px;">Total Enrolled</div>
                    <div id="kpi-total" class="h3 mb-0 fw-bold text-dark">{{ $totalAssigned }}</div>
                    <div class="small text-muted mt-1 text-truncate">#{{ $session->start_number }}–#{{ $session->end_number }}</div>
                </div>
            </div>
        </div>

        {{-- Ready in Waiting Room --}}
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card page-card shadow-xs border-start border-4 border-success h-100">
                <div class="card-body p-3">
                    <div class="text-muted small text-uppercase fw-semibold" style="font-size: 11px;">Ready / Checked In</div>
                    <div id="kpi-ready" class="h3 mb-0 fw-bold text-success">{{ $readyCount }}</div>
                    <div class="small text-muted mt-1">Waiting room queue</div>
                </div>
            </div>
        </div>

        {{-- In-Progress / Taking Test --}}
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card page-card shadow-xs border-start border-4 border-warning h-100">
                <div class="card-body p-3">
                    <div class="text-muted small text-uppercase fw-semibold" style="font-size: 11px;">Taking Test (Live)</div>
                    <div id="kpi-in-progress" class="h3 mb-0 fw-bold text-warning">{{ $inProgressCount }}</div>
                    <div class="small text-muted mt-1">Active test-takers</div>
                </div>
            </div>
        </div>

        {{-- Submitted Exams --}}
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card page-card shadow-xs border-start border-4 border-info h-100">
                <div class="card-body p-3">
                    <div class="text-muted small text-uppercase fw-semibold" style="font-size: 11px;">Submitted</div>
                    <div id="kpi-submitted" class="h3 mb-0 fw-bold text-info">{{ $submittedCount }}</div>
                    <div class="small text-muted mt-1" id="kpi-percentage">{{ $totalAssigned > 0 ? round(($submittedCount / $totalAssigned) * 100) : 0 }}% completed</div>
                </div>
            </div>
        </div>

        {{-- Absent / Unchecked --}}
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card page-card shadow-xs border-start border-4 border-danger h-100">
                <div class="card-body p-3">
                    <div class="text-muted small text-uppercase fw-semibold" style="font-size: 11px;">Absent</div>
                    <div id="kpi-absent" class="h3 mb-0 fw-bold text-danger">{{ $absentCount }}</div>
                    <div class="small text-muted mt-1">
                        @if($absentCount > 0 && !$cycle->isCompleted() && $session->status !== 'Completed')
                            <a href="#roster-table" class="text-danger fw-semibold" data-bs-toggle="modal" data-bs-target="#reassignBulkModal" style="text-decoration: underline;">Transfer batch</a>
                        @else
                            Not checked in
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Prohibited Rules / Violations --}}
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card page-card shadow-xs border-start border-4 {{ $violationsCount > 0 ? 'border-danger bg-danger bg-opacity-10' : 'border-dark' }} h-100">
                <div class="card-body p-3">
                    <div class="text-muted small text-uppercase fw-semibold" style="font-size: 11px;">Prohibited Rules</div>
                    <div id="kpi-violations" class="h3 mb-0 fw-bold {{ $violationsCount > 0 ? 'text-danger' : 'text-dark' }}">
                        {{ $violationsCount }}
                    </div>
                    <div class="small text-muted mt-1">
                        {{ $violationsCount > 0 ? 'Security strike(s) logged' : 'Clean / No violations' }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── LIVE PROHIBITED RULES & SECURITY INCIDENTS FEED ── --}}
    <div class="card page-card shadow-sm mb-4 border-top border-3 {{ $violationsCount > 0 ? 'border-danger' : 'border-primary' }}">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-shield-exclamation {{ $violationsCount > 0 ? 'text-danger' : 'text-primary' }} fs-5"></i>
                <h2 class="h6 mb-0 fw-bold">Live Prohibited Rules &amp; Security Violations Monitor</h2>
                <span id="badge-incident-count" class="badge {{ $violationsCount > 0 ? 'bg-danger' : 'bg-secondary' }}">
                    {{ $violationsCount }} Incident(s) Logged
                </span>
            </div>
            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#securityFeedCollapse" aria-expanded="{{ $violationsCount > 0 ? 'true' : 'false' }}">
                <i class="bi bi-chevron-down"></i> Toggle Feed
            </button>
        </div>
        <div class="collapse {{ $violationsCount > 0 ? 'show' : '' }}" id="securityFeedCollapse">
            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 220px; overflow-y: auto;">
                    <table class="table table-sm table-hover align-middle mb-0" style="font-size: 13px;">
                        <thead class="table-light sticky-top">
                            <tr>
                                <th class="ps-3">Time</th>
                                <th>Examinee Name</th>
                                <th>Application #</th>
                                <th>Prohibited Rule Violated</th>
                                <th>Strike Number</th>
                                <th class="pe-3 text-end">Status</th>
                            </tr>
                        </thead>
                        <tbody id="security-incidents-tbody">
                            @forelse ($recentIncidents as $incident)
                                <tr>
                                    <td class="ps-3 text-muted font-monospace">{{ $incident->created_at?->format('h:i:s A') ?: '—' }}</td>
                                    <td class="fw-semibold text-dark">{{ $incident->admissionApplicant?->full_name ?? 'Unknown Examinee' }}</td>
                                    <td class="font-monospace">{{ $incident->admissionApplicant?->application_number ?? '—' }}</td>
                                    <td>
                                        <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-1">
                                            <i class="bi bi-exclamation-diamond me-1"></i>
                                            {{ ucwords(str_replace('_', ' ', $incident->incident_type)) }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge {{ $incident->strike_number >= 3 ? 'bg-danger' : 'bg-warning text-dark' }}">
                                            Strike {{ $incident->strike_number }}
                                        </span>
                                    </td>
                                    <td class="pe-3 text-end">
                                        @if($incident->strike_number >= 3)
                                            <span class="text-danger fw-bold small"><i class="bi bi-slash-circle me-1"></i>Auto-Terminated</span>
                                        @else
                                            <span class="text-warning fw-semibold small"><i class="bi bi-flag-fill me-1"></i>Warned</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr id="no-incidents-row">
                                    <td colspan="6" class="text-center text-muted py-3 small">
                                        <i class="bi bi-shield-check text-success me-1 fs-6"></i>
                                        No prohibited rule violations logged for this session yet.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- ── ENROLLED EXAMINEES ROSTER TABLE ── --}}
    <div class="card page-card shadow-sm" id="roster-table">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-people text-primary fs-5"></i>
                <h2 class="h6 mb-0 fw-bold">Session Examinees Roster</h2>
                <span class="badge bg-light text-dark border font-monospace" id="roster-count-badge">{{ $applicants->count() }} Examinee(s)</span>
            </div>

            {{-- Bulk Actions Bar --}}
            <div class="d-flex align-items-center gap-2 flex-wrap">
                @if(!$cycle->isCompleted() && $session->status !== 'Completed' && $otherSessions->isNotEmpty())
                    <button type="button" class="btn btn-outline-warning text-dark btn-sm fw-semibold"
                            data-bs-toggle="modal" data-bs-target="#reassignBulkModal" title="Transfer Absent Applicants">
                        <i class="bi bi-box-arrow-right me-1"></i> Transfer Absent to Batch
                    </button>
                @endif
                <div class="input-group input-group-sm" style="max-width: 220px;">
                    <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                    <input type="text" id="roster-search-input" class="form-control" placeholder="Search examinee...">
                </div>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="roster-main-table">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3" style="width: 45px;">#</th>
                            <th>Application #</th>
                            <th>Full Name</th>
                            <th>Course Choice</th>
                            <th>GWA</th>
                            <th>Attendance / Live Status</th>
                            <th>Prohibited Rules / Strikes</th>
                            <th>Exam Score / Stanine</th>
                            <th class="pe-3 text-end" style="min-width: 200px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="roster-tbody">
                        @forelse ($applicants as $idx => $applicant)
                            @php
                                $status = $applicant->computed_attendance_status;
                                $strikeCount = (int) $applicant->strike_count;
                            @endphp
                            <tr id="applicant-row-{{ $applicant->id }}"
                                data-applicant-id="{{ $applicant->id }}"
                                data-application-number="{{ $applicant->application_number }}"
                                data-applicant-name="{{ $applicant->full_name }}"
                                data-status="{{ $status }}"
                                class="applicant-row {{ $status === 'In-Progress' ? 'table-warning bg-opacity-25' : ($status === 'Absent' ? 'table-light' : '') }}">
                                <td class="ps-3 font-monospace text-muted">{{ $idx + 1 }}</td>
                                <td>
                                    <span class="fw-bold font-monospace text-dark">{{ $applicant->application_number }}</span>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark">{{ $applicant->full_name }}</div>
                                    <div class="text-muted small" style="font-size: 11px;">
                                        {{ $applicant->sex ?: 'N/A' }}
                                        @if($applicant->checked_in_at)
                                            · Check-in: <span class="text-primary">{{ $applicant->checked_in_at->format('h:i A') }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border">{{ $applicant->course_choice }}</span>
                                </td>
                                <td>
                                    <span class="font-monospace">{{ $applicant->gwa ? number_format($applicant->gwa, 2) : '—' }}</span>
                                </td>
                                {{-- 1. ATTENDANCE & LIVE EXAM STATUS --}}
                                <td class="js-attendance-cell">
                                    @if ($status === 'Submitted')
                                        <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle px-2 py-1">
                                            <i class="bi bi-check2-circle me-1"></i> Submitted
                                        </span>
                                        <div class="text-muted small" style="font-size: 11px;">
                                            {{ $applicant->submitted_at?->format('M d, h:i A') }}
                                        </div>
                                    @elseif ($status === 'In-Progress')
                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2 py-1">
                                            <span class="spinner-grow spinner-grow-sm me-1 text-warning" role="status" style="width: 0.6rem; height: 0.6rem;"></span>
                                            Taking Test
                                        </span>
                                    @elseif ($status === 'Ready')
                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                            <i class="bi bi-person-check-fill me-1"></i> Ready (Waiting Room)
                                        </span>
                                    @else
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">
                                            <i class="bi bi-x-circle me-1"></i> Absent
                                        </span>
                                    @endif
                                </td>

                                {{-- 2. PROHIBITED RULES & STRIKES --}}
                                <td class="js-strikes-cell">
                                    @if($strikeCount > 0)
                                        <button type="button" class="btn btn-sm btn-danger px-2 py-0 fw-semibold"
                                                onclick="openIncidentModal({{ $applicant->id }}, '{{ addslashes($applicant->full_name) }}', '{{ $applicant->application_number }}')"
                                                title="View Prohibited Rule Violations">
                                            <i class="bi bi-shield-exclamation me-1"></i> {{ $strikeCount }} Strike(s)
                                        </button>
                                    @else
                                        <span class="badge bg-light text-success border border-success-subtle px-2 py-1">
                                            <i class="bi bi-shield-check me-1"></i> Clean
                                        </span>
                                    @endif
                                </td>

                                {{-- 3. SCORE & STANINE --}}
                                <td class="js-score-cell">
                                    @if($applicant->exam_score !== null)
                                        <div class="fw-bold text-dark font-monospace">{{ number_format($applicant->exam_score, 2) }} / {{ (int) ($cycle->total_items ?: 80) }}</div>
                                        <div class="text-muted small" style="font-size: 11px;">
                                            Stanine: <strong>{{ $applicant->stanine_score ?? '—' }}</strong>
                                        </div>
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>

                                {{-- 4. ACTIONS: TRANSFER BATCH / MANUAL CHECK-IN / PAPER / OMR --}}
                                <td class="pe-3 text-end js-actions-cell">
                                    <div class="btn-group btn-group-sm">
                                        {{-- If absent, show Transfer Batch button --}}
                                        @if(!$applicant->submitted_at && !$cycle->isCompleted())
                                            @if($status === 'Absent')
                                                @if($otherSessions->isNotEmpty())
                                                    <button type="button"
                                                            class="btn btn-outline-warning text-dark btn-sm"
                                                            onclick="openSingleReassignModal({{ $applicant->id }}, '{{ addslashes($applicant->full_name) }}', '{{ $applicant->application_number }}')"
                                                            title="Transfer Absent Applicant to Another Batch">
                                                        <i class="bi bi-arrow-left-right"></i> Add to Batch
                                                    </button>
                                                @endif
                                                {{-- Manual check-in (e.g. late arrival with phone issue) --}}
                                                <form method="POST" action="{{ route('admin.admission.sessions.checkin-applicant', [$session, $applicant]) }}" class="d-inline"
                                                      onsubmit="return confirm('Manually mark {{ $applicant->full_name }} as Present / Checked-in?');">
                                                    @csrf
                                                    <button type="submit" class="btn btn-outline-success btn-sm" title="Manual Check-In / Sign In">
                                                        <i class="bi bi-box-arrow-in-right"></i> Sign In
                                                    </button>
                                                </form>
                                            @elseif($status === 'Ready')
                                                {{-- Undo check-in --}}
                                                <form method="POST" action="{{ route('admin.admission.sessions.mark-absent-applicant', [$session, $applicant]) }}" class="d-inline"
                                                      onsubmit="return confirm('Mark {{ $applicant->full_name }} as Absent (undo check-in)?');">
                                                    @csrf
                                                    <button type="submit" class="btn btn-outline-secondary btn-sm" title="Mark Absent">
                                                        <i class="bi bi-person-x"></i> Absent
                                                    </button>
                                                </form>
                                            @endif
                                        @endif

                                        {{-- Paper Bubble Sheet --}}
                                        <a href="{{ route('admin.admission.paper', $applicant) }}" class="btn btn-outline-secondary btn-sm" target="_blank" title="Print Bubble Sheet">
                                            <i class="bi bi-printer"></i> Paper
                                        </a>

                                        {{-- Manual Encode --}}
                                        <a href="{{ route('admin.admission.encode', $applicant) }}" class="btn btn-outline-primary btn-sm" title="Encode Bubble Sheet">
                                            <i class="bi bi-pencil-square"></i> Encode
                                        </a>

                                        {{-- OMR Web Scanner --}}
                                        @if(!$applicant->submitted_at && $session->status !== 'Completed' && !$cycle->isCompleted())
                                            <button type="button" class="btn btn-primary btn-sm js-open-omr"
                                                    data-application-number="{{ $applicant->application_number }}" title="Scan OMR Answer Sheet">
                                                <i class="bi bi-camera"></i> OMR
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted py-5">
                                    <i class="bi bi-people fs-1 d-block mb-2 text-secondary opacity-50"></i>
                                    No examinees currently assigned to this session.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════
     MODAL 1: REASSIGN SINGLE APPLICANT TO ANOTHER BATCH
     ═══════════════════════════════════════════════════════════ --}}
<div class="modal fade" id="reassignApplicantModal" tabindex="-1" aria-labelledby="reassignApplicantModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" id="reassignSingleForm" action="">
            @csrf
            <div class="modal-content shadow">
                <div class="modal-header bg-light border-bottom">
                    <div class="d-flex align-items-center gap-2">
                        <div class="bg-warning text-dark rounded p-2 d-flex align-items-center justify-content-center" style="width:34px;height:34px;">
                            <i class="bi bi-arrow-left-right"></i>
                        </div>
                        <h5 class="modal-title fw-bold mb-0" id="reassignApplicantModalLabel">Add Absentee to Another Batch</h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="text-muted small mb-3">
                        Move absent examinee <strong id="reassign-single-name" class="text-dark"></strong>
                        (<span id="reassign-single-app" class="font-monospace"></span>) from
                        <span class="badge bg-light text-dark border">{{ $session->session_name }}</span> to another testing batch for a make-up/rescheduled test.
                    </p>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark small">Select Destination Test Batch / Session <span class="text-danger">*</span></label>
                        @if($otherSessions->isEmpty())
                            <div class="alert alert-warning py-2 small mb-0">
                                <i class="bi bi-exclamation-triangle me-1"></i> No other active sessions available in this cycle. Please create a new session first.
                            </div>
                        @else
                            <select name="target_session_id" class="form-select" required>
                                <option value="" disabled selected>-- Choose Destination Batch / Session --</option>
                                @foreach($otherSessions as $other)
                                    <option value="{{ $other->id }}">
                                        {{ $other->session_name }}
                                        · {{ optional($other->start_time)->format('M d, Y · h:i A') }}
                                        @if($other->room) · {{ $other->room }} @endif
                                        ({{ $other->status }})
                                    </option>
                                @endforeach
                            </select>
                        @endif
                    </div>

                    <div class="alert alert-info py-2 px-3 small rounded-3 mb-0">
                        <i class="bi bi-info-circle me-1"></i>
                        The examinee will be unlinked from this session and assigned to the selected batch with attendance status reset to <strong>Absent</strong> awaiting new check-in.
                    </div>
                </div>
                <div class="modal-footer bg-light border-top">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm fw-semibold" @disabled($otherSessions->isEmpty())>
                        <i class="bi bi-check2-circle me-1"></i> Confirm Transfer to Batch
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════
     MODAL 2: BULK REASSIGN ABSENT APPLICANTS TO ANOTHER BATCH
     ═══════════════════════════════════════════════════════════ --}}
<div class="modal fade" id="reassignBulkModal" tabindex="-1" aria-labelledby="reassignBulkModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <form method="POST" action="{{ route('admin.admission.sessions.reassign-absent-bulk', $session) }}">
            @csrf
            <div class="modal-content shadow">
                <div class="modal-header bg-light border-bottom">
                    <div class="d-flex align-items-center gap-2">
                        <div class="bg-warning text-dark rounded p-2 d-flex align-items-center justify-content-center" style="width:36px;height:36px;">
                            <i class="bi bi-people-fill"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold mb-0" id="reassignBulkModalLabel">Reassign Absent Examinees to Another Batch</h5>
                            <small class="text-muted">Batch transfer examinees who missed {{ $session->session_name }}</small>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    @php
                        $absentApplicants = $applicants->filter(fn($a) => $a->computed_attendance_status === 'Absent' && !$a->submitted_at);
                    @endphp

                    @if($absentApplicants->isEmpty())
                        <div class="alert alert-success py-3 text-center mb-0">
                            <i class="bi bi-check-circle fs-4 d-block mb-1 text-success"></i>
                            <strong>No Absent Examinees Found!</strong>
                            <p class="small text-muted mb-0">All enrolled examinees in this session are either checked in or have submitted their exams.</p>
                        </div>
                    @else
                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark small">Select Destination Test Batch / Session <span class="text-danger">*</span></label>
                            @if($otherSessions->isEmpty())
                                <div class="alert alert-warning py-2 small mb-0">
                                    <i class="bi bi-exclamation-triangle me-1"></i> No other active sessions available in this cycle. Please create a new session first.
                                </div>
                            @else
                                <select name="target_session_id" class="form-select" required>
                                    <option value="" disabled selected>-- Choose Destination Batch / Session --</option>
                                    @foreach($otherSessions as $other)
                                        <option value="{{ $other->id }}">
                                            {{ $other->session_name }}
                                            · {{ optional($other->start_time)->format('M d, Y · h:i A') }}
                                            @if($other->room) · {{ $other->room }} @endif
                                            ({{ $other->status }})
                                        </option>
                                    @endforeach
                                </select>
                            @endif
                        </div>

                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="form-label fw-bold text-dark small mb-0">
                                    Select Absent Examinees to Transfer ({{ $absentApplicants->count() }} Available):
                                </label>
                                <div>
                                    <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" id="btn-select-all-absent">Select All</button>
                                    <span class="text-muted mx-1">·</span>
                                    <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" id="btn-deselect-all-absent">Deselect All</button>
                                </div>
                            </div>
                            <div class="border rounded p-2" style="max-height: 220px; overflow-y: auto; background-color: #fafafa;">
                                @foreach($absentApplicants as $abs)
                                    <div class="form-check py-1 border-bottom border-light">
                                        <input class="form-check-input js-bulk-absent-check" type="checkbox" name="applicant_ids[]" value="{{ $abs->id }}" id="absent-chk-{{ $abs->id }}" checked>
                                        <label class="form-check-label small d-flex justify-content-between align-items-center" for="absent-chk-{{ $abs->id }}">
                                            <span>
                                                <strong class="text-dark">{{ $abs->full_name }}</strong>
                                                <span class="text-muted font-monospace ms-1">({{ $abs->application_number }})</span>
                                            </span>
                                            <span class="badge bg-light text-secondary border">{{ $abs->course_choice }}</span>
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" name="transfer_all_absent" value="1" id="transfer_all_absent">
                            <label class="form-check-label small fw-semibold text-secondary" for="transfer_all_absent">
                                Automatically include any future examinees marked absent
                            </label>
                        </div>
                    @endif
                </div>
                <div class="modal-footer bg-light border-top">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning text-dark btn-sm fw-bold" @disabled($otherSessions->isEmpty() || $absentApplicants->isEmpty())>
                        <i class="bi bi-arrow-left-right me-1"></i> Transfer Selected to New Batch
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════
     MODAL 3: EXAMINEE SECURITY VIOLATIONS DETAIL MODAL
     ═══════════════════════════════════════════════════════════ --}}
<div class="modal fade" id="applicantSecurityModal" tabindex="-1" aria-labelledby="applicantSecurityModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow">
            <div class="modal-header bg-danger text-white">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-shield-exclamation fs-5"></i>
                    <h5 class="modal-title fw-bold mb-0" id="applicantSecurityModalLabel">Prohibited Rule Violations</h5>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <div class="fw-bold fs-6 text-dark" id="sec-modal-name"></div>
                    <div class="text-muted font-monospace small" id="sec-modal-app"></div>
                </div>

                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-0" style="font-size: 13px;">
                        <thead class="table-light">
                            <tr>
                                <th>Strike #</th>
                                <th>Violation</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody id="sec-modal-tbody">
                            {{-- Populated via JS --}}
                        </tbody>
                    </table>
                </div>

                <div class="alert alert-danger py-2 px-3 small rounded-3 mt-3 mb-0" id="sec-modal-terminated-alert" style="display:none;">
                    <i class="bi bi-slash-circle me-1"></i>
                    <strong>Exam Auto-Terminated:</strong> Examinee reached 3 strikes for violating test lockdown rules.
                </div>
            </div>
            <div class="modal-footer bg-light border-top">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

{{-- QR Modal & OMR Scanner Modal (Preserved) --}}
@include('admin.admission.sessions.qr_modal', ['session' => $session])

<div class="modal fade" id="omrScannerModal" tabindex="-1" aria-labelledby="omrScannerTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content" id="session-omr-scanner"
             data-submit-url="{{ route('admin.admission.sessions.scan-omr') }}"
             data-session-id="{{ $session->id }}"
             data-total-items="{{ (int) ($cycle->total_items ?: 80) }}"
             data-csrf="{{ csrf_token() }}">
            <div class="modal-header">
                <div>
                    <h2 class="modal-title h5 mb-0" id="omrScannerTitle"><i class="bi bi-camera me-2"></i>OMR Web Scanner</h2>
                    <div class="small text-muted">Frame 1: identify examinee &nbsp;→&nbsp; Frame 2: capture bubble grid</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-lg-6">
                        <div class="position-relative bg-dark rounded overflow-hidden" style="min-height:340px">
                            <video id="session-omr-video" autoplay muted playsinline class="w-100" style="max-height:520px;object-fit:contain"></video>
                            <div id="session-omr-guide" class="position-absolute top-50 start-50 translate-middle text-center text-white w-75">
                                <i class="bi bi-qr-code-scan display-5"></i>
                                <div class="mt-2">Start the camera and point it at the answer sheet header QR.</div>
                            </div>
                        </div>
                        <canvas id="session-omr-canvas" class="w-100 border rounded mt-2 d-none" style="cursor:crosshair"></canvas>
                        <div class="d-flex flex-wrap gap-2 mt-2">
                            <button type="button" id="session-omr-start" class="btn btn-outline-primary"><i class="bi bi-camera-video me-1"></i>Start Camera</button>
                            <button type="button" id="session-omr-capture" class="btn btn-primary" disabled><i class="bi bi-camera me-1"></i>Capture Grid</button>
                            <label class="btn btn-outline-secondary mb-0"><i class="bi bi-upload me-1"></i>Upload Photo<input id="session-omr-file" type="file" accept="image/png,image/jpeg,image/webp" hidden></label>
                            <button type="button" id="session-omr-reset" class="btn btn-outline-secondary"><i class="bi bi-arrow-counterclockwise me-1"></i>Reset Points</button>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="card mb-3">
                            <div class="card-header fw-semibold">Frame 1 — Header QR Detection</div>
                            <div class="card-body">
                                <div id="session-omr-identity" class="text-muted">No examinee identified.</div>
                                <div class="input-group mt-2">
                                    <input id="session-omr-app-number" class="form-control" placeholder="Application number">
                                    <button type="button" id="session-omr-match" class="btn btn-outline-primary">Match Roster</button>
                                </div>
                            </div>
                        </div>
                        <div class="card">
                            <div class="card-header fw-semibold">Frame 2 — Optical Bubble Grid</div>
                            <div class="card-body">
                                <p class="small text-muted">Capture the full grid, then select the four black markers: top-left, top-right, bottom-right, bottom-left.</p>
                                <div id="session-omr-review" class="row g-2 overflow-auto" style="max-height:310px"></div>
                                <div id="session-omr-message" class="alert alert-info py-2 small mt-3 mb-2" role="status">Waiting to start.</div>
                                <button type="button" id="session-omr-submit" class="btn btn-success w-100 fw-semibold" disabled>
                                    <i class="bi bi-check2-circle me-1"></i>Score and Record OMR
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script type="module" src="{{ asset('js/admission-session-omr.js') }}"></script>

{{-- ═══════════════════════════════════════════════════════════
     MONITOR JAVASCRIPT: POLLING, FILTERING, & MODALS
     ═══════════════════════════════════════════════════════════ --}}
<script>
    const pollUrl = "{{ route('admin.admission.sessions.poll', $session) }}";
    const sessionId = {{ $session->id }};
    let isPolling = true;

    // Single Reassign Modal Trigger
    function openSingleReassignModal(applicantId, applicantName, applicationNumber) {
        const form = document.getElementById('reassignSingleForm');
        form.action = `/admin/admission/sessions/${sessionId}/reassign/${applicantId}`;
        document.getElementById('reassign-single-name').textContent = applicantName;
        document.getElementById('reassign-single-app').textContent = applicationNumber;
        const modal = new bootstrap.Modal(document.getElementById('reassignApplicantModal'));
        modal.show();
    }

    // Examinee Security Violations Modal Trigger
    const examineeSecurityLogs = {
        @foreach($applicants as $app)
            @if($app->strike_count > 0)
                "{{ $app->id }}": [
                    @foreach($app->securityLogs as $log)
                        {
                            strike: {{ $log->strike_number }},
                            violation: "{{ ucwords(str_replace('_', ' ', $log->incident_type)) }}",
                            time: "{{ $log->created_at?->format('h:i:s A') ?: '—' }}"
                        },
                    @endforeach
                ],
            @endif
        @endforeach
    };

    function openIncidentModal(applicantId, applicantName, applicationNumber) {
        document.getElementById('sec-modal-name').textContent = applicantName;
        document.getElementById('sec-modal-app').textContent = applicationNumber;
        const tbody = document.getElementById('sec-modal-tbody');
        tbody.innerHTML = '';

        const logs = examineeSecurityLogs[applicantId] || [];
        if (logs.length === 0) {
            tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-2">No violation logs found.</td></tr>';
        } else {
            logs.forEach(l => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><span class="badge ${l.strike >= 3 ? 'bg-danger' : 'bg-warning text-dark'}">Strike ${l.strike}</span></td>
                    <td class="text-danger fw-semibold">${l.violation}</td>
                    <td class="text-muted font-monospace">${l.time}</td>
                `;
                tbody.appendChild(tr);
            });
        }

        const isTerminated = logs.some(l => l.strike >= 3);
        document.getElementById('sec-modal-terminated-alert').style.display = isTerminated ? 'block' : 'none';

        const modal = new bootstrap.Modal(document.getElementById('applicantSecurityModal'));
        modal.show();
    }

    // Bulk selection helpers
    const selectAllBtn = document.getElementById('btn-select-all-absent');
    const deselectAllBtn = document.getElementById('btn-deselect-all-absent');
    if (selectAllBtn) {
        selectAllBtn.addEventListener('click', () => {
            document.querySelectorAll('.js-bulk-absent-check').forEach(cb => cb.checked = true);
        });
    }
    if (deselectAllBtn) {
        deselectAllBtn.addEventListener('click', () => {
            document.querySelectorAll('.js-bulk-absent-check').forEach(cb => cb.checked = false);
        });
    }

    // Client-side quick filter
    const searchInput = document.getElementById('roster-search-input');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();
            document.querySelectorAll('.applicant-row').forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(query) ? '' : 'none';
            });
        });
    }

    // ── LIVE POLLING ENGINE ──
    async function fetchMonitorUpdates() {
        const spinner = document.getElementById('poll-spinner');
        if (spinner) spinner.classList.add('text-primary');

        try {
            const response = await fetch(pollUrl, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            if (!response.ok) return;
            const data = await response.json();

            // 1. Update KPIs
            if (data.kpis) {
                document.getElementById('kpi-total').textContent = data.kpis.total;
                document.getElementById('kpi-ready').textContent = data.kpis.ready;
                document.getElementById('kpi-in-progress').textContent = data.kpis.in_progress;
                document.getElementById('kpi-submitted').textContent = data.kpis.submitted;
                document.getElementById('kpi-absent').textContent = data.kpis.absent;
                const violEl = document.getElementById('kpi-violations');
                violEl.textContent = data.kpis.violations;
                if (data.kpis.violations > 0) {
                    violEl.classList.remove('text-dark');
                    violEl.classList.add('text-danger');
                }
                const pctEl = document.getElementById('kpi-percentage');
                if (pctEl) pctEl.textContent = `${data.kpis.percentage}% completed`;
            }

            // 2. Update Incident Table
            if (data.recent_incidents) {
                const badgeInc = document.getElementById('badge-incident-count');
                if (badgeInc) badgeInc.textContent = `${data.recent_incidents.length} Incident(s) Logged`;

                const tbody = document.getElementById('security-incidents-tbody');
                if (tbody && data.recent_incidents.length > 0) {
                    tbody.innerHTML = '';
                    data.recent_incidents.forEach(inc => {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td class="ps-3 text-muted font-monospace">${inc.time}</td>
                            <td class="fw-semibold text-dark">${inc.applicant_name}</td>
                            <td class="font-monospace">${inc.application_number}</td>
                            <td>
                                <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-1">
                                    <i class="bi bi-exclamation-diamond me-1"></i> ${inc.incident_type}
                                </span>
                            </td>
                            <td>
                                <span class="badge ${inc.strike_number >= 3 ? 'bg-danger' : 'bg-warning text-dark'}">
                                    Strike ${inc.strike_number}
                                </span>
                            </td>
                            <td class="pe-3 text-end">
                                ${inc.strike_number >= 3
                                    ? '<span class="text-danger fw-bold small"><i class="bi bi-slash-circle me-1"></i>Auto-Terminated</span>'
                                    : '<span class="text-warning fw-semibold small"><i class="bi bi-flag-fill me-1"></i>Warned</span>'}
                            </td>
                        `;
                        tbody.appendChild(tr);
                    });
                }
            }

            // 3. Update Individual Rows (Live Status & Strikes)
            if (data.applicants) {
                data.applicants.forEach(app => {
                    const row = document.getElementById(`applicant-row-${app.id}`);
                    if (!row) return;

                    // Update Attendance Cell
                    const cell = row.querySelector('.js-attendance-cell');
                    if (cell) {
                        if (app.attendance_status === 'Submitted') {
                            cell.innerHTML = `
                                <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle px-2 py-1">
                                    <i class="bi bi-check2-circle me-1"></i> Submitted
                                </span>
                                ${app.submitted_at ? `<div class="text-muted small" style="font-size: 11px;">${app.submitted_at}</div>` : ''}
                            `;
                        } else if (app.attendance_status === 'In-Progress') {
                            cell.innerHTML = `
                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2 py-1">
                                    <span class="spinner-grow spinner-grow-sm me-1 text-warning" role="status" style="width: 0.6rem; height: 0.6rem;"></span>
                                    Taking Test
                                </span>
                            `;
                        } else if (app.attendance_status === 'Ready') {
                            cell.innerHTML = `
                                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                    <i class="bi bi-person-check-fill me-1"></i> Ready (Waiting Room)
                                </span>
                            `;
                        } else {
                            cell.innerHTML = `
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">
                                    <i class="bi bi-x-circle me-1"></i> Absent
                                </span>
                            `;
                        }
                    }

                    // Update Strikes Cell
                    const strikeCell = row.querySelector('.js-strikes-cell');
                    if (strikeCell) {
                        if (app.strike_count > 0) {
                            strikeCell.innerHTML = `
                                <button type="button" class="btn btn-sm btn-danger px-2 py-0 fw-semibold"
                                        onclick="openIncidentModal(${app.id}, '${app.full_name.replace(/'/g, "\\'")}', '${app.application_number}')">
                                    <i class="bi bi-shield-exclamation me-1"></i> ${app.strike_count} Strike(s)
                                </button>
                            `;
                            // Update cache
                            if (app.security_incidents) {
                                examineeSecurityLogs[app.id] = app.security_incidents.map(i => ({
                                    strike: i.strike,
                                    violation: i.type,
                                    time: i.time
                                }));
                            }
                        }
                    }

                    // Update Score Cell
                    const scoreCell = row.querySelector('.js-score-cell');
                    if (scoreCell && app.exam_score !== null) {
                        scoreCell.innerHTML = `
                            <div class="fw-bold text-dark font-monospace">${app.exam_score} / ${app.total_items}</div>
                            <div class="text-muted small" style="font-size: 11px;">Stanine: <strong>${app.stanine_score ?? '—'}</strong></div>
                        `;
                    }
                });
            }

        } catch (e) {
            console.warn('Poll error:', e);
        } finally {
            if (spinner) spinner.classList.remove('text-primary');
        }
    }

    // Manual Poll Button
    const manualBtn = document.getElementById('btn-manual-poll');
    if (manualBtn) {
        manualBtn.addEventListener('click', fetchMonitorUpdates);
    }

    // Start background poll every 4 seconds unless session completed
    @if($session->status !== 'Completed')
        setInterval(fetchMonitorUpdates, 4000);
    @endif
</script>
@endsection
