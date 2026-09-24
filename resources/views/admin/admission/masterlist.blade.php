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
        <a class="btn btn-outline-primary btn-sm" href="{{ route('admin.admission.encoding-sheet', ['cycle_id' => $cycle->id]) }}">
            <i class="bi bi-grid-3x3 me-1"></i> Masterlist Encoding Sheet
        </a>
        <a class="btn btn-outline-primary btn-sm" href="{{ route('admin.sessions.index') }}">
            <i class="bi bi-calendar3 me-1"></i> Open Test Sessions
        </a>
        <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#rangeAllocationModal" @disabled($isLocked)>
            <i class="bi bi-person-lines-fill me-1"></i> Range Session Allocation
        </button>
        {{-- ── REFRESH DATA BUTTON ── --}}
        <button class="btn btn-outline-secondary btn-sm" id="refreshMasterlistBtn" type="button" title="Reload applicant list without a full page reload">
            <i class="bi bi-arrow-clockwise me-1"></i> Refresh Data
        </button>
        <a class="btn btn-primary btn-sm"
           href="{{ route('admin.admission.report', ['cycle_id' => $cycle->id, 'type' => 'summary', 'format' => 'docx']) }}">
            <i class="bi bi-file-earmark-word me-1"></i> DOCX Export
        </a>
        @if ($cycle->isActive())
            <form method="post" action="{{ route('admin.admission.cycles.complete', $cycle) }}"
                  onsubmit="return confirm('Are you sure you want to mark cycle \'{{ $cycle->displayName }}\' as Completed and Archive it? This will lock all applicant entries and exam scores from further modification.');"
                  class="d-inline">
                @csrf
                <button class="btn btn-warning btn-sm fw-semibold">
                    <i class="bi bi-archive-fill me-1"></i> Complete &amp; Archive
                </button>
            </form>
        @endif
    </div>
</div>

{{-- ── ARCHIVED CYCLE LOCKED BANNER ── --}}
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
                        @for($s = 1; $s <= 9; $s++)
                            <option value="{{ $s }}" @selected(request('stanine') == $s)>Stanine {{ $s }}</option>
                        @endfor
                    </select>
                </div>
                <div class="col-md-4">
                    <select class="form-select form-select-sm" name="sort">
                        <option value="course_last_name" @selected(request('sort','course_last_name') === 'course_last_name')>Sort by Course</option>
                        <option value="gwa_desc" @selected(request('sort') === 'gwa_desc')>Highest GWA</option>
                        <option value="total_desc" @selected(request('sort') === 'total_desc')>Highest Total Score</option>
                        <option value="last_name" @selected(request('sort') === 'last_name')>Last Name A–Z</option>
                    </select>
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
                    <tr>
                        <th class="ps-3">NO.</th>
                        <th>LAST NAME</th>
                        <th>GIVEN NAME</th>
                        <th>MIDDLE NAME</th>
                        <th>COURSE (1ST CHOICE)</th>
                        <th>SEX</th>
                        <th>4PS/OSY/IP/PWD/SP</th>
                        <th>CMFL</th>
                        <th>GWA</th>
                        <th>TEST</th>
                        <th>STANINE</th>
                        <th>INTERVIEW</th>
                        <th>STATUS</th>
                        <th>EXAM SUBMITTED</th>
                        <th>TOTAL</th>
                        <th class="pe-3 text-end">ACTION</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($applicants as $i => $applicant)
                    <tr>
                        <td class="ps-3">{{ $applicants->firstItem() + $i }}</td>
                        <td class="fw-bold">{{ mb_strtoupper($applicant->last_name) }}</td>
                        <td>{{ mb_strtoupper($applicant->first_name) }}</td>
                        <td>{{ $applicant->middle_name ? mb_strtoupper($applicant->middle_name) : '–' }}</td>
                        <td>
                            <span class="badge bg-light text-dark border" title="{{ \App\Support\CourseCatalog::label($applicant->course_choice) }}">
                                {{ $applicant->course_choice }}
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
                                $batchLabel = $applicant->batch_group ?? 'First Batch - Session A';
                            @endphp
                            <div>
                                <span class="badge {{ $applicant->submitted_at ? ($status === 'Qualified' ? 'bg-success' : 'bg-danger') : 'bg-secondary' }}">
                                    {{ $applicant->submitted_at ? $status : 'Absent in Exam' }}
                                </span>
                                <div class="text-muted" style="font-size:10px">{{ $batchLabel }}</div>
                                <div class="text-muted" style="font-size:10px">{{ $sessionLabel }}</div>
                            </div>
                        </td>
                        <td>
                            @if ($applicant->submitted_at)
                                <span class="text-success fw-semibold">Yes</span>
                            @else
                                <span class="text-muted">No</span>
                            @endif
                        </td>
                        <td class="fw-bold">
                            {{ $applicant->total_score !== null ? number_format($applicant->total_score, 2) : '–' }}
                        </td>
                        <td class="pe-3 text-end">
                            <button class="btn btn-sm btn-outline-primary"
                                    data-bs-toggle="modal"
                                    data-bs-target="#edit-applicant-{{ $applicant->id }}">
                                {{ $isLocked ? 'View' : 'Edit' }}
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="16" class="text-center text-muted py-5">
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
                            <label class="form-label form-label-sm fw-semibold">Course (1st Choice)</label>
                            <select name="course_choice" class="form-select form-select-sm" required @disabled($isLocked)>
                                @foreach ($courses as $code => $title)
                                    <option value="{{ $code }}" @selected($applicant->course_choice === $code)>
                                        {{ $title }}
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
                            <input name="special_group" value="{{ $applicant->special_group }}"
                                   class="form-control form-control-sm" placeholder="N/A" @disabled($isLocked)>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label form-label-sm fw-semibold">CMFL</label>
                            <input name="cmfl" value="{{ $applicant->cmfl }}"
                                   class="form-control form-control-sm" placeholder="N/A" @disabled($isLocked)>
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
                                   class="form-control form-control-sm bg-light text-muted" disabled readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label form-label-sm text-muted">Qualification</label>
                            <input value="{{ $applicant->qualification_status ?? 'Pending' }}"
                                   class="form-control form-control-sm bg-light text-muted" disabled readonly>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    @if(!$isLocked)
                        <button class="btn btn-primary btn-sm">
                            <i class="bi bi-save me-1"></i> Save Changes
                        </button>
                    @endif
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endforeach


{{-- ── REFRESH DATA: AJAX re-fetch without full page reload ── --}}
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

    btn.addEventListener('click', () => refresh(false));

    // Auto-refresh every 60 seconds (silent)
    setInterval(() => refresh(true), 60000);
})();
</script>
@endpush

@endsection
