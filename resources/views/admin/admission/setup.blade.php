@extends('layouts.app')
@section('content')

{{-- ═══════════════════════════════════════════════════════════
     PSU-CAT ADMISSION CYCLE SETUP — Gatekeeper Page
     All admission sub-pages redirect here if no active cycle exists.
     ═══════════════════════════════════════════════════════════ --}}

<div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">PSU-CAT Admission</h1>
        <p class="text-muted mb-0">
            Configure and activate an admission cycle to unlock the Masterlist, Encoding Sheet, and Exam modules.
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        @if ($active)
            <a class="btn btn-outline-primary" href="{{ route('admin.admission.masterlist') }}">
                <i class="bi bi-table me-1"></i> Open Masterlist
            </a>
            <a class="btn btn-outline-primary" href="{{ route('admin.admission.encoding-sheet') }}">
                <i class="bi bi-grid-3x3 me-1"></i> Encoding Sheet
            </a>
        @endif
        <a class="btn btn-outline-secondary" href="{{ route('admin.admission.proctoring') }}">
            <i class="bi bi-camera-video me-1"></i> Proctoring
        </a>
    </div>
</div>

{{-- ── ACTIVE CYCLE BANNER ── --}}
@if ($active)
    <div class="alert alert-success d-flex align-items-center gap-3 mb-4">
        <i class="bi bi-check-circle-fill fs-4"></i>
        <div>
            <strong>Active Cycle: {{ $active->name }}</strong> ({{ $active->academic_year }}) &nbsp;·&nbsp;
            Passing Stanine: {{ $active->passing_stanine }} &nbsp;·&nbsp;
            Weights: Exam {{ $active->exam_weight }}% / GWA {{ $active->gwa_weight }}% / Interview {{ $active->interview_weight }}%
        </div>
    </div>
@else
    <div class="alert alert-warning d-flex align-items-center gap-3 mb-4">
        <i class="bi bi-exclamation-triangle-fill fs-4"></i>
        <div>
            <strong>No active admission cycle.</strong>
            Create and activate a cycle below to unlock the Masterlist, Encoding Sheet, and Exam modules.
        </div>
    </div>
@endif

<div class="row g-4">

    {{-- ── LEFT: CYCLES TABLE ── --}}
    <div class="col-lg-6">
        <div class="card page-card h-100">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2 class="h5 section-title mb-0">Admission Cycles</h2>
                    <button class="btn btn-primary btn-sm" data-bs-toggle="collapse" data-bs-target="#createCycleForm">
                        <i class="bi bi-plus-lg me-1"></i> New Cycle
                    </button>
                </div>

                {{-- Create form (collapsible) --}}
                <div class="collapse mb-3" id="createCycleForm">
                    <div class="border rounded-3 p-3 bg-light">
                        <h3 class="h6 mb-3">Create Admission Cycle</h3>
                        <form method="post" action="{{ route('admin.admission.cycles.save') }}" class="row g-2">
                            @csrf
                            <div class="col-md-6">
                                <label class="form-label form-label-sm">Cycle Name</label>
                                <input name="name" class="form-control form-control-sm"
                                       placeholder="S.Y. 2026 – 2027" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label form-label-sm">Academic Year</label>
                                <input name="academic_year" class="form-control form-control-sm"
                                       placeholder="2026-2027" required>
                            </div>
                            <div class="col-4">
                                <label class="form-label form-label-sm">Passing Stanine</label>
                                <input type="number" name="passing_stanine" value="4" min="1" max="9"
                                       class="form-control form-control-sm">
                            </div>
                            <div class="col-4">
                                <label class="form-label form-label-sm">Exam Weight %</label>
                                <input type="number" name="exam_weight" value="60" class="form-control form-control-sm">
                            </div>
                            <div class="col-4">
                                <label class="form-label form-label-sm">GWA Weight %</label>
                                <input type="number" name="gwa_weight" value="20" class="form-control form-control-sm">
                            </div>
                            <div class="col-4">
                                <label class="form-label form-label-sm">Interview Weight %</label>
                                <input type="number" name="interview_weight" value="20" class="form-control form-control-sm">
                            </div>
                            <div class="col-12">
                                <button class="btn btn-primary btn-sm">Save Cycle</button>
                                <button type="button" class="btn btn-link btn-sm"
                                        data-bs-toggle="collapse" data-bs-target="#createCycleForm">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>

                {{-- Cycles table --}}
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th>Cycle</th>
                                <th>Year</th>
                                <th>Stanine</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse ($cycles as $cycle)
                            <tr>
                                <td class="fw-semibold">{{ $cycle->name }}</td>
                                <td>{{ $cycle->academic_year }}</td>
                                <td>{{ $cycle->passing_stanine }}</td>
                                <td>
                                    @if ($cycle->is_archived)
                                        <span class="badge bg-secondary">Archived</span>
                                    @elseif ($cycle->is_active)
                                        <span class="badge bg-success">Active</span>
                                    @else
                                        <span class="badge bg-light text-dark border">Draft</span>
                                    @endif
                                </td>
                                <td class="d-flex gap-1 flex-wrap">
                                    @unless ($cycle->is_archived)
                                        @unless ($cycle->is_active)
                                        <form method="post"
                                              action="{{ route('admin.admission.cycles.activate', $cycle) }}">
                                            @csrf
                                            <button class="btn btn-sm btn-success">
                                                <i class="bi bi-lightning-fill"></i> Activate
                                            </button>
                                        </form>
                                        @endunless
                                        <button class="btn btn-sm btn-outline-primary"
                                                data-bs-toggle="modal"
                                                data-bs-target="#editCycle{{ $cycle->id }}">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form method="post"
                                              action="{{ route('admin.admission.cycles.archive', $cycle) }}"
                                              onsubmit="return confirm('Archive this cycle? This cannot be undone.')">
                                            @csrf
                                            <button class="btn btn-sm btn-outline-secondary">
                                                <i class="bi bi-archive"></i>
                                            </button>
                                        </form>
                                    @endunless
                                </td>
                            </tr>
                            {{-- Edit modal --}}
                            <div class="modal fade" id="editCycle{{ $cycle->id }}" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h3 class="modal-title h5">Edit: {{ $cycle->name }}</h3>
                                            <button class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form method="post" action="{{ route('admin.admission.cycles.save', $cycle) }}">
                                            @csrf
                                            <div class="modal-body row g-2">
                                                <div class="col-6">
                                                    <label class="form-label">Cycle Name</label>
                                                    <input name="name" value="{{ $cycle->name }}" class="form-control" required>
                                                </div>
                                                <div class="col-6">
                                                    <label class="form-label">Academic Year</label>
                                                    <input name="academic_year" value="{{ $cycle->academic_year }}" class="form-control" required>
                                                </div>
                                                <div class="col-4">
                                                    <label class="form-label">Passing Stanine</label>
                                                    <input type="number" name="passing_stanine" value="{{ $cycle->passing_stanine }}" min="1" max="9" class="form-control">
                                                </div>
                                                <div class="col-4">
                                                    <label class="form-label">Exam %</label>
                                                    <input type="number" name="exam_weight" value="{{ $cycle->exam_weight }}" class="form-control">
                                                </div>
                                                <div class="col-4">
                                                    <label class="form-label">GWA %</label>
                                                    <input type="number" name="gwa_weight" value="{{ $cycle->gwa_weight }}" class="form-control">
                                                </div>
                                                <div class="col-4">
                                                    <label class="form-label">Interview %</label>
                                                    <input type="number" name="interview_weight" value="{{ $cycle->interview_weight }}" class="form-control">
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button class="btn btn-primary">Save Changes</button>
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">No cycles yet. Create one above.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- ── RIGHT: SEAT QUOTAS + ANSWER KEY ── --}}
    <div class="col-lg-6">

        @if ($active)

        {{-- Seat Quotas --}}
        <div class="card page-card mb-4">
            <div class="card-body p-4">
                <h2 class="h5 section-title mb-3">Seat Quotas — {{ $active->name }}</h2>
                <form method="post" action="{{ route('admin.admission.quotas.save') }}" class="row g-2">
                    @csrf
                    <div class="col-md-7">
                        <label class="form-label form-label-sm">Program</label>
                        <select name="course_code" class="form-select form-select-sm" required>
                            @foreach ($courses as $code => $title)
                                <option value="{{ $code }}">{{ $code }} — {{ $title }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label form-label-sm">Seats</label>
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
        <div class="card page-card">
            <div class="card-body p-4">
                <h2 class="h5 section-title mb-3">Answer Key — 80 Items</h2>
                <form method="post" action="{{ route('admin.admission.answer-key.save') }}">
                    @csrf
                    @php
                        $keys = \Illuminate\Support\Facades\DB::table('admission_answer_keys')
                            ->where('admission_cycle_id', $active->id)
                            ->pluck('correct_answer', 'item_number');
                    @endphp
                    <div class="row g-1" style="max-height:320px;overflow-y:auto">
                        @for ($i = 1; $i <= 80; $i++)
                        <div class="col-6 col-md-3">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text" style="min-width:36px">{{ $i }}</span>
                                <select name="answers[{{ $i }}]" class="form-select form-select-sm" required>
                                    <option value="">–</option>
                                    @foreach (['A','B','C','D'] as $letter)
                                        <option value="{{ $letter }}"
                                            @selected(($keys[$i] ?? null) === $letter)>
                                            {{ $letter }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        @endfor
                    </div>
                    <button class="btn btn-primary mt-3">
                        <i class="bi bi-save me-1"></i> Save Answer Key
                    </button>
                </form>
            </div>
        </div>

        @else
            <div class="card page-card">
                <div class="card-body p-5 text-center text-muted">
                    <i class="bi bi-lock-fill fs-1 mb-3 d-block opacity-25"></i>
                    <h3 class="h5">Seat Quotas & Answer Key Locked</h3>
                    <p>Activate an admission cycle on the left to configure seat quotas and the answer key.</p>
                </div>
            </div>
        @endif

    </div>
</div>

@endsection
