@extends('layouts.app')
@section('content')

{{-- ═══════════════════════════════════════════════════════
     ADMISSION MASTERLIST — matching the provided UI screenshot
     15-column table with 7-filter bar, Edit modal with
     READ-ONLY exam score protection.
     ═══════════════════════════════════════════════════════ --}}

{{-- Top page header --}}
<div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
    <div>
        <h1 class="h3 mb-1">Admission Masterlist</h1>
        <p class="text-muted mb-0 small">
            Current admission cycle: <strong>{{ $cycle->name }}</strong>
            @if(session('import_errors'))
                &nbsp;·&nbsp;
                <span class="text-warning">
                    {{ count(session('import_errors')) }} import warning(s)
                </span>
            @endif
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-outline-primary btn-sm" href="{{ route('admin.admission.encoding-sheet') }}">
            <i class="bi bi-grid-3x3 me-1"></i> Masterlist Encoding Sheet
        </a>
        <a class="btn btn-outline-primary btn-sm" href="{{ route('admin.sessions.index') }}">
            <i class="bi bi-calendar3 me-1"></i> Open Test Sessions
        </a>
        <a class="btn btn-primary btn-sm"
           href="{{ route('admin.admission.report', ['type'=>'summary','format'=>'docx']) }}">
            <i class="bi bi-file-earmark-word me-1"></i> DOCX Export
        </a>
    </div>
</div>

@if(session('import_errors'))
    <div class="alert alert-warning">
        <strong>Import warnings:</strong> {{ implode('; ', session('import_errors')) }}
    </div>
@endif

{{-- ── FILTER BAR ── --}}
<div class="card page-card mb-3">
    <div class="card-body p-3">
        <div class="fw-semibold small mb-2 text-muted">Admission Masterlist
            <span class="text-dark ms-2">{{ $applicants->total() }} record(s)</span>
        </div>
        <form method="get" action="{{ route('admin.admission.masterlist') }}" id="masterlist-filters">
            <div class="row g-2 mb-2">
                {{-- Cycle --}}
                <div class="col-md-2">
                    <select class="form-select form-select-sm" name="cycle_id">
                        <option value="">{{ $cycle->name }}</option>
                    </select>
                </div>
                {{-- Search --}}
                <div class="col-md-3">
                    <input class="form-control form-control-sm" name="search"
                           value="{{ request('search') }}"
                           placeholder="Search by no., name, or course">
                </div>
                {{-- Batch groups --}}
                <div class="col-md-3">
                    <select class="form-select form-select-sm" name="batch_group">
                        <option value="">All batch groups</option>
                        @foreach($batchGroups as $bg)
                            <option value="{{ $bg }}" @selected(request('batch_group') === $bg)>{{ $bg }}</option>
                        @endforeach
                    </select>
                </div>
                {{-- Course --}}
                <div class="col-md-4">
                    <select class="form-select form-select-sm" name="course">
                        <option value="">All courses</option>
                        @foreach($courses as $code => $title)
                            <option value="{{ $code }}" @selected(request('course') === $code)>{{ $title }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="row g-2 align-items-end">
                {{-- Session --}}
                <div class="col-md-2">
                    <select class="form-select form-select-sm" name="session_filter">
                        <option value="">All sessions</option>
                        @foreach($sessionOptions as $s)
                            <option value="{{ $s }}" @selected(request('session_filter') === $s)>{{ $s }}</option>
                        @endforeach
                    </select>
                </div>
                {{-- Exam records --}}
                <div class="col-md-2">
                    <select class="form-select form-select-sm" name="exam_filter">
                        <option value="">All exam records</option>
                        <option value="submitted" @selected(request('exam_filter') === 'submitted')>Submitted</option>
                        <option value="not_submitted" @selected(request('exam_filter') === 'not_submitted')>Not submitted</option>
                    </select>
                </div>
                {{-- Interview --}}
                <div class="col-md-2">
                    <select class="form-select form-select-sm" name="interview_filter">
                        <option value="">All interview records</option>
                        <option value="scored" @selected(request('interview_filter') === 'scored')>Scored</option>
                        <option value="pending" @selected(request('interview_filter') === 'pending')>Pending</option>
                    </select>
                </div>
                {{-- Stanine --}}
                <div class="col-md-2">
                    <select class="form-select form-select-sm" name="stanine">
                        <option value="">All stanines</option>
                        @for($s = 1; $s <= 9; $s++)
                            <option value="{{ $s }}" @selected(request('stanine') == $s)>Stanine {{ $s }}</option>
                        @endfor
                    </select>
                </div>
                {{-- Sort --}}
                <div class="col-md-3">
                    <select class="form-select form-select-sm" name="sort">
                        <option value="course_last_name" @selected(request('sort','course_last_name') === 'course_last_name')>Sort by Course</option>
                        <option value="gwa_desc" @selected(request('sort') === 'gwa_desc')>Highest GWA</option>
                        <option value="total_desc" @selected(request('sort') === 'total_desc')>Highest Total Score</option>
                        <option value="last_name" @selected(request('sort') === 'last_name')>Last Name A–Z</option>
                    </select>
                </div>
                <div class="col-md-1">
                    <button class="btn btn-primary btn-sm w-100">
                        <i class="bi bi-funnel"></i> Apply
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- ── APPLICANT TABLE ── --}}
<div class="card page-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0" id="masterlist-table">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">No.</th>
                        <th>Last Name</th>
                        <th>Given Name</th>
                        <th>Middle Name</th>
                        <th>Course (1st Choice)</th>
                        <th>Sex</th>
                        <th>4PS/OSY/IP/PWD/SP</th>
                        <th>CMFL</th>
                        <th>GWA</th>
                        <th>Test</th>
                        <th>Stanine</th>
                        <th>Interview</th>
                        <th>Status</th>
                        <th>Exam Submitted</th>
                        <th>Total</th>
                        <th class="pe-3">Action</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($applicants as $i => $applicant)
                    <tr>
                        <td class="ps-3">{{ $applicants->firstItem() + $i }}</td>
                        <td class="fw-semibold">{{ mb_strtoupper($applicant->last_name) }}</td>
                        <td>{{ $applicant->first_name }}</td>
                        <td>{{ $applicant->middle_name ?: '–' }}</td>
                        <td style="max-width:200px">
                            <span title="{{ \App\Support\CourseCatalog::OPTIONS[$applicant->course_choice] ?? $applicant->course_choice }}">
                                {{ \App\Support\CourseCatalog::OPTIONS[$applicant->course_choice] ?? $applicant->course_choice }}
                            </span>
                        </td>
                        <td>{{ $applicant->sex ?: '–' }}</td>
                        <td>{{ $applicant->special_group ?: 'N/A' }}</td>
                        <td>{{ $applicant->cmfl ?: 'N/A' }}</td>
                        <td>{{ $applicant->gwa ?? '–' }}</td>
                        <td>{{ $applicant->exam_score !== null ? number_format($applicant->exam_score, 2) : '–' }}</td>
                        <td>
                            @if ($applicant->stanine_score)
                                <span class="badge {{ $applicant->stanine_score >= $cycle->passing_stanine ? 'bg-success' : 'bg-danger' }}">
                                    {{ $applicant->stanine_score }}
                                </span>
                            @else
                                <span class="text-muted">–</span>
                            @endif
                        </td>
                        <td>{{ $applicant->interview_score !== null ? number_format($applicant->interview_score, 1) : '–' }}</td>
                        <td>
                            @php
                                $status = $applicant->qualification_status ?? 'Pending';
                                $sessionLabel = $applicant->session_label ?? 'Unassigned';
                                $batchLabel = $applicant->batch_label ?? '';
                            @endphp
                            <div>
                                <span class="badge {{ $status === 'Qualified' ? 'bg-success' : ($status === 'Pending' ? 'bg-secondary' : 'bg-danger') }}">
                                    {{ $applicant->submitted_at ? $status : 'Absent in Exam' }}
                                </span>
                                <div class="text-muted" style="font-size:11px">{{ $sessionLabel }}</div>
                                @if($batchLabel)
                                    <div class="text-muted" style="font-size:11px">{{ $batchLabel }}</div>
                                @endif
                            </div>
                        </td>
                        <td>
                            @if ($applicant->submitted_at)
                                <span class="badge bg-success-subtle text-success border border-success-subtle">Yes</span>
                            @else
                                <span class="text-muted">No</span>
                            @endif
                        </td>
                        <td class="fw-bold">
                            {{ $applicant->total_score !== null ? number_format($applicant->total_score, 2) : '–' }}
                        </td>
                        <td class="pe-3">
                            <button class="btn btn-sm btn-outline-primary"
                                    data-bs-toggle="modal"
                                    data-bs-target="#edit-applicant-{{ $applicant->id }}">
                                <i class="bi bi-pencil"></i> Edit
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="16" class="text-center text-muted py-5">
                            <i class="bi bi-inbox fs-2 d-block mb-2 opacity-25"></i>
                            No applicants found for the selected filters.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-3 py-2">
            {{ $applicants->withQueryString()->links('pagination::bootstrap-5') }}
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════
     EDIT APPLICANT MODALS
     Exam score is strictly DISABLED/READ-ONLY
     ══════════════════════════════════════ --}}
@foreach ($applicants as $applicant)
<div class="modal fade" id="edit-applicant-{{ $applicant->id }}"
     tabindex="-1" aria-labelledby="edit-label-{{ $applicant->id }}" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title h5" id="edit-label-{{ $applicant->id }}">
                    Edit Applicant — {{ $applicant->full_name }}
                </h3>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" action="{{ route('admin.admission.applicants.save', $applicant) }}">
                @csrf
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Application Number</label>
                            <input name="application_number" value="{{ $applicant->application_number }}"
                                   class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Last Name</label>
                            <input name="last_name" value="{{ $applicant->last_name }}"
                                   class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">First Name</label>
                            <input name="first_name" value="{{ $applicant->first_name }}"
                                   class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Middle Name</label>
                            <input name="middle_name" value="{{ $applicant->middle_name }}"
                                   class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Course (1st Choice)</label>
                            <select name="course_choice" class="form-select" required>
                                @foreach ($courses as $code => $title)
                                    <option value="{{ $code }}"
                                        @selected($applicant->course_choice === $code)>
                                        {{ $title }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Sex</label>
                            <select name="sex" class="form-select">
                                <option value="">—</option>
                                <option value="Male"   @selected($applicant->sex === 'Male')>Male</option>
                                <option value="Female" @selected($applicant->sex === 'Female')>Female</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">4PS/OSY/IP/PWD/SP</label>
                            <input name="special_group" value="{{ $applicant->special_group }}"
                                   class="form-control" placeholder="N/A">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">CMFL</label>
                            <input name="cmfl" value="{{ $applicant->cmfl }}"
                                   class="form-control" placeholder="N/A">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">GWA</label>
                            <input type="number" step="0.01" min="75" max="100"
                                   name="gwa" value="{{ $applicant->gwa }}"
                                   class="form-control" placeholder="e.g. 92.50">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Interview Score</label>
                            <input type="number" step="0.01" min="0" max="100"
                                   name="interview_score" value="{{ $applicant->interview_score }}"
                                   class="form-control" placeholder="Optional">
                        </div>

                        {{-- ══ READ-ONLY EXAM SCORE SECTION ══ --}}
                        <div class="col-12">
                            <hr class="my-1">
                            <div class="alert alert-light border d-flex align-items-center gap-2 py-2">
                                <i class="bi bi-lock-fill text-secondary"></i>
                                <small class="text-muted">
                                    The fields below are <strong>computed automatically</strong> from submitted exam answers and cannot be manually edited.
                                </small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">
                                Computed Exam Score
                                <i class="bi bi-lock text-muted ms-1" title="Auto-computed — read only"></i>
                            </label>
                            <input value="{{ $applicant->exam_score !== null ? number_format($applicant->exam_score, 2) : 'Not submitted' }}"
                                   class="form-control bg-light text-muted"
                                   disabled readonly
                                   title="This score is calculated automatically from submitted answers and cannot be manually edited.">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Stanine Score <i class="bi bi-lock text-muted ms-1"></i></label>
                            <input value="{{ $applicant->stanine_score ?? 'Not computed' }}"
                                   class="form-control bg-light text-muted"
                                   disabled readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Total Score <i class="bi bi-lock text-muted ms-1"></i></label>
                            <input value="{{ $applicant->total_score !== null ? number_format($applicant->total_score, 2) : 'Pending' }}"
                                   class="form-control bg-light text-muted"
                                   disabled readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Qualification <i class="bi bi-lock text-muted ms-1"></i></label>
                            <input value="{{ $applicant->qualification_status ?? 'Pending' }}"
                                   class="form-control bg-light text-muted"
                                   disabled readonly>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary">
                        <i class="bi bi-save me-1"></i> Save Changes
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endforeach

{{-- Import panel (hidden, triggered by button if needed) --}}
<div class="modal fade" id="import-modal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title h5">Import Applicants from CSV / Excel</h3>
                <button class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small">
                    Required columns: <code>application_number, last_name, first_name, middle_name, course, sex, 4ps_osy_ip_pwd_sp, cmfl, gwa</code>
                </p>
                <form method="post"
                      enctype="multipart/form-data"
                      action="{{ route('admin.admission.applicants.import') }}">
                    @csrf
                    <input type="file" name="file" accept=".csv,.xlsx" class="form-control mb-3" required>
                    <button class="btn btn-primary">
                        <i class="bi bi-upload me-1"></i> Import Applicants
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection
