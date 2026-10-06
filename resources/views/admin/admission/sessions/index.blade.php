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
        <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createBatchModal" @disabled($cycle->isCompleted())>
            <i class="bi bi-collection me-1"></i> Add Batch
        </button>
        @if($batches->isNotEmpty())
            <div class="dropdown">
                <button class="btn btn-primary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" @disabled($cycle->isCompleted())>
                    <i class="bi bi-plus-circle me-1"></i> Create Test Session
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><h6 class="dropdown-header">Choose a batch</h6></li>
                    @foreach($batches as $batch)
                        <li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#createSessionModal" data-batch-id="{{ $batch->id }}" data-batch-name="{{ $batch->batch_name }}">{{ $batch->batch_name }}</button></li>
                    @endforeach
                </ul>
            </div>
        @else
            <button type="button" class="btn btn-primary btn-sm" disabled title="Create a batch first">
                <i class="bi bi-plus-circle me-1"></i> Create Test Session
            </button>
        @endif
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
            <div class="card-body p-3 d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small text-uppercase fw-semibold">Total Sessions</div>
                    <div class="h3 mb-0 fw-bold text-dark">{{ $totalCount }}</div>
                    <div class="small text-muted mt-1">{{ $totalAssigned }} examinee(s) enrolled</div>
                </div>
                <div class="stat-icon p-2 rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                    <i class="bi bi-calendar3 fs-4"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card page-card shadow-xs border-start border-4 border-info h-100">
            <div class="card-body p-3 d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small text-uppercase fw-semibold">Scheduled</div>
                    <div class="h3 mb-0 fw-bold text-info">{{ $scheduledCount }}</div>
                    <div class="small text-muted mt-1">Awaiting start time</div>
                </div>
                <div class="stat-icon p-2 rounded-circle bg-info bg-opacity-10 text-info d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                    <i class="bi bi-clock-history fs-4"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card page-card shadow-xs border-start border-4 border-warning h-100">
            <div class="card-body p-3 d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small text-uppercase fw-semibold">In-Progress</div>
                    <div class="h3 mb-0 fw-bold text-warning">{{ $progressCount }}</div>
                    <div class="small text-muted mt-1">Live / check-in active</div>
                </div>
                <div class="stat-icon p-2 rounded-circle bg-warning bg-opacity-10 text-warning d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                    <i class="bi bi-play-circle-fill fs-4"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card page-card shadow-xs border-start border-4 border-success h-100">
            <div class="card-body p-3 d-flex justify-content-between align-items-center">
                <div>
                    <div class="text-muted small text-uppercase fw-semibold">Completed</div>
                    <div class="h3 mb-0 fw-bold text-success">{{ $completedCount }}</div>
                    <div class="small text-muted mt-1">{{ $totalSubmitted }} submission(s)</div>
                </div>
                <div class="stat-icon p-2 rounded-circle bg-success bg-opacity-10 text-success d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                    <i class="bi bi-check-circle-fill fs-4"></i>
                </div>
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
        <span class="badge bg-light text-dark border">{{ $batches->count() }} Batch(es) · {{ $sessions->count() }} Session(s)</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light" id="batchRosterHeaders">
                    <tr>
                        <th class="ps-3" colspan="2">Batch Name</th>
                        <th colspan="2">Batch Date</th>
                        <th colspan="2">Venue</th>
                        <th class="pe-3 text-end">Actions</th>
                    </tr>
                </thead>
                <thead class="table-light d-none" id="sessionRosterHeaders">
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
                    @foreach ($batches as $batch)
                        @php
                            $batchAssigned = $batch->sessions->sum('applicants_count');
                            $batchSubmitted = $batch->sessions->sum('submitted_count');
                        @endphp
                        <tr class="table-primary batch-row" style="cursor:pointer" onclick="toggleBatchSessions({{ $batch->id }})">
                            <td colspan="2" class="ps-3">
                                        <i id="batchIcon{{ $batch->id }}" class="bi bi-chevron-right me-2"></i>
                                        <i class="bi bi-collection-fill me-2"></i><strong>{{ $batch->batch_name }}</strong>
                                        <span class="ms-2 small text-muted">{{ $batch->sessions_count }} session(s) · {{ $batchAssigned }} examinee(s) · {{ $batchSubmitted }} submitted</span>
                            </td>
                            <td colspan="2">@if($batch->batch_date)<span class="badge bg-light text-dark border">{{ $batch->batch_date->format('M d, Y') }}</span>@else<span class="text-muted">—</span>@endif</td>
                            <td colspan="2">{{ $batch->room ?: '—' }}</td>
                            <td class="pe-3 text-end" onclick="event.stopPropagation()">
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createSessionModal" data-batch-id="{{ $batch->id }}" data-batch-name="{{ $batch->batch_name }}"><i class="bi bi-plus-circle me-1"></i>Add Session</button>
                                        <a class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener" href="{{ route('admin.admission.batches.print-masterlist', $batch) }}"><i class="bi bi-printer"></i> Masterlist</a>
                                        @unless($cycle->isCompleted())
                                            <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#editBatchModal{{ $batch->id }}"><i class="bi bi-pencil"></i></button>
                                            <form method="POST" action="{{ route('admin.admission.batches.destroy', $batch) }}" onsubmit="return confirm('Delete batch {{ $batch->batch_name }} and all {{ $batch->sessions_count }} session(s) inside it? Assigned applicants will be returned to the masterlist.')">@csrf @method('DELETE')<button class="btn btn-outline-danger btn-sm" title="Delete batch and all sessions inside"><i class="bi bi-trash"></i></button></form>
                                        @endunless
                                    </div>
                            </td>
                        </tr>
                        @forelse ($batch->sessions as $session)
                        @php
                            $assignedCount  = $session->applicants_count;
                            $submittedCount = $session->submitted_count;
                            $pct            = $assignedCount > 0 ? round(($submittedCount / $assignedCount) * 100) : 0;
                            $isDone         = $session->status === 'Completed';
                        @endphp
                        <tr class="batch-session batch-session-{{ $batch->id }} d-none {{ $session->status === 'In-Progress' ? 'table-warning bg-opacity-25' : ($isDone ? 'table-light opacity-75' : '') }}">
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
                                        {{ $session->start_time->format('M d, Y | h:i A') }}
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
                                @if ($session->status === 'Completed')
                                    <span class="badge bg-success">
                                        <i class="bi bi-check-circle me-1"></i> Completed
                                    </span>
                                @elseif ($session->status === 'In-Progress')
                                    <span class="badge bg-warning text-dark">
                                        <i class="bi bi-play-circle-fill me-1"></i> In-Progress
                                    </span>
                                @else
                                    <span class="badge bg-secondary">
                                        <i class="bi bi-calendar-event me-1"></i> Scheduled
                                    </span>
                                @endif
                            </td>
                            <td class="pe-3 text-end">
                                <div class="btn-group btn-group-sm">
                                    {{-- Start Test / End Session are now inside the Roster Monitor --}}
                                    <a href="{{ route('admin.admission.sessions.show', $session) }}"
                                       class="btn btn-primary btn-sm fw-semibold"
                                       title="Open Roster Monitor — Start, monitor, and end this session from inside">
                                        <i class="bi bi-speedometer2 me-1"></i>
                                        @if($session->status === 'Scheduled')
                                            Roster Monitor / Launch
                                        @elseif($session->status === 'In-Progress')
                                            <span class="spinner-grow spinner-grow-sm me-1 text-white" role="status" style="width:0.6rem;height:0.6rem;"></span>
                                            Roster Monitor (Live)
                                        @else
                                            View Roster
                                        @endif
                                    </a>

                                    <button type="button"
                                            class="btn btn-outline-dark btn-sm"
                                            data-bs-toggle="modal"
                                            data-bs-target="#sessionQrModal{{ $session->id }}"
                                            title="Session Venue Check-In QR Code">
                                        <i class="bi bi-qr-code"></i> Session QR
                                    </button>

                                    <a class="btn btn-outline-dark btn-sm"
                                       target="_blank"
                                       rel="noopener"
                                       href="{{ route('admin.admission.sessions.print-paper-answer-sheets', $session) }}"
                                       title="Print paper answer sheets for this session only">
                                        <i class="bi bi-file-earmark-text"></i> Print Papers
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
                                <h6 class="fw-bold mb-1">No sessions in this batch</h6>
                                <p class="small text-muted mb-3">Add Session A, Session B, and more inside this batch.</p>
                                @unless($cycle->isCompleted())
                                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createSessionModal">
                                        <i class="bi bi-plus-circle me-1"></i> Create Test Session
                                    </button>
                                @endunless
                            </td>
                        </tr>
                    @endforelse
                    @endforeach
                    @if($batches->isEmpty())
                        <tr><td colspan="7" class="text-center text-muted py-5"><i class="bi bi-collection fs-1 d-block mb-2 text-primary opacity-50"></i><h6 class="fw-bold mb-1">No Batches Configured</h6><p class="small mb-3">Create a batch first, then add sessions inside it.</p>@unless($cycle->isCompleted())<button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createBatchModal"><i class="bi bi-plus-circle me-1"></i>Add Batch</button>@endunless</td></tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ── MODALS ── --}}
@include('admin.admission.sessions.create_session_modal')

@include('admin.admission.batches.create_modal')
@foreach($batches as $batch)
    @include('admin.admission.batches.edit_modal', ['batch' => $batch])
@endforeach
@foreach($sessions as $session)
    @include('admin.admission.sessions.edit_session_modal', ['session' => $session])
    @include('admin.admission.sessions.qr_modal', ['session' => $session])
@endforeach

<script>
    function toggleBatchSessions(batchId) {
        const rows = document.querySelectorAll('.batch-session-' + batchId);
        const icon = document.getElementById('batchIcon' + batchId);
        const opening = Array.from(rows).some(row => row.classList.contains('d-none'));
        rows.forEach(row => row.classList.toggle('d-none', !opening));
        icon?.classList.toggle('bi-chevron-right', !opening);
        icon?.classList.toggle('bi-chevron-down', opening);
        const hasVisibleSessions = Array.from(document.querySelectorAll('.batch-session'))
            .some(row => !row.classList.contains('d-none'));
        document.getElementById('sessionRosterHeaders')?.classList.toggle('d-none', !hasVisibleSessions);
        document.getElementById('batchRosterHeaders')?.classList.toggle('d-none', hasVisibleSessions);
    }

    document.getElementById('createSessionModal')?.addEventListener('show.bs.modal', event => {
        const batchId = event.relatedTarget?.dataset?.batchId;
        const batchName = event.relatedTarget?.dataset?.batchName;
        if (batchId) event.currentTarget.querySelector('[name="admission_batch_id"]').value = batchId;
        const label = event.currentTarget.querySelector('[data-selected-batch]');
        if (label && batchName) label.textContent = batchName;
    });

    // Keep the session form open after a rejected range so the admin sees the
    // exact occupied masterlist number and can correct it immediately.
    @if($errors->has('start_number'))
        document.addEventListener('DOMContentLoaded', () => {
            const modalElement = document.getElementById('createSessionModal');
            if (!modalElement) return;

            const batchId = @json(old('admission_batch_id'));
            const batch = batchId
                ? document.querySelector(`[data-batch-id="${batchId}"]`)
                : null;
            if (batch) {
                modalElement.querySelector('[name="admission_batch_id"]').value = batchId;
                const label = modalElement.querySelector('[data-selected-batch]');
                if (label) label.textContent = batch.dataset.batchName || 'this batch';
            }
            bootstrap.Modal.getOrCreateInstance(modalElement).show();
        });
    @endif
    function updateProjectorClocks() {
        const now = new Date();
        const timeStr = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        document.querySelectorAll('.live-projector-clock').forEach(el => {
            el.textContent = timeStr;
        });
    }
    setInterval(updateProjectorClocks, 1000);
    updateProjectorClocks();

    function printSessionQr(sessionId) {
        const modal = document.getElementById('sessionQrModal' + sessionId);
        if (!modal) return;
        const img = modal.querySelector('img');
        if (!img) return;

        const printWindow = window.open('', '_blank', 'width=700,height=750');
        printWindow.document.write(`
            <html>
                <head>
                    <title>Print Admission Session QR Code</title>
                    <style>
                        body { font-family: Arial, sans-serif; text-align: center; padding: 40px; color: #111; }
                        h2 { margin-bottom: 4px; color: #0f3f97; }
                        p { margin: 4px 0; color: #555; }
                        .qr-box { margin: 24px auto; padding: 16px; border: 2px solid #ccc; display: inline-block; }
                        .url { font-family: monospace; font-size: 13px; color: #222; margin-top: 12px; }
                    </style>
                </head>
                <body onload="window.print(); window.close();">
                    <h2>Pangasinan State University - San Carlos Campus</h2>
                    <p><strong>PSU-CAT Venue Check-In QR Code</strong></p>
                    <div class="qr-box">
                        <img src="${img.src}" style="width: 280px; height: 280px;" alt="QR Code" />
                        <div class="url">${modal.querySelector('input[readonly]')?.value || ''}</div>
                    </div>
                    <p>Examinees scan this QR code with their mobile phone to verify attendance and begin their exam.</p>
                </body>
            </html>
        `);
        printWindow.document.close();
    }
</script>
@endsection
