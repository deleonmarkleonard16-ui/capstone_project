<div class="modal fade" id="create-bundled-modal" tabindex="-1" aria-labelledby="createBundledModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold" id="createBundledModalLabel">
                    <i class="bi bi-people-fill me-2"></i>Create Bundled / Batch Request
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ isset($moduleKey) && $moduleKey ? route(auth()->user()->role.'.'.$moduleKey.'.batches.store') : route(auth()->user()->role.'.guidance-batches.store') }}" method="post" enctype="multipart/form-data">
                @csrf
                <div class="modal-body p-4">
                    <div class="alert alert-primary d-flex align-items-center mb-4" role="alert">
                        <i class="bi bi-info-circle-fill fs-5 me-2 flex-shrink-0"></i>
                        <div>
                            Import a class or cohort roster via CSV to create a synchronized testing batch. A single Master Proctoring Pass and QR code will be generated for proctors to launch the exam simultaneously.
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="modal_batch_name" class="form-label fw-semibold">Batch Name / Reference <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="modal_batch_name" name="batch_name" value="{{ old('batch_name') }}" placeholder="e.g. BATCH-BSIT-3A" required maxlength="255">
                            <div class="form-text">A unique identifier for this class or group.</div>
                        </div>

                        <div class="col-md-6">
                            <label for="modal_course" class="form-label fw-semibold">Course / Program <span class="text-danger">*</span></label>
                            <select class="form-select" id="modal_course" name="course" required><option value="">Select a program</option>@foreach(\App\Support\CourseCatalog::activeOptions() as $code=>$title)<option value="{{ $code }}" @selected(old('course') === $code)>{{ $title }} ({{ $code }})</option>@endforeach</select>
                        </div>

                        <div class="col-md-6">
                            <label for="modal_year_section" class="form-label fw-semibold">Year &amp; Section <span class="text-muted small">(Optional)</span></label>
                            <input type="text" class="form-control" id="modal_year_section" name="year_section" value="{{ old('year_section') }}" placeholder="e.g. 3-A" maxlength="50">
                        </div>

                        <div class="col-md-6">
                            <label for="modal_reason" class="form-label fw-semibold">Reason for Request <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="modal_reason" name="reason_for_request" value="{{ old('reason_for_request', 'Field Study / Practicum') }}" placeholder="e.g. Practicum / Field Study / OJT" required maxlength="255">
                        </div>

                        <div class="col-md-12">
                            <label for="modal_test_type" class="form-label fw-semibold">Test Category / Assessment <span class="text-danger">*</span></label>
                            @php
                                $selectedCategory = old('test_type');
                                if (!$selectedCategory && isset($moduleKey)) {
                                    $selectedCategory = match($moduleKey) {
                                        'personality' => 'Personality Test',
                                        'career' => 'Career Test',
                                        default => 'Psychological Assessment',
                                    };
                                }
                            @endphp
                            <select id="modal_test_type" name="test_type" class="form-select" required @if(isset($moduleKey) && $moduleKey) disabled @endif>
                                @foreach(\App\Services\GuidanceCategories::LABELS as $catKey => $label)
                                    <option value="{{ $label }}" @selected(($selectedCategory ?? 'Psychological Assessment') === $label)>
                                        {{ $label }}
                                        @if($catKey === 'personality') (BFPI)
                                        @elseif($catKey === 'career') (RIASEC Career Interest)
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                            @if(isset($moduleKey) && $moduleKey)<input type="hidden" name="test_type" value="{{ \App\Services\GuidanceCategories::LABELS[$moduleKey] }}">@endif
                        </div>

                        <div class="col-md-12">
                            <label for="modal_roster" class="form-label fw-semibold">Class Roster (CSV File) <span class="text-danger">*</span></label>
                            <input class="form-control @error('roster') is-invalid @enderror" type="file" id="modal_roster" name="roster" accept=".csv,text/csv" required>
                            @error('roster')
                                <div class="invalid-feedback d-block fw-semibold">{{ $message }}</div>
                            @enderror
                            <div class="form-text mt-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <span><strong>Accepted columns:</strong> <code>student_id</code>, <code>first_name</code>, <code>middle_name</code> (optional), <code>last_name</code>. (Max 500 students).</span>
                                <a href="data:text/csv;charset=utf-8,student_id%2Cfirst_name%2Cmiddle_name%2Clast_name%0A23-SC-4143%2CJUAN%2CSANTOS%2CDELA%20CRUZ%0A23-SC-4144%2CMARIA%2C%2CCLARA" download="roster_template.csv" class="btn btn-sm btn-outline-secondary">
                                    <i class="bi bi-download me-1"></i>Download Template CSV
                                </a>
                            </div>
                        </div>

                        <div class="col-md-12">
                            <div class="p-3 bg-light border rounded small">
                                <span class="fw-semibold text-dark">Example CSV structure:</span>
                                <pre class="mb-0 mt-1 p-2 bg-white border rounded text-secondary font-monospace">student_id,first_name,middle_name,last_name
23-SC-4143,JUAN,SANTOS,DELA CRUZ
23-SC-4144,MARIA,,CLARA</pre>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light px-4 py-3">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4 fw-semibold">
                        <i class="bi bi-cloud-arrow-up-fill me-1"></i> Import Batch &amp; Generate Pass
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@if($errors->any())
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var modalEl = document.getElementById('create-bundled-modal');
        if (modalEl && typeof bootstrap !== 'undefined') {
            var modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
            modal.show();
        }
    });
</script>
@endif
