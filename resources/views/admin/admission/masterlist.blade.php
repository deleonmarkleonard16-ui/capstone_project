@extends('layouts.app')
@section('content')

{{-- ═══════════════════════════════════════════════════════
     ADMISSION MASTERLIST & ARCHIVED CYCLE VIEWER
     Supports inspecting both Active and Completed/Archived cycles.
     Archived cycles are strictly locked against score edits or new entries.
     ═══════════════════════════════════════════════════════ --}}

{{-- Top page header --}}
<div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
    <div>
        <h1 class="h3 mb-1">
            Admission Masterlist
            <span class="badge {{ $isLocked ? 'bg-secondary' : ($cycle->isActive() ? 'bg-success' : 'bg-warning text-dark') }} fs-6 ms-2">
                {{ $isLocked ? 'Viewing: ' . $cycle->displayName . ' [Archived]' : ($cycle->isActive() ? 'Viewing: ' . $cycle->displayName . ' [Active]' : 'Viewing: ' . $cycle->displayName . ' [Draft]') }}
            </span>
        </h1>
        <p class="text-muted mb-0 small">
            Academic Year: <strong>{{ $cycle->academic_year }}</strong> &nbsp;·&nbsp;
            Passing Stanine: <strong>{{ $cycle->passing_stanine }}</strong> &nbsp;·&nbsp;
            Exam / GWA / Interview: {{ $cycle->exam_weight }}% / {{ $cycle->gwa_weight }}% / {{ $cycle->interview_weight }}%
            @if($isLocked)
                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle ms-2"><i class="bi bi-lock-fill me-1"></i>Archived / Read-Only</span>
            @endif
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        {{-- ── REFRESH DATA BUTTON ── --}}
        <button class="btn btn-primary btn-sm" id="refreshMasterlistBtn" type="button" title="Reload applicant list without a full page reload">
            <i class="bi bi-arrow-clockwise me-1"></i> Refresh Data
        </button>
    </div>
</div>

{{-- ── ARCHIVED CYCLE LOCKED BANNER ── --}}
@if($cycle->isActive())
<div class="card page-card shadow-sm mb-3">
    <div class="card-body p-3">
        <h2 class="h6 mb-1">Enrollment Quotas by Program</h2>
        <p class="small text-muted mb-2">
            Set the number of available enrollment seats here. This is separate from the Interview Top Limit below.
            A missing or zero quota makes otherwise eligible applicants <strong>Waitlisted</strong>.
        </p>
        <form method="post" action="{{ route('admin.admission.quotas.save') }}" class="mb-3">
            @csrf
            <div class="row g-2">
                @foreach($reportPrograms as $code)
                    <div class="col-6 col-md-3 col-xl-2">
                        <label class="form-label small mb-1" for="quota-{{ $loop->index }}">{{ $code }}</label>
                        <input id="quota-{{ $loop->index }}" class="form-control form-control-sm" type="number" min="0" max="100000" required name="quotas[{{ $code }}]" value="{{ old('quotas.'.$code, $courseQuotas[$code] ?? 0) }}" aria-label="{{ $code }} enrollment seats">
                    </div>
                @endforeach
            </div>
            @error('quotas') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            <div class="mt-3">
                <button class="btn btn-primary btn-sm" type="submit">Save Enrollment Quotas</button>
            </div>
        </form>

        <h2 class="h6 mb-2">Admission Reports</h2>
        <div class="d-flex flex-wrap gap-2 mb-3">
            @foreach([
                'psu-cat-qualifiers' => 'PSU-CAT Roster',
                'interview-non-qualifiers' => 'Not Qualified for Interview',
                'final-enrollment-qualified' => 'Qualified for Enrollment',
                'final-enrollment-waitlisted' => 'Waitlisted',
            ] as $report => $label)
                <div class="btn-group btn-group-sm" role="group" aria-label="{{ $label }} exports">
                    <a class="btn btn-outline-primary" href="{{ route('admin.admission.reports.'.$report, ['format' => 'pdf', 'batch_group' => request('batch_group')]) }}">{{ $label }} PDF</a>
                    <a class="btn btn-outline-primary" href="{{ route('admin.admission.reports.'.$report, ['format' => 'docx', 'batch_group' => request('batch_group')]) }}">DOCX</a>
                </div>
            @endforeach
        </div>
        <form method="post" action="{{ route('admin.admission.reports.interview-qualifiers') }}">
            @csrf
            @if(request('batch_group')) <input type="hidden" name="batch_group" value="{{ request('batch_group') }}"> @endif
            <div class="fw-semibold small mb-1">Interview top limits by program</div>
            <p class="small text-muted mb-2">Board programs require stanine 4 or higher. Other programs accept all recorded stanines. Zero selects no applicants.</p>
            <div class="row g-2">
                @foreach($reportPrograms as $code)
                    <div class="col-6 col-md-3 col-xl-2">
                        <label class="form-label small mb-1" for="top-limit-{{ $loop->index }}">{{ $code }}</label>
                        <input id="top-limit-{{ $loop->index }}" class="form-control form-control-sm" type="number" min="0" max="100000" required name="top_limits[{{ $code }}]" value="{{ old('top_limits.'.$code, $reportCutoffs[$code] ?? 100000) }}">
                    </div>
                @endforeach
            </div>
            @error('top_limits') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
            <div class="d-flex gap-2 mt-3">
                <button class="btn btn-outline-secondary btn-sm" name="save_only" value="1">Save Interview Limits</button>
                <button class="btn btn-primary btn-sm" name="format" value="pdf">Save Limits &amp; Export Interview PDF</button>
                <button class="btn btn-outline-primary btn-sm" name="format" value="docx">Save Limits &amp; Export Interview DOCX</button>
            </div>
        </form>
    </div>
</div>

@endif

@if ($isLocked)
    <div class="alert alert-secondary border d-flex align-items-center justify-content-between flex-wrap gap-2 py-2 px-3 mb-3">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-shield-lock-fill fs-4 text-secondary"></i>
            <div>
                <strong>Archived Historical Record:</strong> You are inspecting past applicant rosters for <strong>{{ $cycle->displayName }}</strong>.
                New applicant registrations, imports, and score modifications are permanently locked.
            </div>
        </div>
        @php $activeCycle = \App\Models\AdmissionCycle::active(); @endphp
        @if ($activeCycle && $activeCycle->id !== $cycle->id)
            <a href="{{ route('admin.admission.masterlist', ['cycle_id' => $activeCycle->id]) }}" class="btn btn-sm btn-primary">
                <i class="bi bi-arrow-return-left me-1"></i> Return to Active Cycle
            </a>
        @endif
    </div>
@endif

@if(session('import_errors'))
    <div class="alert alert-warning">
        <strong>Import warnings:</strong> {{ implode('; ', session('import_errors')) }}
    </div>
@endif

{{-- ── 7-FILTER BAR (MATCHING SCREENSHOT) ── --}}
<div class="card page-card shadow-sm mb-3">
    <div class="card-body p-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <div class="fw-semibold small text-muted">Admission Masterlist</div>
            <span class="badge bg-light text-dark border">{{ $applicants->total() }} record(s)</span>
        </div>
        <form method="get" action="{{ route('admin.admission.masterlist') }}" id="masterlist-filters">
            {{-- Row 1: Cycle Selector, Search, Batch Groups --}}
            <div class="row g-2 mb-2">
                {{-- Cycle Selector --}}
                <div class="col-md-3">
                    <select class="form-select form-select-sm" name="cycle_id" onchange="this.form.submit()">
                        @foreach($allCycles as $c)
                            <option value="{{ $c->id }}" @selected($cycle->id === $c->id)>
                                Viewing: {{ $c->displayName }} [{{ $c->isCompleted() ? 'Archived' : ($c->isActive() ? 'Active' : 'Draft') }}]
                            </option>
                        @endforeach
                    </select>
                </div>
                {{-- Search --}}
                <div class="col-md-5">
                    <input class="form-control form-control-sm" name="search"
                           value="{{ request('search') }}"
                           placeholder="Search by no., name, or course">
                </div>
                {{-- Batch groups --}}
                <div class="col-md-4">
                    <select class="form-select form-select-sm" name="batch_group">
                        <option value="">All batch groups</option>
                        @foreach($batchGroups as $bg)
                            <option value="{{ $bg }}" @selected(request('batch_group') === $bg)>{{ $bg }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Row 2: Course, Sessions, Exam Records --}}
            <div class="row g-2 mb-2">
                {{-- Course --}}
                <div class="col-md-4">
                    <select class="form-select form-select-sm" name="course">
                        <option value="">All courses</option>
                        @foreach($courses as $code => $title)
                            <option value="{{ $code }}" @selected(request('course') === $code)>{{ $title }}</option>
                        @endforeach
                    </select>
                </div>
                {{-- Session --}}
                <div class="col-md-4">
                    <select class="form-select form-select-sm" name="session_filter">
                        <option value="">All sessions</option>
                        @foreach($sessionOptions as $s)
                            <option value="{{ $s }}" @selected(request('session_filter') === $s)>{{ $s }}</option>
                        @endforeach
                    </select>
                </div>
                {{-- Exam records --}}
                <div class="col-md-4">
                    <select class="form-select form-select-sm" name="exam_filter">
                        <option value="">All exam records</option>
                        <option value="submitted" @selected(request('exam_filter') === 'submitted')>Submitted</option>
                        <option value="not_submitted" @selected(request('exam_filter') === 'not_submitted')>Not submitted</option>
                    </select>
                </div>
            </div>

            {{-- Row 3: Interview, Stanines, Sort, Apply --}}
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <select class="form-select form-select-sm" name="interview_filter">
                        <option value="">All interview records</option>
                        <option value="scored" @selected(request('interview_filter') === 'scored')>Scored</option>
                        <option value="pending" @selected(request('interview_filter') === 'pending')>Pending</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select form-select-sm" name="stanine">
                        <option value="">All stanines</option>
                        <option value="below_3" @selected(request('stanine') === 'below_3')>Below 3</option>
                        <option value="at_least_3" @selected(request('stanine') === 'at_least_3')>3 and above</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <div class="form-control form-control-sm bg-light text-muted">Ranked by total, stanine, GWA, interview</div>
                </div>
                <div class="col-md-2 d-flex gap-1">
                    <button class="btn btn-primary btn-sm flex-fill">Apply Filters</button>
                    <a href="{{ route('admin.admission.masterlist', ['cycle_id' => $cycle->id]) }}" class="btn btn-outline-secondary btn-sm" title="Reset Filters">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- ── APPLICANT TABLE (MATCHING SCREENSHOT EXACTLY) ── --}}
<div class="card page-card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0" id="masterlist-table">
                <thead class="table-light">
                    @php
                        $gwaWeight = (float) ($cycle->gwa_weight ?? 20);
                        $catWeight = (float) ($cycle->exam_weight ?? 60);
                        $interviewWeight = (float) ($cycle->interview_weight ?? 20);
                    @endphp
                    <tr>
                        <th class="ps-3">RANK</th>
                        <th>LAST NAME</th>
                        <th>FIRST NAME</th>
                        <th>MIDDLE NAME</th>
                        <th>1ST COURSE CHOICE</th>
                        <th>2ND COURSE CHOICE</th>
                        <th>SEX</th>
                        <th>4PS/OSY/IP/PWD/SP</th>
                        <th>CMFL</th>
                        <th>GWA ({{ $gwaWeight }}%)</th>
                        <th>CAT ({{ $catWeight }}%)</th>
                        <th>INTERVIEW ({{ $interviewWeight }}%)</th>
                        <th>TOTAL (%)</th>
                        <th>REMARKS</th>
                        <th class="pe-3 text-end">ACTION</th>
                    </tr>
                </thead>
                <tbody id="applicants-tbody">
                @forelse ($applicants as $i => $applicant)
                    @php
                        $eval = $applicant->qualification_evaluation;
                        $outcome = $outcomes[$applicant->id] ?? null;
                    @endphp
                    <tr>
                        <td class="ps-3 fw-bold">#{{ $ranks[$applicant->id] ?? ($i + 1) }}</td>
                        <td class="fw-bold">{{ mb_strtoupper($applicant->last_name) }}</td>
                        <td>{{ mb_strtoupper($applicant->first_name) }}</td>
                        <td>{{ $applicant->middle_name ? mb_strtoupper($applicant->middle_name) : '–' }}</td>

                        {{-- 1ST COURSE CHOICE with Qualification Highlighting --}}
                        <td data-course-cell="{{ $applicant->id }}-1" @if($eval['c1_status'] === 'qualified') style="background-color: #d1fae5 !important;" @elseif($eval['c1_status'] === 'not_qualified') style="background-color: #fee2e2 !important;" @endif>
                            <span data-course-badge="{{ $applicant->id }}-1" class="badge {{ $eval['c1_status'] === 'qualified' ? 'bg-success text-white' : ($eval['c1_status'] === 'not_qualified' ? 'bg-danger-subtle text-danger border border-danger-subtle' : 'bg-light text-dark border') }}"
                                  title="{{ \App\Support\CourseCatalog::label($applicant->course_choice_1 ?: $applicant->course_choice) }}">
                                {{ $applicant->course_choice_1 ?: $applicant->course_choice }}
                            </span>
                        </td>

                        {{-- 2ND COURSE CHOICE with Qualification Highlighting --}}
                        <td data-course-cell="{{ $applicant->id }}-2" @if($eval['c2_status'] === 'qualified') style="background-color: #d1fae5 !important;" @elseif($eval['c2_status'] === 'not_qualified') style="background-color: #fee2e2 !important;" @endif>
                            @php
                                $c2Display = $applicant->course_choice_2 ?: $applicant->second_course_choice;
                            @endphp
                            @if($c2Display && $c2Display !== 'N/A' && $c2Display !== 'None')
                                <span data-course-badge="{{ $applicant->id }}-2" class="badge {{ $eval['c2_status'] === 'qualified' ? 'bg-success text-white' : ($eval['c2_status'] === 'not_qualified' ? 'bg-danger-subtle text-danger border border-danger-subtle' : 'bg-light text-dark border') }}"
                                      title="{{ \App\Support\CourseCatalog::label($c2Display) }}">
                                    {{ $c2Display }}
                                </span>
                            @else
                                <span class="text-muted">–</span>
                            @endif
                        </td>

                        <td>{{ $applicant->sex ?: '–' }}</td>
                        <td>{{ $applicant->special_group ?: 'N/A' }}</td>
                        <td>{{ $applicant->cmfl ?: 'N/A' }}</td>
                        <td>
                            @if($applicant->gwa !== null)
                                <span class="fw-semibold">{{ number_format($applicant->gwa, 2) }}%</span>
                                <div class="text-muted" style="font-size: 10px;" title="{{ number_format($applicant->gwa, 2) }}% × {{ $gwaWeight }}% weight">
                                    Wt: {{ number_format($applicant->gwa * ($gwaWeight / 100), 2) }}%
                                </div>
                            @else
                                <span class="text-muted">–</span>
                            @endif
                        </td>
                        <td>
                            @if($applicant->cat_score_percentage !== null)
                                <span class="fw-semibold">{{ number_format($applicant->cat_score_percentage, 2) }}%</span>
                                <div class="text-muted" style="font-size: 10px;" title="Raw: {{ $applicant->exam_score }}/{{ $cycle->total_items ?: 80 }} · Stanine {{ $applicant->stanine_score ?? '–' }} · Weighted: {{ number_format($applicant->cat_score_percentage * ($catWeight / 100), 2) }}%">
                                    ({{ $applicant->exam_score }}/{{ $cycle->total_items ?: 80 }} · St. {{ $applicant->stanine_score ?? '–' }} · Wt: {{ number_format($applicant->cat_score_percentage * ($catWeight / 100), 2) }}%)
                                </div>
                            @else
                                <span class="text-muted">–</span>
                            @endif
                        </td>
                        <td data-interview-cell="{{ $applicant->id }}">
                            @if(!$isLocked)
                                <form class="js-inline-interview-form mb-1" action="{{ route('admin.admission.applicants.interview-score', $applicant) }}" data-applicant-id="{{ $applicant->id }}">
                                    @csrf
                                    <div class="input-group input-group-sm" style="min-width: 116px;">
                                        <input class="form-control text-center js-inline-interview-input" type="number" name="interview_score" min="0" max="100" step="0.01"
                                               value="{{ $applicant->interview_score }}" placeholder="Enter score" aria-label="Interview score for {{ $applicant->full_name }}" required>
                                        <button class="btn btn-outline-primary js-inline-interview-save" type="submit" title="Save interview score"><i class="bi bi-check-lg"></i></button>
                                    </div>
                                </form>
                            @endif
                            <div class="js-inline-interview-result">
                            @if($applicant->interview_score !== null)
                                <span class="fw-semibold">{{ number_format($applicant->interview_score, 2) }}%</span>
                                <div class="text-muted" style="font-size: 10px;" title="{{ number_format($applicant->interview_score, 2) }}% × {{ $interviewWeight }}% weight">
                                    Wt: {{ number_format($applicant->interview_score * ($interviewWeight / 100), 2) }}%
                                </div>
                            @else
                                <span class="text-muted">–</span>
                            @endif
                            </div>
                        </td>
                        <td class="fw-bold" data-total-cell="{{ $applicant->id }}">
                            @php
                                $tot = $applicant->calculated_total ?? $applicant->total_score;
                            @endphp
                            {{ $tot !== null ? number_format($tot, 2) . '%' : '–' }}
                        </td>
                        <td data-remarks-cell="{{ $applicant->id }}">
                            <span class="badge {{ $outcome['badge'] ?? $eval['badge'] ?? 'bg-secondary' }}">
                                {{ $outcome['remark'] ?? $eval['remarks'] }}
                            </span>
                            @if($applicant->admissionSession)
                                <div class="text-muted" style="font-size: 10px;">{{ $applicant->admissionSession->session_name ?? $applicant->session_label }}</div>
                            @elseif($applicant->session_label)
                                <div class="text-muted" style="font-size: 10px;">{{ $applicant->session_label }}</div>
                            @endif
                        </td>
                        <td class="pe-3 text-end">
                            <div class="d-flex justify-content-end gap-1">
                                <button class="btn btn-sm btn-outline-primary"
                                        data-bs-toggle="modal"
                                        data-bs-target="#edit-applicant-{{ $applicant->id }}">
                                    {{ $isLocked ? 'View' : 'Edit' }}
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="15" class="text-center text-muted py-5">
                            <i class="bi bi-inbox fs-2 d-block mb-2 opacity-25"></i>
                            No applicants found for {{ $cycle->displayName }}.
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-3 py-2 border-top">
            {{ $applicants->withQueryString()->links('pagination::bootstrap-5') }}
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════
     EDIT / VIEW APPLICANT MODALS
     Exam score is strictly DISABLED/READ-ONLY
     Locked for changes if cycle is completed
     ══════════════════════════════════════ --}}
@foreach ($applicants as $applicant)
<div class="modal fade" id="edit-applicant-{{ $applicant->id }}"
     tabindex="-1" aria-labelledby="edit-label-{{ $applicant->id }}" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title h5" id="edit-label-{{ $applicant->id }}">
                    {{ $isLocked ? 'Applicant Profile' : 'Edit Applicant' }} — {{ $applicant->full_name }}
                </h3>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" action="{{ route('admin.admission.applicants.save', $applicant) }}">
                @csrf
                <input type="hidden" name="cycle_id" value="{{ $cycle->id }}">
                <div class="modal-body">
                    @if($isLocked)
                        <div class="alert alert-secondary py-2 small mb-3">
                            <i class="bi bi-lock-fill me-1"></i> Historical Cycle Record — Profile is displayed in read-only mode.
                        </div>
                    @endif

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label form-label-sm fw-semibold">Application Number</label>
                            <input name="application_number" value="{{ $applicant->application_number }}"
                                   class="form-control form-control-sm" required @disabled($isLocked)>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label form-label-sm fw-semibold">Last Name</label>
                            <input name="last_name" value="{{ $applicant->last_name }}"
                                   class="form-control form-control-sm" required @disabled($isLocked)>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label form-label-sm fw-semibold">First Name</label>
                            <input name="first_name" value="{{ $applicant->first_name }}"
                                   class="form-control form-control-sm" required @disabled($isLocked)>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label form-label-sm fw-semibold">Middle Name</label>
                            <input name="middle_name" value="{{ $applicant->middle_name }}"
                                   class="form-control form-control-sm" @disabled($isLocked)>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label form-label-sm fw-semibold">1st Course Choice</label>
                            <select name="course_choice" class="form-select form-select-sm" required @disabled($isLocked)>
                                @foreach ($courses as $code => $title)
                                    <option value="{{ $code }}" @selected($applicant->course_choice === $code)>
                                        {{ $title }} ({{ $code }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label form-label-sm fw-semibold">2nd Course Choice</label>
                            <select name="second_course_choice" class="form-select form-select-sm" @disabled($isLocked)>
                                <option value="">— None —</option>
                                @foreach ($courses as $code => $title)
                                    <option value="{{ $code }}" @selected($applicant->second_course_choice === $code)>
                                        {{ $title }} ({{ $code }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label form-label-sm fw-semibold">Sex</label>
                            <select name="sex" class="form-select form-select-sm" @disabled($isLocked)>
                                <option value="">—</option>
                                <option value="Male"   @selected($applicant->sex === 'Male')>Male</option>
                                <option value="Female" @selected($applicant->sex === 'Female')>Female</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label form-label-sm fw-semibold">4PS/OSY/IP/PWD/SP</label>
                            <select name="special_group" class="form-select form-select-sm" @disabled($isLocked)>
                                @foreach(['N/A', '4Ps', 'OSY', 'IP', 'PWD', 'SP'] as $opt)
                                    <option value="{{ $opt }}" @selected(($applicant->special_group ?? 'N/A') === $opt || (!($applicant->special_group ?? '') && $opt === 'N/A'))>{{ $opt }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label form-label-sm fw-semibold">CMFL</label>
                            <select name="cmfl" class="form-select form-select-sm" @disabled($isLocked)>
                                @foreach(['N/A', '10,000 below', '10,001 to 20,000', '20,001 to 30,000', '30,001 to 50,000', '50,001 and above'] as $opt)
                                    <option value="{{ $opt }}" @selected(($applicant->cmfl ?? 'N/A') === $opt || (!($applicant->cmfl ?? '') && $opt === 'N/A'))>{{ $opt }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label form-label-sm fw-semibold">GWA</label>
                            <input type="number" step="0.01" min="75" max="100"
                                   name="gwa" value="{{ $applicant->gwa }}"
                                   class="form-control form-control-sm" placeholder="e.g. 92.50" @disabled($isLocked)>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label form-label-sm fw-semibold">Interview Score</label>
                            <input type="number" step="0.01" min="0" max="100"
                                   name="interview_score" value="{{ $applicant->interview_score }}"
                                   id="edit-interview-score-{{ $applicant->id }}"
                                   class="form-control form-control-sm" placeholder="Optional" @disabled($isLocked)>
                        </div>

                        {{-- ══ STRICTLY READ-ONLY EXAM SCORES ══ --}}
                        <div class="col-12">
                            <hr class="my-2">
                            <div class="small text-muted mb-2">
                                <i class="bi bi-lock-fill me-1"></i> Exam score &amp; stanine ratings are computed automatically from submitted bubble answers and cannot be manually edited.
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label form-label-sm text-muted">Computed Exam Score</label>
                            <input value="{{ $applicant->exam_score !== null ? number_format($applicant->exam_score, 2) : 'Not submitted' }}"
                                   class="form-control form-control-sm bg-light text-muted" disabled readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label form-label-sm text-muted">Stanine Score</label>
                            <input value="{{ $applicant->stanine_score ?? 'Not computed' }}"
                                   class="form-control form-control-sm bg-light text-muted" disabled readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label form-label-sm text-muted">Total Score</label>
                            <input value="{{ $applicant->total_score !== null ? number_format($applicant->total_score, 2) : 'Pending' }}"
                                   id="edit-total-score-{{ $applicant->id }}"
                                   class="form-control form-control-sm bg-light text-muted" disabled readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label form-label-sm text-muted">Remarks</label>
                            <input value="{{ $outcomes[$applicant->id]['remark'] ?? $eval['remarks'] ?? 'Pending' }}"
                                   id="edit-qualification-{{ $applicant->id }}"
                                   class="form-control form-control-sm bg-light text-muted" disabled readonly>
                        </div>

                        @php
                            $eval = $applicant->qualification_evaluation;
                            $isRemarksDone = $applicant->stanine_score !== null
                                && ($eval['status'] ?? '') !== 'Pending'
                                && !in_array($eval['remarks'] ?? '', ['Pending', 'Scheduled', 'Absent', 'Not submitted', '']);
                            $defaultRequestor = ($applicant->sex === 'Female' ? 'MS. ' : 'MR. ') . mb_strtoupper($applicant->last_name);
                        @endphp

                        @if($isRemarksDone)
                            {{-- ══ CERTIFICATE OF ADMISSION TEST RESULT GENERATOR ══ --}}
                            <div class="col-12 mt-3 pt-3 border-top bg-light-subtle rounded p-3 border">
                                <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                                    <label class="form-label form-label-sm fw-bold text-primary mb-0">
                                        <i class="bi bi-file-earmark-text-fill me-1"></i> Certificate of Admission Test Result
                                    </label>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                        Remarks: {{ $applicant->certificate_remarks }}
                                    </span>
                                </div>
                                <div class="row g-2 align-items-end">
                                    <div class="col-md-3">
                                        <label class="form-label form-label-sm text-muted mb-1" for="cert_issued_date_{{ $applicant->id }}">Date Issued:</label>
                                        <input type="date" class="form-control form-control-sm" id="cert_issued_date_{{ $applicant->id }}"
                                               value="{{ now()->format('Y-m-d') }}">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label form-label-sm text-muted mb-1" for="cert_requestor_{{ $applicant->id }}">Requestor Name:</label>
                                        <input type="text" class="form-control form-control-sm" id="cert_requestor_{{ $applicant->id }}"
                                               value="{{ $defaultRequestor }}"
                                               placeholder="e.g. MS. PEREZ">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label form-label-sm text-muted mb-1" for="cert_purpose_{{ $applicant->id }}">Purpose:</label>
                                        <input type="text" class="form-control form-control-sm" id="cert_purpose_{{ $applicant->id }}"
                                               value="SCHOLARSHIP"
                                               placeholder="e.g. SCHOLARSHIP">
                                    </div>
                                    <div class="col-md-3">
                                        <div class="d-flex gap-1">
                                            <button type="button" class="btn btn-outline-success btn-sm flex-fill fw-semibold"
                                                    onclick="openApplicantCert({{ $applicant->id }}, 'pdf')">
                                                <i class="bi bi-download me-1"></i> Download PDF
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary btn-sm"
                                                    onclick="openApplicantCert({{ $applicant->id }}, 'print')"
                                                    title="Print Preview">
                                                <i class="bi bi-printer"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label form-label-sm text-muted mb-1" for="cert_or_number_{{ $applicant->id }}">O.R. # (Optional):</label>
                                        <input type="text" class="form-control form-control-sm" id="cert_or_number_{{ $applicant->id }}"
                                               placeholder="e.g. 1234567">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label form-label-sm text-muted mb-1" for="cert_or_date_{{ $applicant->id }}">O.R. Date (Optional):</label>
                                        <input type="date" class="form-control form-control-sm" id="cert_or_date_{{ $applicant->id }}"
                                               value="{{ now()->format('Y-m-d') }}">
                                    </div>
                                    <div class="col-md-6">
                                        <div class="small text-muted pt-2">
                                            <i class="bi bi-info-circle me-1"></i> Certificate reflects Stanine {{ $applicant->stanine_score }} and official institutional remarks.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @else
                            {{-- ══ CERTIFICATE GENERATION DISABLED UNTIL REMARKS COMPLETE ══ --}}
                            <div class="col-12 mt-3 pt-3 border-top">
                                <div class="alert alert-secondary py-2 small mb-0 d-flex align-items-center gap-2">
                                    <i class="bi bi-lock-fill fs-5 text-muted"></i>
                                    <div>
                                        <strong>Certificate Generation Locked:</strong> Official Certificate of Admission Test Result will be available once the examinee's score and evaluation remarks are finalized.
                                        (Current Status: <span class="badge bg-secondary">{{ $eval['remarks'] ?? 'Pending' }}</span>)
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
                <div class="modal-footer d-flex justify-content-between flex-wrap gap-2">
                    <div>
                        @if($isRemarksDone)
                            <button type="button" class="btn btn-outline-success btn-sm"
                                    onclick="openApplicantCert({{ $applicant->id }}, 'pdf')">
                                <i class="bi bi-file-earmark-arrow-down me-1"></i> Download Certificate of Admission Test Result
                            </button>
                        @else
                            <button type="button" class="btn btn-outline-secondary btn-sm" disabled title="Evaluation remarks pending">
                                <i class="bi bi-lock me-1"></i> Certificate Locked (Pending Evaluation)
                            </button>
                        @endif
                    </div>
                    <div class="d-flex gap-2">
                        @if(!$isLocked)
                            <button class="btn btn-primary btn-sm">
                                <i class="bi bi-save me-1"></i> Save Changes
                            </button>
                        @endif
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endforeach


{{-- ── REFRESH DATA: AJAX re-fetch without full page reload ── --}}
<div class="modal fade" id="rangeAllocationModal" tabindex="-1" aria-labelledby="rangeAllocationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" action="{{ route('admin.admission.sessions.assign-range') }}">
                @csrf
                <input type="hidden" name="cycle_id" value="{{ $cycle->id }}">
                <div class="modal-header">
                    <div>
                        <h2 class="modal-title fs-5" id="rangeAllocationModalLabel">Range Session Allocation</h2>
                        <p class="text-muted small mb-0">Assign a numbered group of applicants to a session label.</p>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label" for="range-session-label">Session label <span class="text-danger">*</span></label>
                        <input class="form-control" id="range-session-label" name="session_label" value="{{ old('session_label') }}" maxlength="120" placeholder="e.g. Morning Session A" required>
                    </div>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label" for="range-start-number">Start # <span class="text-danger">*</span></label>
                            <input class="form-control" type="number" id="range-start-number" name="start_number" value="{{ old('start_number', 1) }}" min="1" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label" for="range-end-number">End # <span class="text-danger">*</span></label>
                            <input class="form-control" type="number" id="range-end-number" name="end_number" value="{{ old('end_number') }}" min="1" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="range-batch-group">Batch group <span class="text-muted">(optional)</span></label>
                            <select class="form-select" id="range-batch-group" name="batch_group">
                                <option value="">All batch groups</option>
                                @foreach($batchGroups as $bg)
                                    <option value="{{ $bg }}" @selected(old('batch_group') === $bg)>{{ $bg }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="range-course">Course <span class="text-muted">(optional)</span></label>
                            <select class="form-select" id="range-course" name="course">
                                <option value="">All courses</option>
                                @foreach($courses as $code => $title)
                                    <option value="{{ $code }}" @selected(old('course') === $code)>{{ $title }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <p class="form-text mb-0 mt-3">The selected range is counted after applying any optional batch group or course filter.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary" type="submit"><i class="bi bi-person-check me-1"></i>Assign Range</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function () {
    const btn = document.getElementById('refreshMasterlistBtn');
    if (!btn) return;

    async function refresh(silent = false) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> Refreshing…';

        try {
            const url  = new URL(window.location.href);
            url.searchParams.set('_ajax_refresh', '1');
            const res  = await fetch(url.toString(), {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' }
            });

            if (!res.ok) throw new Error('Server error ' + res.status);
            const html = await res.text();

            // Parse the returned HTML and swap the applicants table body
            const parser  = new DOMParser();
            const doc     = parser.parseFromString(html, 'text/html');
            const newBody  = doc.querySelector('#applicants-tbody');
            const curBody  = document.querySelector('#applicants-tbody');
            if (newBody && curBody) {
                curBody.innerHTML = newBody.innerHTML;

                // Re-attach modal triggers on newly injected buttons
                if (typeof bootstrap !== 'undefined') {
                    curBody.querySelectorAll('[data-bs-toggle="modal"]').forEach(el => {
                        new bootstrap.Modal(document.querySelector(el.dataset.bsTarget));
                    });
                }
            }

            if (!silent) showToast('Masterlist refreshed successfully.', 'success');
        } catch (err) {
            if (!silent) showToast('Refresh failed: ' + err.message, 'danger');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-arrow-clockwise me-1"></i> Refresh Data';
        }
    }

    function showToast(msg, type) {
        const container = document.getElementById('toast-container')
            || (() => {
                const c = document.createElement('div');
                c.id = 'toast-container';
                c.className = 'toast-container position-fixed top-0 end-0 p-3';
                c.style.zIndex = '9999';
                document.body.appendChild(c);
                return c;
            })();
        const toast = document.createElement('div');
        toast.className = `toast show text-bg-${type} border-0`;
        toast.innerHTML = `<div class="toast-body d-flex justify-content-between align-items-center">
            <span>${msg}</span>
            <button class="btn-close btn-close-white ms-2" onclick="this.closest('.toast').remove()"></button>
        </div>`;
        container.prepend(toast);
        setTimeout(() => { try { toast.remove(); } catch (e) {} }, 4000);
    }


    window.openApplicantCert = function(id, format = 'pdf') {
        const purposeInput = document.getElementById('cert_purpose_' + id);
        const purpose = purposeInput ? purposeInput.value : 'SCHOLARSHIP';

        const requestorInput = document.getElementById('cert_requestor_' + id);
        const requestor = requestorInput ? requestorInput.value : '';

        const issuedDateInput = document.getElementById('cert_issued_date_' + id);
        const issuedDate = issuedDateInput ? issuedDateInput.value : '';

        const orNumberInput = document.getElementById('cert_or_number_' + id);
        const orNumber = orNumberInput ? orNumberInput.value : '';

        const orDateInput = document.getElementById('cert_or_date_' + id);
        const orDate = orDateInput ? orDateInput.value : '';

        const params = new URLSearchParams({
            purpose: purpose,
            requestor_name: requestor,
            issued_date: issuedDate,
            or_number: orNumber,
            or_date: orDate,
            format: format
        });

        const url = '{{ url("admin/admission/applicants") }}/' + id + '/certificate?' + params.toString();
        window.open(url, '_blank');
    };

    // Inline interview-score entry is delegated so it also works after the
    // masterlist's AJAX refresh replaces table rows.
    document.addEventListener('change', event => {
        if (event.target.matches('.js-inline-interview-input') && event.target.value !== '') {
            event.target.closest('.js-inline-interview-form')?.requestSubmit();
        }
    });

    document.addEventListener('submit', async event => {
        const form = event.target.closest('.js-inline-interview-form');
        if (!form) return;
        event.preventDefault();

        const button = form.querySelector('.js-inline-interview-save');
        const input = form.querySelector('.js-inline-interview-input');
        const applicantId = form.dataset.applicantId;
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span>';

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: new FormData(form),
            });
            const payload = await response.json();
            if (!response.ok) {
                throw new Error(payload.message || 'Unable to save the interview score.');
            }

            input.value = Number(payload.interview_score).toFixed(2);
            const result = document.querySelector(`[data-interview-cell="${applicantId}"] .js-inline-interview-result`);
            if (result) {
                const score = Number(payload.interview_score);
                const weighted = score * (Number(payload.interview_weight) / 100);
                result.replaceChildren();
                const scoreLine = document.createElement('span');
                scoreLine.className = 'fw-semibold';
                scoreLine.textContent = `${score.toFixed(2)}%`;
                const weightLine = document.createElement('div');
                weightLine.className = 'text-muted';
                weightLine.style.fontSize = '10px';
                weightLine.textContent = `Wt: ${weighted.toFixed(2)}%`;
                result.append(scoreLine, weightLine);
            }

            const total = document.querySelector(`[data-total-cell="${applicantId}"]`);
            if (total) total.textContent = payload.total_score === null ? '–' : `${Number(payload.total_score).toFixed(2)}%`;

            // Keep the already-rendered Edit modal in sync with the inline
            // masterlist save; the administrator need not reload the page.
            const editInterview = document.getElementById(`edit-interview-score-${applicantId}`);
            if (editInterview) editInterview.value = Number(payload.interview_score).toFixed(2);
            const editTotal = document.getElementById(`edit-total-score-${applicantId}`);
            if (editTotal) editTotal.value = payload.total_score === null ? 'Pending' : Number(payload.total_score).toFixed(2);
            const editQualification = document.getElementById(`edit-qualification-${applicantId}`);
            if (editQualification) editQualification.value = payload.qualification_status || 'Pending';

            const remarks = document.querySelector(`[data-remarks-cell="${applicantId}"] .badge`);
            if (remarks) {
                remarks.className = `badge ${payload.remarks_badge || 'bg-secondary'}`;
                remarks.textContent = payload.remarks;
            }

            const updateChoiceColor = (choice, status) => {
                const cell = document.querySelector(`[data-course-cell="${applicantId}-${choice}"]`);
                const badge = document.querySelector(`[data-course-badge="${applicantId}-${choice}"]`);
                if (!cell || !badge) return;
                cell.style.removeProperty('background-color');
                if (status === 'qualified') {
                    cell.style.setProperty('background-color', '#d1fae5', 'important');
                    badge.className = 'badge bg-success text-white';
                } else if (status === 'not_qualified') {
                    cell.style.setProperty('background-color', '#fee2e2', 'important');
                    badge.className = 'badge bg-danger-subtle text-danger border border-danger-subtle';
                } else {
                    badge.className = 'badge bg-light text-dark border';
                }
            };
            updateChoiceColor(1, payload.first_choice_status);
            updateChoiceColor(2, payload.second_choice_status);

            showToast(payload.warning || 'Interview score, total, and remarks updated.', payload.warning ? 'warning' : 'success');
            // A score can move other applicants between qualified and
            // waitlisted, so reload the ranked rows after a successful save.
            if (!payload.warning) refresh(true);
        } catch (error) {
            showToast(error.message || 'Unable to save the interview score.', 'danger');
        } finally {
            button.disabled = false;
            button.innerHTML = '<i class="bi bi-check-lg"></i>';
        }
    });

    btn.addEventListener('click', () => refresh(false));

    // Auto-refresh every 60 seconds (silent)
    setInterval(() => refresh(true), 60000);
})();
</script>
@endpush

@endsection
