@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <h1 class="h3 mb-0">Admission Test Sessions</h1>
            <span class="badge {{ $cycle->isActive() ? 'bg-success' : 'bg-warning text-dark' }} fs-6">
                {{ $cycle->displayName }} ({{ $cycle->academic_year }})
            </span>
        </div>
        <p class="text-muted mb-0 small">
            Configure testing batches, monitor examinee attendance, and track live examination submissions.
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createSessionModal" @disabled($cycle->isCompleted())>
            <i class="bi bi-plus-circle me-1"></i> Create Test Session
        </button>
        <a class="btn btn-outline-primary btn-sm" href="{{ route('admin.admission.masterlist', ['cycle_id' => $cycle->id]) }}">
            <i class="bi bi-table me-1"></i> Masterlist
        </a>
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.admission.encoding-sheet', ['cycle_id' => $cycle->id]) }}">
            <i class="bi bi-grid-3x3 me-1"></i> Encoding Sheet
        </a>
    </div>
</div>

{{-- ── SUMMARY KPI CARDS ── --}}
@php
    $totalCount     = $sessions->count();
    $scheduledCount = $sessions->where('status', 'Scheduled')->count();
    $progressCount  = $sessions->where('status', 'In-Progress')->count();
    $completedCount = $sessions->where('status', 'Completed')->count();
    $totalAssigned  = $sessions->sum('applicants_count');
    $totalSubmitted = $sessions->sum('submitted_count');
@endphp
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card page-card shadow-xs border-start border-4 border-primary h-100">
            <div class="card-body p-3">
                <div class="text-muted small text-uppercase fw-semibold">Total Sessions</div>
                <div class="h3 mb-0 fw-bold text-dark">{{ $totalCount }}</div>
                <div class="small text-muted mt-1">{{ $totalAssigned }} examinee(s) enrolled</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card page-card shadow-xs border-start border-4 border-info h-100">
            <div class="card-body p-3">
                <div class="text-muted small text-uppercase fw-semibold">Scheduled</div>
                <div class="h3 mb-0 fw-bold text-info">{{ $scheduledCount }}</div>
                <div class="small text-muted mt-1">Awaiting start time</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card page-card shadow-xs border-start border-4 border-warning h-100">
            <div class="card-body p-3">
                <div class="text-muted small text-uppercase fw-semibold">In-Progress</div>
                <div class="h3 mb-0 fw-bold text-warning">{{ $progressCount }}</div>
                <div class="small text-muted mt-1">Live / check-in active</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card page-card shadow-xs border-start border-4 border-success h-100">
            <div class="card-body p-3">
                <div class="text-muted small text-uppercase fw-semibold">Completed</div>
                <div class="h3 mb-0 fw-bold text-success">{{ $completedCount }}</div>
                <div class="small text-muted mt-1">{{ $totalSubmitted }} submission(s)</div>
            </div>
        </div>
    </div>
</div>

{{-- ── TEST SESSIONS ROSTER TABLE ── --}}
<div class="card page-card shadow-sm">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-calendar3 text-primary fs-5"></i>
            <h2 class="h6 mb-0 fw-bold">Admission Test Sessions Roster</h2>
        </div>
        <span class="badge bg-light text-dark border">{{ $sessions->count() }} Session(s) Registered</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">Session Name</th>
                        <th>Start Date &amp; Time</th>
                        <th>Assigned Range</th>
                        <th>Room / Venue</th>
                        <th style="min-width: 170px;">Examinees &amp; Submissions</th>
                        <th>Status</th>
                        <th class="pe-3 text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sessions as $session)
                        @php
                            $assignedCount  = $session->applicants_count;
                            $submittedCount = $session->submitted_count;
                            $pct            = $assignedCount > 0 ? round(($submittedCount / $assignedCount) * 100) : 0;
                            $isDone         = $session->status === 'Completed' || ($assignedCount > 0 && $assignedCount === $submittedCount);
                        @endphp
                        <tr class="{{ $session->status === 'In-Progress' ? 'table-warning bg-opacity-25' : ($isDone ? 'table-light opacity-75' : '') }}">
                            <td class="ps-3">
                                <div class="fw-bold text-dark">{{ $session->session_name }}</div>
                                @if($session->qr_token)
                                    <div class="text-muted small font-monospace" style="font-size: 11px;">
                                        Token: {{ substr($session->qr_token, 0, 12) }}…
                                    </div>
                                @endif
                            </td>
                            <td>
                                @if($session->start_time)
                                    <div class="fw-semibold text-dark">
                                        <i class="bi bi-clock me-1 text-primary"></i>
                                        {{ $session->start_time->format('M d, Y') }}
                                    </div>
                                    <div class="text-muted small">
                                        {{ $session->start_time->format('h:i A') }}
                                    </div>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge bg-light text-primary border font-monospace px-2 py-1">
                                    #{{ $session->start_number }} – #{{ $session->end_number }}
                                </span>
                                <div class="text-muted small mt-1" style="font-size: 11px;">
                                    ({{ ($session->end_number - $session->start_number) + 1 }} slots)
                                </div>
                            </td>
                            <td>
                                {{ $session->room ?: 'Main Testing Hall' }}
                            </td>
                            <td>
                                <div class="d-flex justify-content-between align-items-center small mb-1">
                                    <span class="fw-semibold">{{ $submittedCount }} / {{ $assignedCount }} Submitted</span>
                                    <span class="text-muted">{{ $pct }}%</span>
                                </div>
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar {{ $pct === 100 ? 'bg-success' : ($pct > 0 ? 'bg-primary' : 'bg-secondary') }}"
                                         role="progressbar"
                                         style="width: {{ $pct }}%"
                                         aria-valuenow="{{ $pct }}"
                                         aria-valuemin="0"
                                         aria-valuemax="100"></div>
                                </div>
                            </td>
                            <td>
                                @if ($session->status === 'Completed' || $isDone)
                                    <span class="badge bg-success">
                                        <i class="bi bi-check-circle me-1"></i> Completed
                                    </span>
                                @elseif ($session->status === 'In-Progress')
                                    <span class="badge bg-warning text-dark">
                                        <i class="bi bi-play-circle-fill me-1"></i> In-Progress
                                    </span>
                                @else
                                    <span class="badge bg-info text-dark">
                                        <i class="bi bi-calendar-event me-1"></i> Scheduled
                                    </span>
                                @endif
                            </td>
                            <td class="pe-3 text-end">
                                <div class="btn-group btn-group-sm">
                                    {{-- Quick status transitions --}}
                                    @if($session->status === 'Scheduled' && !$cycle->isCompleted())
                                        <form method="POST" action="{{ route('admin.admission.sessions.status', $session) }}" class="d-inline">
                                            @csrf
                                            <input type="hidden" name="status" value="In-Progress">
                                            <button type="submit" class="btn btn-outline-success btn-sm" title="Start Session">
                                                <i class="bi bi-play-fill"></i> Start
                                            </button>
                                        </form>
                                    @elseif($session->status === 'In-Progress' && !$cycle->isCompleted())
                                        <form method="POST" action="{{ route('admin.admission.sessions.complete', $session) }}"
                                              onsubmit="return confirm('Mark session \'{{ $session->session_name }}\' as Completed?');"
                                              class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-warning btn-sm" title="Mark Completed">
                                                <i class="bi bi-check-all"></i> Complete
                                            </button>
                                        </form>
                                    @endif

                                    <a href="{{ route('admin.admission.sessions.show', $session) }}" class="btn btn-outline-primary btn-sm" title="View Session Roster">
                                        <i class="bi bi-people"></i> Roster
                                    </a>

                                    @unless($cycle->isCompleted())
                                        <button type="button"
                                                class="btn btn-outline-secondary btn-sm"
                                                data-bs-toggle="modal"
                                                data-bs-target="#editSessionModal{{ $session->id }}"
                                                title="Edit Session">
                                            <i class="bi bi-pencil"></i>
                                        </button>

                                        <form method="POST"
                                              action="{{ route('admin.admission.sessions.destroy', $session) }}"
                                              class="d-inline"
                                              onsubmit="return confirm('Delete session \'{{ $session->session_name }}\'? Assigned examinees will be unlinked.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger btn-sm" title="Delete Session">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    @endunless
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-5">
                                <i class="bi bi-calendar-x fs-1 d-block mb-2 text-secondary opacity-50"></i>
                                <h6 class="fw-bold mb-1">No Admission Sessions Configured</h6>
                                <p class="small text-muted mb-3">Click "Create Test Session" to schedule examinee batches by numerical range.</p>
                                @unless($cycle->isCompleted())
                                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createSessionModal">
                                        <i class="bi bi-plus-circle me-1"></i> Create Test Session
                                    </button>
                                @endunless
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ── MODALS ── --}}
@include('admin.admission.sessions.create_session_modal')

@foreach($sessions as $session)
    @include('admin.admission.sessions.edit_session_modal', ['session' => $session])
@endforeach

@endsection
