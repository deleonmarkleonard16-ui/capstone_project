@extends('layouts.app')
@section('content')

{{-- ═════════════════════════════════════════════════════════════
     ADMIN WEB SCANNER & AUTOMATIC OMR ENGINE (/admin/admission/scan-paper)
     Guidance staff scans the QR header via webcam to identify applicant,
     then captures & evaluates shaded bubbles via optical recognition.
     ═════════════════════════════════════════════════════════════ --}}

<div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">Optical Answer Sheet Scanner &amp; OMR Engine</h1>
        <p class="text-muted mb-0 small">
            Scan the Applicant QR Header, verify examination credentials, and automatically grade shaded physical bubble sheets.
        </p>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.admission.masterlist') }}">
            <i class="bi bi-arrow-left me-1"></i> Return to Masterlist
        </a>
    </div>
</div>

<div id="paper-scanner"
     data-lookup="{{ route('admin.admission.scan-paper.lookup') }}"
     data-csrf="{{ csrf_token() }}"
     data-total-items="{{ $totalItems ?? 80 }}">

    {{-- ── 4-STEP PROGRESS INDICATOR ── --}}
    <div class="row g-2 mb-4">
        <div class="col-md-3">
            <div class="p-3 border rounded-3 bg-white shadow-sm h-100 step-card border-primary" id="step-ind-1">
                <div class="small text-muted fw-bold">STEP 1</div>
                <div class="fw-bold"><i class="bi bi-qr-code-scan me-1 text-primary"></i> Scan QR Header</div>
                <div class="small text-muted">Identify examinee via camera</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="col-12 p-3 border rounded-3 bg-white shadow-sm h-100 step-card" id="step-ind-2">
                <div class="small text-muted fw-bold">STEP 2</div>
                <div class="fw-bold"><i class="bi bi-person-check me-1 text-primary"></i> Verify Examinee</div>
                <div class="small text-muted">Match test permit &amp; photo ID</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="col-12 p-3 border rounded-3 bg-white shadow-sm h-100 step-card" id="step-ind-3">
                <div class="small text-muted fw-bold">STEP 3</div>
                <div class="fw-bold"><i class="bi bi-bounding-box me-1 text-primary"></i> Capture OMR Grid</div>
                <div class="small text-muted">Align 4 black corner markers</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="col-12 p-3 border rounded-3 bg-white shadow-sm h-100 step-card" id="step-ind-4">
                <div class="small text-muted fw-bold">STEP 4</div>
                <div class="fw-bold"><i class="bi bi-check2-circle me-1 text-success"></i> Score &amp; Record</div>
                <div class="small text-muted">Instant grading &amp; save</div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        {{-- Left Column: Camera / Capture --}}
        <div class="col-lg-6">
            <div class="card page-card shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h2 class="h6 mb-0 fw-bold"><i class="bi bi-camera me-1"></i> Live Webcam &amp; Document Feed</h2>
                </div>
                <div class="card-body p-3">
                    <div class="position-relative bg-dark rounded overflow-hidden d-flex align-items-center justify-content-center"
                         style="min-height:300px; max-height:420px;">
                        <video id="preview" autoplay muted playsinline
                               style="width:100%; height:100%; object-fit:contain; background:#000;"></video>
                        <div id="video-overlay" class="position-absolute text-white text-center p-3" style="pointer-events:none;">
                            <i class="bi bi-camera-video fs-1 d-block mb-2 opacity-50"></i>
                            <span class="small opacity-75">Click "Start Camera" to initialize optical scanner</span>
                        </div>
                    </div>

                    <div class="d-flex gap-2 my-3">
                        <button id="start-camera" type="button" class="btn btn-outline-primary flex-fill">
                            <i class="bi bi-camera-video-fill me-1"></i> Start Camera / Scan QR
                        </button>
                        <button id="capture" type="button" class="btn btn-primary flex-fill" disabled>
                            <i class="bi bi-camera me-1"></i> Capture Answer Matrix
                        </button>
                    </div>

                    <div class="border-top pt-3 mt-3">
                        <label class="form-label small fw-semibold text-muted">Or Upload Clear Photograph of Answer Sheet:</label>
                        <input id="photo" type="file" accept="image/png,image/jpeg,image/webp" class="form-control form-control-sm">
                    </div>

                    <div class="border-top pt-3 mt-3">
                        <label class="form-label small fw-semibold text-muted">Hardware Barcode/QR Scanner or Manual ID Lookup:</label>
                        <form id="lookup" class="d-flex gap-2">
                            <input id="code" class="form-control form-control-sm" maxlength="100"
                                   placeholder="Scan or type Application Number (e.g., CAT-26-0001)" required>
                            <button class="btn btn-outline-primary btn-sm">Lookup</button>
                        </form>
                    </div>

                    <div id="message" class="mt-3 small" role="status" aria-live="polite"></div>
                </div>
            </div>
        </div>

        {{-- Right Column: Examinee Confirmation & OMR Recognition --}}
        <div class="col-lg-6">
            {{-- Examinee Profile Box --}}
            <div class="card page-card shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h2 class="h6 mb-0 fw-bold"><i class="bi bi-person-badge me-1"></i> Identified Examinee Profile</h2>
                </div>
                <div class="card-body p-4">
                    <div id="identity-placeholder" class="text-center py-4 text-muted">
                        <i class="bi bi-upc-scan fs-1 d-block mb-2 opacity-25"></i>
                        <span>No applicant scanned yet. Point webcam at the QR code on the upper right of the answer sheet.</span>
                    </div>

                    <div id="identity-panel" class="d-none">
                        <div class="alert alert-success d-flex align-items-center gap-3 mb-3">
                            <i class="bi bi-check-circle-fill fs-3"></i>
                            <div>
                                <h3 class="h5 mb-0 fw-bold" id="applicant-name">Applicant Name</h3>
                                <div class="small opacity-75" id="applicant-app-no">Application No.</div>
                            </div>
                        </div>

                        <div class="p-3 bg-light rounded border mb-3 small">
                            <div class="mb-1"><strong>Program (1st Choice):</strong> <span id="applicant-course">—</span></div>
                            <div><strong>Proctor Check:</strong> Confirm physical permit &amp; student photo ID match this name.</div>
                        </div>

                        <div class="badge bg-primary px-3 py-2">Ready for Matrix Alignment</div>
                    </div>
                </div>
            </div>

            {{-- OMR Alignment Canvas & Form --}}
            <div class="card page-card shadow-sm">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h2 class="h6 mb-0 fw-bold"><i class="bi bi-grid-3x3 me-1"></i> Bubble Recognition Matrix</h2>
                    <button type="button" id="reset-points" class="btn btn-link btn-sm p-0 text-decoration-none">
                        <i class="bi bi-arrow-counterclockwise"></i> Reset Alignment
                    </button>
                </div>
                <div class="card-body p-3">
                    <p class="small text-muted mb-2">
                        Click the 4 black square corner markers in order: <strong>Top-Left → Top-Right → Bottom-Right → Bottom-Left</strong>.
                    </p>

                    <div class="border rounded bg-light text-center p-2 mb-3 overflow-hidden">
                        <canvas id="sheet" style="max-width:100%; border:1px dashed #aaa; cursor:crosshair;"></canvas>
                    </div>

                    <form id="answers" method="post" hidden>
                        @csrf
                        <div class="alert alert-info py-2 small mb-3">
                            <i class="bi bi-info-circle me-1"></i> Review all <strong>{{ $totalItems ?? 80 }}</strong> detected bubbles against the paper. Unshaded or ambiguous items require confirmation.
                        </div>

                        <div id="answer-review" class="row g-1 mb-3" style="max-height:220px; overflow-y:auto;"></div>

                        <div class="form-check mb-3 small">
                            <input class="form-check-input" type="checkbox" id="confirm-match" required>
                            <label class="form-check-label fw-semibold" for="confirm-match">
                                I verified the examinee's ID and confirmed all <strong>{{ $totalItems ?? 80 }}</strong> detected answers against the physical paper.
                            </label>
                        </div>

                        <button id="record" class="btn btn-success w-100 fw-bold py-2" disabled>
                            <i class="bi bi-check2-all me-1"></i> Submit &amp; Record Shaded Answers
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script type="module" src="{{ asset('js/admission-paper-scanner.js') }}"></script>
@endsection
