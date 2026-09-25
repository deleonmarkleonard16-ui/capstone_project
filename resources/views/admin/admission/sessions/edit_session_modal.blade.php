{{-- ═══════════════════════════════════════════════════════════
     PSU-CAT ADMISSION SESSION EDIT MODAL
     Strict Requirements:
     1. Session Name
     2. Start Date & Start Time (Single datetime-local input)
     3. Masterlist Range Assignment (Start Index # and End Index #)
     * Strictly NO End Time field
     ═══════════════════════════════════════════════════════════ --}}
<div class="modal fade" id="editSessionModal{{ $session->id }}" tabindex="-1" aria-labelledby="editSessionModalLabel{{ $session->id }}" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow">
            <div class="modal-header bg-light border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <div class="bg-secondary text-white rounded p-2 d-flex align-items-center justify-content-center" style="width:36px;height:36px;">
                        <i class="bi bi-pencil-square fs-5"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold text-dark mb-0" id="editSessionModalLabel{{ $session->id }}">Edit: {{ $session->session_name }}</h5>
                        <small class="text-muted">Update session configuration, schedule, or assigned masterlist range</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="{{ route('admin.admission.sessions.update', $session) }}">
                @csrf
                @method('PUT')
                <div class="modal-body p-4">
                    <div class="row g-3">
                        {{-- 1. Session Name --}}
                        <div class="col-md-7">
                            <label class="form-label form-label-sm fw-bold text-dark">
                                Session Name <span class="text-danger">*</span>
                            </label>
                            <input type="text"
                                   name="session_name"
                                   class="form-control"
                                   value="{{ old('session_name', $session->session_name) }}"
                                   required>
                        </div>

                        {{-- Status Selection --}}
                        <div class="col-md-5">
                            <label class="form-label form-label-sm fw-bold text-dark">
                                Session Status <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" name="status" required>
                                <option value="Scheduled" @selected(old('status', $session->status) === 'Scheduled')>Scheduled</option>
                                <option value="In-Progress" @selected(old('status', $session->status) === 'In-Progress')>In-Progress</option>
                                <option value="Completed" @selected(old('status', $session->status) === 'Completed')>Completed</option>
                            </select>
                        </div>

                        {{-- 2. Start Date & Start Time --}}
                        <div class="col-md-7">
                            <label class="form-label form-label-sm fw-bold text-dark">
                                Start Date &amp; Start Time <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-light text-primary">
                                    <i class="bi bi-calendar-event"></i>
                                </span>
                                <input type="datetime-local"
                                       name="start_time"
                                       class="form-control"
                                       value="{{ old('start_time', optional($session->start_time)->format('Y-m-d\TH:i')) }}"
                                       required>
                            </div>
                            <div class="form-text small">
                                Execution starts at Start Time and remains open until marked Completed.
                            </div>
                        </div>

                        {{-- Room / Venue --}}
                        <div class="col-md-5">
                            <label class="form-label form-label-sm fw-bold text-dark">
                                Room / Testing Center
                            </label>
                            <input type="text"
                                   name="room"
                                   class="form-control"
                                   placeholder="e.g. PSU IT Building Rm 204"
                                   value="{{ old('room', $session->room) }}">
                        </div>

                        {{-- 3. Masterlist Range Assignment --}}
                        <div class="col-12 mt-2">
                            <div class="p-3 border rounded-3 bg-light bg-opacity-75">
                                <label class="form-label form-label-sm fw-bold text-dark mb-1">
                                    <i class="bi bi-person-lines-fill text-primary me-1"></i>
                                    Masterlist Range Assignment <span class="text-danger">*</span>
                                </label>
                                <p class="text-muted small mb-3">
                                    Currently assigned examinees: <strong>{{ $session->applicants_count ?? $session->applicants()->count() }}</strong>. Modifying the index range will reassign the target examinees.
                                </p>

                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <label class="form-label form-label-sm fw-semibold text-secondary">
                                            Start Index # <span class="text-danger">*</span>
                                        </label>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text font-monospace">#</span>
                                            <input type="number"
                                                   min="1"
                                                   name="start_number"
                                                   class="form-control font-monospace"
                                                   value="{{ old('start_number', $session->start_number) }}"
                                                   required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label form-label-sm fw-semibold text-secondary">
                                            End Index # <span class="text-danger">*</span>
                                        </label>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text font-monospace">#</span>
                                            <input type="number"
                                                   min="1"
                                                   name="end_number"
                                                   class="form-control font-monospace"
                                                   value="{{ old('end_number', $session->end_number) }}"
                                                   required>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm px-3 fw-semibold">
                        <i class="bi bi-save me-1"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
