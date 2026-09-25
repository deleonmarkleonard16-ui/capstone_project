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
                                                    <label class="form-label form-label-sm fw-semibold">
                                                        Total Items
                                                        <span class="text-danger">*</span>
                                                    </label>
                                                    <input type="number" name="total_items" value="{{ $cycle->total_items ?: 80 }}" min="10" max="200" class="form-control form-control-sm" required>
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
                                                @if($cycle->total_items && $cycle->total_items !== 80)
                                                <div class="col-12">
                                                    <div class="alert alert-warning py-2 small mb-0">
                                                        <i class="bi bi-exclamation-triangle me-1"></i>
                                                        Changing Total Items will resize the answer key grid and may trigger a rescore of all existing submissions.
                                                    </div>
                                                </div>
                                                @endif
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
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h2 class="h6 section-title mb-0 fw-bold">
                    <i class="bi bi-list-ol me-1"></i>
                    Official Answer Key
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle ms-1" id="key-item-badge">{{ $active->total_items }} Items</span>
                    — {{ $active->displayName }}
                </h2>
                <div class="d-flex align-items-center gap-2">
                    <label class="form-label form-label-sm mb-0 fw-semibold text-muted" for="total_items_setter">Total Items:</label>
                    <input type="number" id="total_items_setter" class="form-control form-control-sm"
                           style="width:90px" min="10" max="200"
                           value="{{ $active->total_items ?: 80 }}"
                           title="Set total exam item count (10–200). Changing this will dynamically resize the grid below.">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="applyItemCount" title="Apply new item count to grid">
                        <i class="bi bi-arrow-repeat me-1"></i> Apply
                    </button>
                </div>
            </div>
            <div class="card-body p-4">
                <form method="post" action="{{ route('admin.admission.answer-key.save') }}" id="answerKeyForm">
                    @csrf
                    <input type="hidden" name="total_items" id="hidden_total_items" value="{{ $active->total_items ?: 80 }}">
                    @php
                        $keys = \Illuminate\Support\Facades\DB::table('admission_answer_keys')
                            ->where('admission_cycle_id', $active->id)
                            ->pluck('correct_answer', 'item_number');
                        $configuredItems = (int) ($active->total_items ?: 80);
                    @endphp
                    <div class="row g-1" id="answerKeyGrid" style="max-height:380px;overflow-y:auto">
                        @for ($i = 1; $i <= $configuredItems; $i++)
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
                    <div class="d-flex align-items-center gap-3 mt-3 flex-wrap">
                        <button class="btn btn-primary btn-sm">
                            <i class="bi bi-save me-1"></i> Save Answer Key &amp; Re-Score
                        </button>
                        <span class="small text-muted">
                            <i class="bi bi-info-circle me-1"></i>
                            Saving updates <strong id="item-count-label">{{ $configuredItems }}</strong> answer slots and re-scores all existing submissions.
                        </span>
                    </div>
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

                    <div class="mb-3">
                        <label class="form-label form-label-sm fw-semibold" for="new-total-items">
                            Total Exam Items <span class="text-danger">*</span>
                            <span class="badge bg-info-subtle text-info border border-info-subtle ms-1">PSU-CAT</span>
                        </label>
                        <div class="input-group input-group-sm">
                            <input type="number" class="form-control form-control-sm" id="new-total-items" name="total_items"
                                   value="80" min="10" max="200" required>
                            <span class="input-group-text text-muted">items (10–200)</span>
                        </div>
                        <div class="form-text">
                            Common PSU-CAT configurations:
                            <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" onclick="document.getElementById('new-total-items').value=80">80</button>,
                            <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" onclick="document.getElementById('new-total-items').value=100">100</button>,
                            <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" onclick="document.getElementById('new-total-items').value=120">120</button>,
                            <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" onclick="document.getElementById('new-total-items').value=144">144</button>
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

@push('scripts')
<script>
{{-- ── DYNAMIC ANSWER KEY GRID RESIZER ── --}}
(function () {
    const grid = document.getElementById('answerKeyGrid');
    const setter = document.getElementById('total_items_setter');
    const hiddenInput = document.getElementById('hidden_total_items');
    const badge = document.getElementById('key-item-badge');
    const label = document.getElementById('item-count-label');
    const form = document.getElementById('answerKeyForm');

    if (!grid || !setter) return;

    function buildItem(i, savedAnswer) {
        const col = document.createElement('div');
        col.className = 'col-6 col-md-3';
        const group = document.createElement('div');
        group.className = 'input-group input-group-sm';
        const span = document.createElement('span');
        span.className = 'input-group-text';
        span.style.minWidth = '36px';
        span.textContent = i;
        const sel = document.createElement('select');
        sel.name = `answers[${i}]`;
        sel.className = 'form-select form-select-sm';
        sel.required = true;
        [['', '–'], ['A','A'], ['B','B'], ['C','C'], ['D','D']].forEach(([val, text]) => {
            const opt = new Option(text, val);
            if (savedAnswer && val === savedAnswer) opt.selected = true;
            sel.appendChild(opt);
        });
        group.appendChild(span);
        group.appendChild(sel);
        col.appendChild(group);
        return col;
    }

    document.getElementById('applyItemCount').addEventListener('click', function () {
        const newCount = parseInt(setter.value, 10);
        if (isNaN(newCount) || newCount < 10 || newCount > 200) {
            setter.classList.add('is-invalid');
            return;
        }
        setter.classList.remove('is-invalid');

        const currentItems = grid.querySelectorAll('select');
        const currentCount = currentItems.length;

        // Preserve existing answers
        const saved = {};
        currentItems.forEach(sel => {
            const m = sel.name.match(/answers\[(\d+)\]/);
            if (m) saved[m[1]] = sel.value;
        });

        if (newCount > currentCount) {
            // Append new items
            for (let i = currentCount + 1; i <= newCount; i++) {
                grid.appendChild(buildItem(i, saved[i] || ''));
            }
        } else if (newCount < currentCount) {
            // Remove excess items from end
            const items = grid.querySelectorAll('.col-6');
            for (let i = items.length - 1; i >= newCount; i--) {
                items[i].remove();
            }
        }

        hiddenInput.value = newCount;
        if (badge) badge.textContent = `${newCount} Items`;
        if (label) label.textContent = newCount;
    });
})();
</script>
@endpush

@endsection

