@extends('layouts.app')
@section('content')

{{-- ═══════════════════════════════════════════════════════════
     PSU-CAT ADMISSION CYCLE LIFECYCLE & ARCHIVING ENGINE
     Gatekeeper, Active State Machine, and Archived Cycle Inspector
     ═══════════════════════════════════════════════════════════ --}}

<div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">PSU-CAT Admission Cycle Lifecycle</h1>
        <p class="text-muted mb-0 small">
            Configure, activate, and archive admission cycles. Only one admission cycle can be Active at any given time.
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#initCycleModal">
            <i class="bi bi-plus-circle me-1"></i> Initialize New Cycle
        </button>
        @if ($active)
            <a class="btn btn-outline-primary btn-sm" href="{{ route('admin.admission.masterlist') }}">
                <i class="bi bi-table me-1"></i> Open Masterlist
            </a>
            <a class="btn btn-outline-primary btn-sm" href="{{ route('admin.admission.encoding-sheet') }}">
                <i class="bi bi-grid-3x3 me-1"></i> Encoding Sheet
            </a>
        @endif
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.admission.proctoring') }}">
            <i class="bi bi-camera-video me-1"></i> Proctoring
        </a>
    </div>
</div>

{{-- ── ACTIVE CYCLE BANNER & COMPLETION WORKFLOW ── --}}
@if ($active)
    <div class="card page-card border-success border-2 shadow-sm mb-4">
        <div class="card-body p-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-success text-white rounded-circle p-3 d-flex align-items-center justify-content-center" style="width:48px;height:48px;">
                    <i class="bi bi-check-lg fs-4"></i>
                </div>
                <div>
                    <div class="badge bg-success mb-1">Currently Active Cycle</div>
                    <h2 class="h5 mb-0 fw-bold">{{ $active->displayName }} ({{ $active->academic_year }})</h2>
                    <div class="small text-muted">
                        Passing Stanine: <strong>{{ $active->passing_stanine }}</strong> &nbsp;·&nbsp;
                        Exam: {{ $active->exam_weight }}% / GWA: {{ $active->gwa_weight }}% / Interview: {{ $active->interview_weight }}%
                    </div>
                </div>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a class="btn btn-outline-primary btn-sm" href="{{ route('admin.admission.masterlist', ['cycle_id' => $active->id]) }}">
                    <i class="bi bi-people me-1"></i> Examinee Roster ({{ $active->applicants()->count() }})
                </a>
                <form method="post" action="{{ route('admin.admission.cycles.complete', $active) }}"
                      onsubmit="return confirm('Are you sure you want to mark cycle \'{{ $active->displayName }}\' as Completed and Archive it? This will lock all applicant entries and exam scores from further modification.');">
                    @csrf
                    <button class="btn btn-warning btn-sm fw-semibold">
                        <i class="bi bi-archive-fill me-1"></i> Mark Cycle as Completed / Archive Cycle
                    </button>
                </form>
            </div>
        </div>
    </div>
@else
    {{-- ── MANDATORY GATEKEEPER LANDING SCREEN: NO ACTIVE CYCLE ── --}}
    <div class="card page-card border-warning border-3 shadow-sm mb-4" id="gatekeeperLandingCard">
        <div class="card-body p-4">
            <div class="d-flex align-items-start gap-3">
                <div class="bg-warning text-dark rounded-circle p-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width:52px;height:52px;">
                    <i class="bi bi-shield-exclamation fs-3"></i>
                </div>
                <div class="flex-grow-1">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="badge bg-warning text-dark fw-bold text-uppercase">Gatekeeper Enforcement</span>
                        <span class="badge bg-danger text-white fw-bold">Admission Operations Locked</span>
                    </div>
                    <h2 class="h4 fw-bold text-dark mb-1">Active Admission Cycle Required</h2>
                    <p class="text-muted small mb-3">
                        Access to the <strong>Masterlist</strong>, <strong>Encoding Sheet</strong>, <strong>CSV Import</strong>, and <strong>Test Sessions</strong> is strictly blocked until an Admission Cycle is activated. Choose one of the mandatory options below to proceed:
                    </p>

                    <div class="row g-3">
                        {{-- Option A: Select existing cycle --}}
                        <div class="col-md-6">
                            <div class="p-3 border rounded-3 bg-light h-100 d-flex flex-column justify-content-between">
                                <div>
                                    <div class="fw-bold text-primary mb-1">
                                        <i class="bi bi-check2-circle me-1"></i> Option A: Select Existing Cycle to Activate
                                    </div>
                                    <p class="small text-muted mb-2">
                                        Activate an existing cycle from the system to resume operations.
                                    </p>
                                </div>
                                @php
                                    $availableCycles = $cycles->filter(fn($c) => !$c->isActive());
                                @endphp
                                @if($availableCycles->isNotEmpty())
                                    <form method="post" action="{{ route('admin.admission.cycles.activate', $availableCycles->first()) }}" id="gatekeeperActivateForm">
                                        @csrf
                                        <div class="input-group input-group-sm">
                                            <select class="form-select form-select-sm fw-semibold" id="gatekeeperCycleSelect" onchange="document.getElementById('gatekeeperActivateForm').action = '/admin/admission/cycles/' + this.value + '/activate'">
                                                @foreach($availableCycles as $c)
                                                    <option value="{{ $c->id }}">
                                                        {{ $c->displayName }} ({{ $c->academic_year }}) — {{ $c->status }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <button type="submit" class="btn btn-primary btn-sm">
                                                <i class="bi bi-lightning-fill me-1"></i> Set Active
                                            </button>
                                        </div>
                                    </form>
                                @else
                                    <p class="small text-muted mb-0 fst-italic">No previous cycles found in database.</p>
                                @endif
                            </div>
                        </div>

                        {{-- Option B: Initialize new cycle --}}
                        <div class="col-md-6">
                            <div class="p-3 border rounded-3 bg-light h-100 d-flex flex-column justify-content-between">
                                <div>
                                    <div class="fw-bold text-success mb-1">
                                        <i class="bi bi-plus-circle me-1"></i> Option B: Initialize New Admission Cycle
                                    </div>
                                    <p class="small text-muted mb-2">
                                        Initialize a fresh operational cycle (e.g., <strong>S.Y. 2026 – 2027</strong>) for new applicants.
                                    </p>
                                </div>
                                <button type="button" class="btn btn-success btn-sm w-100 fw-semibold" data-bs-toggle="modal" data-bs-target="#initCycleModal">
                                    <i class="bi bi-plus-lg me-1"></i> Initialize &amp; Activate New Cycle
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif

<div class="row g-4">
    {{-- ── LEFT: ADMISSION CYCLES TABLE ── --}}
    <div class="col-lg-6">
        <div class="card page-card shadow-sm h-100">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h2 class="h6 section-title mb-0 fw-bold"><i class="bi bi-clock-history me-1"></i> Admission Cycles Roster</h2>
                <span class="badge bg-light text-dark border">{{ $cycles->count() }} cycles registered</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Cycle Name</th>
                                <th>Academic Year</th>
                                <th class="text-center">Examinees</th>
                                <th>Status</th>
                                <th class="pe-3 text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse ($cycles as $cycle)
                            <tr class="{{ $cycle->isActive() ? 'table-success bg-opacity-10' : ($cycle->isCompleted() ? 'table-light opacity-75' : '') }}">
                                <td class="ps-3 fw-bold">
                                    {{ $cycle->displayName }}
                                    @if($cycle->isActive())
                                        <i class="bi bi-star-fill text-warning ms-1" title="Currently Active"></i>
                                    @endif
                                </td>
                                <td>{{ $cycle->academic_year }}</td>
                                <td class="text-center">
                                    <span class="badge bg-light text-primary border font-monospace">
                                        {{ $cycle->applicants_count ?? $cycle->applicants()->count() }}
                                    </span>
                                </td>
                                <td>
                                    @if ($cycle->isCompleted())
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">
                                            Completed / Archived
                                        </span>
                                    @elseif ($cycle->status === 'Maintenance')
                                        <span class="badge bg-warning text-dark">
                                            <i class="bi bi-cone-striped me-1"></i>Maintenance
                                        </span>
                                    @elseif ($cycle->isActive())
                                        <span class="badge bg-success">
                                            Active
                                        </span>
                                    @else
                                        <span class="badge bg-light text-dark border">
                                            Draft
                                        </span>
                                    @endif
                                </td>
                                <td class="pe-3 text-end">
                                    <div class="btn-group btn-group-sm">
                                        @if ($cycle->isDraft())
                                            <form method="post" action="{{ route('admin.admission.cycles.activate', $cycle) }}" class="d-inline">
                                                @csrf
                                                <button class="btn btn-sm btn-outline-success" title="Set as the ONE active admission cycle">
                                                    <i class="bi bi-lightning-fill me-1"></i> Activate
                                                </button>
                                            </form>
                                        @endif

                                        <a class="btn btn-sm btn-outline-primary"
                                           href="{{ route('admin.admission.masterlist', ['cycle_id' => $cycle->id]) }}"
                                           title="Inspect applicant roster and historical records">
                                            <i class="bi bi-table"></i> Masterlist
                                        </a>
                                        @unless($cycle->isCompleted())
                                            <a class="btn btn-sm btn-outline-secondary"
                                               href="{{ route('admin.admission.encoding-sheet', ['cycle_id' => $cycle->id]) }}"
                                               title="Open Encoding Sheet">
                                                <i class="bi bi-grid-3x3"></i>
                                            </a>
                                        @endunless

                                        @unless($cycle->isCompleted())
                                            <button type="button" class="btn btn-sm btn-outline-secondary"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#editCycle{{ $cycle->id }}">
                                                <i class="bi bi-pencil"></i>
                                            </button>

                                            {{-- ── MAINTENANCE MODE TOGGLE ── --}}
                                            @if($cycle->status === 'Maintenance')
                                                <form method="post" action="{{ route('admin.admission.cycles.activate', $cycle) }}" class="d-inline"
                                                      onsubmit="return confirm('Exit Maintenance Mode and re-activate this cycle?');">
                                                    @csrf
                                                    <button class="btn btn-sm btn-warning fw-semibold" title="Exit Maintenance Mode">
                                                        <i class="bi bi-cone-striped me-1"></i> Exit Maintenance
                                                    </button>
                                                </form>
                                            @elseif($cycle->isActive())
                                                <form method="post" action="{{ route('admin.admission.cycles.save', $cycle) }}" class="d-inline"
                                                      onsubmit="return confirm('Put this cycle in Maintenance Mode? The encoding sheet and masterlist will be locked until you exit maintenance.');">
                                                    @csrf
                                                    <input type="hidden" name="status" value="Maintenance">
                                                    <button class="btn btn-sm btn-outline-warning" title="Enable Maintenance Mode">
                                                        <i class="bi bi-cone-striped"></i> Maintenance
                                                    </button>
                                                </form>
                                            @endif

                                            <form method="post" action="{{ route('admin.admission.cycles.archive', $cycle) }}"
                                                  onsubmit="return confirm('Archive this cycle? It will become read-only.');"
                                                  class="d-inline">
                                                @csrf
                                                <button class="btn btn-sm btn-outline-danger" title="Archive cycle">
                                                    <i class="bi bi-archive"></i>
                                                </button>
                                            </form>
                                        @endunless
                                    </div>
                                </td>
                            </tr>

                            {{-- Edit modal --}}
                            @unless($cycle->isCompleted())
                            <div class="modal fade" id="editCycle{{ $cycle->id }}" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h3 class="modal-title h5">Edit: {{ $cycle->displayName }}</h3>
                                            <button class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form method="post" action="{{ route('admin.admission.cycles.save', $cycle) }}">
                                            @csrf
                                            <div class="modal-body row g-2">
                                                <div class="col-6">
                                                    <label class="form-label form-label-sm fw-semibold">Cycle Name</label>
                                                    <input name="cycle_name" value="{{ $cycle->displayName }}" class="form-control form-control-sm" required>
                                                </div>
                                                <div class="col-6">
                                                    <label class="form-label form-label-sm fw-semibold">Academic Year</label>
                                                    <input name="academic_year" value="{{ $cycle->academic_year }}" class="form-control form-control-sm" required>
                                                </div>
                                                <div class="col-4">
                                                    <label class="form-label form-label-sm fw-semibold">Passing Stanine</label>
                                                    <input type="number" name="passing_stanine" value="{{ $cycle->passing_stanine }}" min="1" max="9" class="form-control form-control-sm">
                                                </div>
                                                <div class="col-4">
                                                    <label class="form-label form-label-sm fw-semibold">Exam %</label>
                                                    <input type="number" name="exam_weight" value="{{ $cycle->exam_weight }}" class="form-control form-control-sm">
                                                </div>
                                                <div class="col-4">
                                                    <label class="form-label form-label-sm fw-semibold">GWA %</label>
                                                    <input type="number" name="gwa_weight" value="{{ $cycle->gwa_weight }}" class="form-control form-control-sm">
                                                </div>
                                                <div class="col-4">
                                                    <label class="form-label form-label-sm fw-semibold">Interview %</label>
                                                    <input type="number" name="interview_weight" value="{{ $cycle->interview_weight }}" class="form-control form-control-sm">
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button class="btn btn-primary btn-sm">Save Changes</button>
                                                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            @endunless
                        @empty
                            <tr><td colspan="4" class="text-center text-muted py-4">No admission cycles found. Click "Initialize New Cycle" above.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- ── RIGHT: ACTIVE CYCLE CONFIGURATION (Quotas & Answer Key) ── --}}
    <div class="col-lg-6">
        @if ($active)
        {{-- Seat Quotas --}}
        <div class="card page-card shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h2 class="h6 section-title mb-0 fw-bold">Seat Quotas — {{ $active->displayName }}</h2>
            </div>
            <div class="card-body p-4">
                <form method="post" action="{{ route('admin.admission.quotas.save') }}" class="row g-2">
                    @csrf
                    <div class="col-md-7">
                        <label class="form-label form-label-sm">Academic Program</label>
                        <select name="course_code" class="form-select form-select-sm" required>
                            @foreach ($courses as $code => $title)
                                <option value="{{ $code }}">{{ $code }} — {{ $title }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label form-label-sm">Seat Quota</label>
                        <input type="number" min="0" name="seats" class="form-control form-control-sm"
                               placeholder="e.g. 50" required>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button class="btn btn-primary btn-sm w-100">Save</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Answer Key --}}
        <div class="card page-card shadow-sm" id="answer-key">
            <div class="card-header bg-white py-3">
                <h2 class="h6 section-title mb-0 fw-bold">Official Answer Key (80 Items) — {{ $active->displayName }}</h2>
            </div>
            <div class="card-body p-4">
                <form method="post" action="{{ route('admin.admission.answer-key.save') }}">
                    @csrf
                    @php
                        $keys = \Illuminate\Support\Facades\DB::table('admission_answer_keys')
                            ->where('admission_cycle_id', $active->id)
                            ->pluck('correct_answer', 'item_number');
                    @endphp
                    <div class="row g-1" style="max-height:300px;overflow-y:auto">
                        @for ($i = 1; $i <= 80; $i++)
                        <div class="col-6 col-md-3">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text" style="min-width:36px">{{ $i }}</span>
                                <select name="answers[{{ $i }}]" class="form-select form-select-sm" required>
                                    <option value="">–</option>
                                    @foreach (['A','B','C','D'] as $letter)
                                        <option value="{{ $letter }}" @selected(($keys[$i] ?? null) === $letter)>{{ $letter }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        @endfor
                    </div>
                    <button class="btn btn-primary btn-sm mt-3">
                        <i class="bi bi-save me-1"></i> Save Answer Key
                    </button>
                </form>
            </div>
        </div>

        @else
            <div class="card page-card shadow-sm h-100">
                <div class="card-body p-5 text-center text-muted d-flex flex-column align-items-center justify-content-center">
                    <i class="bi bi-lock-fill fs-1 mb-3 opacity-25"></i>
                    <h3 class="h5 fw-bold">Quotas &amp; Answer Key Locked</h3>
                    <p class="small mb-0">Initialize or activate an admission cycle to unlock seat quotas and the answer key matrix.</p>
                </div>
            </div>
        @endif
    </div>
</div>

{{-- ── INITIALIZE NEW CYCLE MODAL ── --}}
<div class="modal fade" id="initCycleModal" tabindex="-1" aria-labelledby="initCycleLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title h5" id="initCycleLabel">Initialize New Admission Cycle</h3>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="{{ route('admin.admission.cycles.save') }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold" for="new-cycle-name">Cycle Name <span class="text-danger">*</span></label>
                        <input class="form-control" id="new-cycle-name" name="cycle_name"
                               placeholder="e.g. S.Y. 2027 – 2028" required>
                        <div class="form-text">Follow the standard institutional naming: <code>S.Y. [Start Year] – [End Year]</code></div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label form-label-sm fw-semibold" for="new-academic-year">Academic Year</label>
                            <input class="form-control form-control-sm" id="new-academic-year" name="academic_year"
                                   placeholder="e.g. 2027-2028">
                        </div>
                        <div class="col-6">
                            <label class="form-label form-label-sm fw-semibold" for="new-passing-stanine">Passing Stanine</label>
                            <input type="number" class="form-control form-control-sm" id="new-passing-stanine" name="passing_stanine"
                                   value="4" min="1" max="9" required>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-4">
                            <label class="form-label form-label-sm fw-semibold">Exam %</label>
                            <input type="number" class="form-control form-control-sm" name="exam_weight" value="60" required>
                        </div>
                        <div class="col-4">
                            <label class="form-label form-label-sm fw-semibold">GWA %</label>
                            <input type="number" class="form-control form-control-sm" name="gwa_weight" value="20" required>
                        </div>
                        <div class="col-4">
                            <label class="form-label form-label-sm fw-semibold">Interview %</label>
                            <input type="number" class="form-control form-control-sm" name="interview_weight" value="20" required>
                        </div>
                    </div>

                    <div class="form-check form-switch p-3 bg-light rounded border mb-1">
                        <input class="form-check-input ms-0 me-2" type="checkbox" role="switch"
                               id="set_active" name="set_active" value="1" checked>
                        <label class="form-check-label fw-semibold" for="set_active">
                            Set as the ONE Active Cycle immediately
                        </label>
                        <div class="small text-muted mt-1">
                            Activating this new cycle will set any prior active cycle to Completed / Archived or Draft.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-check2-circle me-1"></i> Save &amp; Initialize Cycle
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection
