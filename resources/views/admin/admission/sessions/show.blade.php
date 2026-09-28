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
        <button type="button" class="btn btn-outline-dark btn-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#sessionQrModal{{ $session->id }}">
            <i class="bi bi-qr-code me-1"></i> Session QR
        </button>
        @if($session->status !== 'Completed' && !$cycle->isCompleted())
            <button type="button" class="btn btn-primary btn-sm fw-semibold js-open-omr" data-application-number="">
                <i class="bi bi-camera me-1"></i> OMR Web Scanner
            </button>
        @endif
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
                <div id="session-submitted-count" class="h3 mb-0 fw-bold text-success">{{ $submittedCount }}</div>
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
                <div id="session-pending-count" class="h3 mb-0 fw-bold text-warning">{{ $pendingCount }}</div>
                <div class="small text-muted mt-1">Awaiting completion</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card page-card shadow-xs border-start border-4 border-dark h-100">
            <div class="card-body p-3">
                <div class="text-muted small text-uppercase fw-semibold">Average Exam Score</div>
                <div id="session-average-score" class="h3 mb-0 fw-bold text-dark">{{ $avgScore }}</div>
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
                        <tr id="applicant-row-{{ $applicant->id }}" data-application-number="{{ $applicant->application_number }}">
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
                            <td class="js-exam-status">
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
                            <td class="js-score-stanine">
                                @if($applicant->exam_score !== null)
                                    <div class="fw-bold text-dark font-monospace">{{ number_format($applicant->exam_score, 2) }} / {{ (int) ($cycle->total_items ?: 80) }}</div>
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
                                    @if(!$applicant->submitted_at && $session->status !== 'Completed' && !$cycle->isCompleted())
                                        <button type="button" class="btn btn-primary btn-sm js-open-omr"
                                                data-application-number="{{ $applicant->application_number }}" title="Scan OMR Answer Sheet">
                                            <i class="bi bi-camera"></i> Scan OMR
                                        </button>
                                    @endif
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

<script>
    function updateProjectorClocks() {
        const now = new Date();
        const timeStr = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        document.querySelectorAll('.live-projector-clock').forEach(el => {
            el.textContent = timeStr;
        });
    }
    setInterval(updateProjectorClocks, 1000);
    updateProjectorClocks();

    function launchProjectorMode(sessionId) {
        const modalEl = document.getElementById('sessionQrModal' + sessionId);
        if (modalEl) {
            const bsModal = bootstrap.Modal.getInstance(modalEl);
            if (bsModal) bsModal.hide();
        }

        const projectorEl = document.getElementById('projectorScreen' + sessionId);
        if (projectorEl) {
            projectorEl.classList.remove('d-none');
            if (projectorEl.requestFullscreen) {
                projectorEl.requestFullscreen().catch(() => {});
            } else if (projectorEl.webkitRequestFullscreen) {
                projectorEl.webkitRequestFullscreen();
            }
        }
    }

    function exitProjectorMode(sessionId) {
        if (document.fullscreenElement || document.webkitFullscreenElement) {
            if (document.exitFullscreen) {
                document.exitFullscreen().catch(() => {});
            } else if (document.webkitExitFullscreen) {
                document.webkitExitFullscreen();
            }
        }
        const projectorEl = document.getElementById('projectorScreen' + sessionId);
        if (projectorEl) {
            projectorEl.classList.add('d-none');
        }
    }

    document.addEventListener('fullscreenchange', function() {
        if (!document.fullscreenElement) {
            document.querySelectorAll('.projector-overlay').forEach(el => {
                el.classList.add('d-none');
            });
        }
    });

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
