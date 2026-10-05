{{-- ═══════════════════════════════════════════════════════════
     PSU-CAT ADMISSION SESSION VENUE CHECK-IN QR MODAL & PROJECTOR
     Target URL: /admission/checkin/{session_token}
     ═══════════════════════════════════════════════════════════ --}}
@php
    $checkinUrl = url('/admission/checkin/' . $session->qr_token);
    $qrDataUri = app(\App\Services\GuidanceQrService::class)->dataUri($checkinUrl);
@endphp

<div class="modal fade" id="sessionQrModal{{ $session->id }}" tabindex="-1" aria-labelledby="sessionQrModalLabel{{ $session->id }}" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow-lg border-0 rounded-4 overflow-hidden">
            {{-- Modal Header --}}
            <div class="modal-header bg-primary text-white py-3 px-4">
                <div class="d-flex align-items-center gap-3">
                    <div class="bg-white bg-opacity-20 rounded-circle p-2 d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                        <i class="bi bi-qr-code-scan fs-4 text-warning"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold mb-0 text-white" id="sessionQrModalLabel{{ $session->id }}">
                            Session Venue Check-In QR Code
                        </h5>
                        <small class="text-white-50">
                            {{ $session->session_name }} &bull; {{ $session->room ?: 'Main Testing Hall' }}
                        </small>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-4">
                <div class="row align-items-center g-4">
                    {{-- QR Code Display --}}
                    <div class="col-md-5 text-center">
                        <div class="bg-white p-3 border rounded-3 shadow-sm d-inline-block">
                            <img src="{{ $qrDataUri }}"
                                 alt="Session Check-In QR Code"
                                 class="img-fluid rounded"
                                 style="width: 220px; height: 220px; object-fit: contain;">
                        </div>
                        <div class="mt-2">
                            <span class="badge bg-light text-secondary border font-monospace" style="font-size: 11px;">
                                Target: /admission/checkin/{{ substr($session->qr_token, 0, 10) }}...
                            </span>
                        </div>
                    </div>

                    {{-- Session Details & Controls --}}
                    <div class="col-md-7">
                        <h6 class="fw-bold text-dark mb-2">Examinee Entrance Check-In</h6>
                        <p class="text-muted small mb-3">
                            Project this QR code on screens/projectors at the venue or print it at the entrance. Examinees scan this code with their phones to verify attendance and proceed to the digital lockdown exam.
                        </p>

                        <div class="card bg-light border-0 rounded-3 p-3 mb-3">
                            <div class="row g-2 small">
                                <div class="col-6">
                                    <span class="text-muted d-block text-uppercase" style="font-size: 10px;">Session Name</span>
                                    <strong class="text-dark">{{ $session->session_name }}</strong>
                                </div>
                                <div class="col-6">
                                    <span class="text-muted d-block text-uppercase" style="font-size: 10px;">Venue / Room</span>
                                    <strong class="text-dark">{{ $session->room ?: 'Main Testing Hall' }}</strong>
                                </div>
                                <div class="col-6">
                                    <span class="text-muted d-block text-uppercase" style="font-size: 10px;">Start Time</span>
                                    <strong class="text-dark">{{ optional($session->start_time)->format('M d, Y · h:i A') ?: 'TBA' }}</strong>
                                </div>
                                <div class="col-6">
                                    <span class="text-muted d-block text-uppercase" style="font-size: 10px;">Assigned Range</span>
                                    <span class="badge bg-primary bg-opacity-10 text-primary font-monospace">#{{ $session->start_number }} – #{{ $session->end_number }}</span>
                                </div>
                            </div>
                        </div>

                        {{-- URL Input with Copy --}}
                        <div class="mb-3">
                            <label class="form-label text-muted small fw-semibold mb-1">Direct Venue Check-In Link</label>
                            <div class="input-group input-group-sm">
                                <input type="text"
                                       id="checkinUrlInput{{ $session->id }}"
                                       class="form-control font-monospace"
                                       value="{{ $checkinUrl }}"
                                       readonly>
                                <button type="button"
                                        class="btn btn-outline-secondary"
                                        onclick="navigator.clipboard.writeText('{{ $checkinUrl }}'); this.innerHTML='<i class=\'bi bi-check-lg\'></i> Copied'; setTimeout(() => this.innerHTML='<i class=\'bi bi-clipboard\'></i> Copy', 2000);">
                                    <i class="bi bi-clipboard"></i> Copy
                                </button>
                                <a href="{{ $checkinUrl }}" target="_blank" class="btn btn-outline-primary" title="Open Check-In Form in New Tab">
                                    <i class="bi bi-box-arrow-up-right"></i>
                                </a>
                            </div>
                        </div>

                        {{-- Action Buttons --}}
                        <div class="d-flex flex-wrap gap-2">
                            <a href="{{ route('admin.admission.sessions.project-qr', $session) }}"
                               target="_blank" rel="noopener"
                               class="btn btn-warning fw-bold text-dark btn-sm px-3 shadow-sm">
                                <i class="bi bi-projector-fill me-1"></i> Display Fullscreen / Project
                            </a>
                            <a href="{{ route('admin.admission.sessions.print-qr', $session) }}"
                               target="_blank" rel="noopener"
                               class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-printer me-1"></i> Print QR
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer bg-light py-2 px-4">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════
     DEDICATED FULLSCREEN PROJECTOR SCREEN OVERLAY
     ═══════════════════════════════════════════════════════════ --}}
<div id="projectorScreen{{ $session->id }}"
     class="projector-overlay d-none position-fixed top-0 start-0 w-100 h-100 bg-dark text-white p-4 p-md-5 d-flex flex-column justify-content-between"
     style="z-index: 99999; background: radial-gradient(circle at center, #0e2a6d 0%, #061539 100%) !important;">

    {{-- Top Bar: Campus Branding & Exit Button --}}
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
        <div class="d-flex align-items-center gap-3">
            <img src="{{ asset('images/psu-logo.png') }}" alt="PSU Logo" style="width: 56px; height: 56px; object-fit: contain;">
            <div>
                <h4 class="fw-bold mb-0 text-warning text-uppercase letter-spacing-1">
                    Pangasinan State University – San Carlos Campus
                </h4>
                <div class="text-light opacity-75 small text-uppercase">
                    Digital Management System for Guidance Testing and Admission &bull; PSU-CAT
                </div>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <div class="badge bg-white bg-opacity-10 text-warning fs-6 px-3 py-2 font-monospace live-projector-clock">
                --:--:--
            </div>
            <button type="button"
                    class="btn btn-outline-light btn-sm fw-bold px-3 py-2"
                    onclick="exitProjectorMode('{{ $session->id }}')">
                <i class="bi bi-fullscreen-exit me-1"></i> Exit Fullscreen <span class="badge bg-secondary ms-1">ESC</span>
            </button>
        </div>
    </div>

    {{-- Center: QR Code & Session Info --}}
    <div class="row align-items-center justify-content-center my-auto py-4">
        <div class="col-lg-5 text-center mb-4 mb-lg-0">
            <div class="bg-white p-4 rounded-4 shadow-lg d-inline-block border border-4 border-warning">
                <img src="{{ $qrDataUri }}"
                     alt="Session Check-In QR Code"
                     class="img-fluid"
                     style="width: min(340px, 65vw); height: min(340px, 65vw); object-fit: contain;">
            </div>
            <div class="mt-3 text-warning fw-bold fs-5">
                <i class="bi bi-camera-fill me-1"></i> Scan with Phone Camera to Check In
            </div>
        </div>

        <div class="col-lg-6 text-start ps-lg-4">
            <div class="badge bg-warning text-dark text-uppercase fs-6 px-3 py-2 fw-bold mb-3">
                <i class="bi bi-door-open-fill me-1"></i> Venue Check-In Active
            </div>
            <h1 class="display-5 fw-bold text-white mb-2">
                {{ $session->session_name }}
            </h1>
            <div class="fs-4 text-warning mb-3">
                <i class="bi bi-geo-alt-fill me-1 text-danger"></i> {{ $session->room ?: 'Main Testing Hall' }}
            </div>

            <div class="card bg-white bg-opacity-10 border border-light border-opacity-25 rounded-3 p-3 mb-4 text-white">
                <div class="row g-3">
                    <div class="col-sm-6">
                        <div class="text-white-50 text-uppercase small">Exam Schedule</div>
                        <div class="fs-6 fw-bold text-white">
                            {{ optional($session->start_time)->format('F d, Y · h:i A') ?: 'Scheduled Today' }}
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-white-50 text-uppercase small">Assigned Examinee Range</div>
                        <div class="fs-6 fw-bold text-warning font-monospace">
                            #{{ $session->start_number }} – #{{ $session->end_number }}
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-dark bg-opacity-50 border border-secondary rounded-3 p-3 text-light small">
                <div class="fw-bold text-warning mb-1">
                    <i class="bi bi-info-circle-fill me-1"></i> Examinee Instructions:
                </div>
                <ol class="mb-0 ps-3">
                    <li>Scan the QR code on the screen with your smartphone camera or QR scanner.</li>
                    <li>Enter your registered First Name and Last Name on the Attendance Verification page.</li>
                    <li>Upon successful roster verification, you will immediately enter the secure digital lockdown examination.</li>
                </ol>
            </div>
        </div>
    </div>

    {{-- Bottom Bar: Direct URL --}}
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 pt-3 border-top border-light border-opacity-25 text-white-50 small">
        <div>
            <span>If QR scanner is unavailable, open browser and go to:</span>
            <strong class="text-warning font-monospace ms-1">{{ $checkinUrl }}</strong>
        </div>
        <div class="text-end">
            Press <kbd class="bg-secondary text-white">ESC</kbd> to exit projection mode
        </div>
    </div>
</div>
