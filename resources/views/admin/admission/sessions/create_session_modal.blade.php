{{-- ═══════════════════════════════════════════════════════════
     PSU-CAT ADMISSION SESSION CREATION MODAL
     Strict Requirements:
     1. Session Name (e.g. "Session A - Batch 1")
     2. Start Date & Start Time (Single datetime-local input)
     3. Masterlist Range Assignment (Start Index # and End Index #)
     * Strictly NO End Time field
     ═══════════════════════════════════════════════════════════ --}}
<div class="modal fade" id="createSessionModal" tabindex="-1" aria-labelledby="createSessionModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow">
            <div class="modal-header bg-light border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <div class="bg-primary text-white rounded p-2 d-flex align-items-center justify-content-center" style="width:36px;height:36px;">
                        <i class="bi bi-calendar-plus fs-5"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-dark mb-0" id="createSessionModalLabel">Create Admission Test Session</h5>
                        <small class="text-muted">Configure a session within <strong data-selected-batch>this batch</strong>.</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="{{ route('admin.admission.sessions.store') }}" id="createSessionForm">
                @csrf
                <div class="modal-body p-4">
                    {{-- Notice Alert --}}
                    <div class="alert alert-primary bg-primary bg-opacity-10 border-primary border-opacity-25 d-flex align-items-start gap-2 py-2 px-3 mb-4 rounded-3">
                        <i class="bi bi-info-circle-fill text-primary fs-5 mt-1"></i>
                        <div class="small">
                            <strong>Session Lifecycle:</strong> Session execution starts at the designated <strong>Start Time</strong> and remains open until manually marked as <strong>'Completed'</strong> by the Guidance Admin or until all assigned applicants submit their exams.
                        </div>
                    </div>

                    <div class="row g-3">
                        <input type="hidden" name="admission_batch_id" value="{{ old('admission_batch_id') }}">

                        {{-- 1. Session Name --}}
                        <div class="col-12">
                            <label class="form-label form-label-sm fw-bold text-dark">
                                1. Session Name <span class="text-danger">*</span>
                            </label>
                            <input type="text"
                                   name="session_name"
                                   id="session_name"
                                   class="form-control"
                                   placeholder="e.g., Session A"
                                   value="{{ old('session_name') }}"
                                   required>
                            <div class="form-text small">Descriptive batch or session identifier for examinees and proctors.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label form-label-sm fw-bold text-dark" for="create_start_time">
                                2. Start Time <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-primary">
                                    <i class="bi bi-clock"></i>
                                </span>
                                <input type="time"
                                       name="start_time"
                                       id="create_start_time"
                                       class="form-control"
                                       value="{{ old('start_time', now('Asia/Manila')->addHour()->format('H:i')) }}"
                                       required>
                            </div>
                            <div class="form-text small">The batch date and venue will be used automatically.</div>
                        </div>

                        {{-- 3. Masterlist Range Assignment --}}
                        <div class="col-12 mt-2">
                            <div class="p-3 border rounded-3 bg-light bg-opacity-75">
                                <label class="form-label form-label-sm fw-bold text-dark mb-1 d-flex align-items-center justify-content-between">
                                    <span>
                                        <i class="bi bi-person-lines-fill text-primary me-1"></i>
                                        3. Masterlist Range Assignment <span class="text-danger">*</span>
                                    </span>
                                    @if(isset($totalApplicants) && $totalApplicants > 0)
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                            Total in Masterlist: {{ $totalApplicants }} Examinees
                                        </span>
                                    @endif
                                </label>
                                <p class="text-muted small mb-3">
                                    Specify the numerical slice of examinees to automatically enroll into this test session.
                                </p>

                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <label class="form-label form-label-sm fw-semibold text-secondary" for="create_start_number">
                                            Start Index # <span class="text-danger">*</span>
                                        </label>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text font-monospace">#</span>
                                            <input type="number"
                                                   min="1"
                                                   name="start_number"
                                                   id="create_start_number"
                                                   class="form-control font-monospace"
                                                   placeholder="e.g. 1"
                                                   value="{{ old('start_number', 1) }}"
                                                   required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label form-label-sm fw-semibold text-secondary" for="create_end_number">
                                            End Index # <span class="text-danger">*</span>
                                        </label>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text font-monospace">#</span>
                                            <input type="number"
                                                   min="1"
                                                   name="end_number"
                                                   id="create_end_number"
                                                   class="form-control font-monospace"
                                                   placeholder="e.g. 50"
                                                   value="{{ old('end_number', 50) }}"
                                                   required>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-2 text-muted small" id="rangeSummaryPreview">
                                    <i class="bi bi-arrow-right-short text-primary"></i>
                                    Will assign examinees from masterlist sequence into this session.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm px-3 fw-semibold">
                        <i class="bi bi-plus-circle me-1"></i> Create Session
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
