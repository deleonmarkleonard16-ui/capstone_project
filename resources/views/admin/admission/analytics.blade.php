@extends('layouts.app')
@section('content')

{{-- ══════════════════════════════════════════════════════════════════
     ADMISSION ANALYTICS & HISTORIC ARCHIVE INSPECTOR
     Supports live analysis of Active cycles as well as deep inspection
     of past completed/archived cycles in strictly read-only mode.
     ══════════════════════════════════════════════════════════════════ --}}

<div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1">
            <i class="bi bi-graph-up-arrow text-primary me-2"></i>Admission Analytics &amp; Historic Inspector
        </h1>
        <p class="text-muted small mb-0">
            Psychometric qualification statistics, program distribution, and historic cycle performance.
        </p>
    </div>

    {{-- Top Action & Export Buttons --}}
    @if ($cycle)
    <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-outline-primary btn-sm" href="{{ route('admin.admission.masterlist', ['cycle_id' => $cycle->id]) }}">
            <i class="bi bi-table me-1"></i> Inspect Masterlist Roster
        </a>
        <a class="btn btn-primary btn-sm" href="{{ route('admin.admission.report', ['cycle_id' => $cycle->id, 'type' => 'summary', 'format' => 'docx']) }}">
            <i class="bi bi-file-earmark-word me-1"></i> Download Evaluation (DOCX)
        </a>
        <a class="btn btn-outline-danger btn-sm" href="{{ route('admin.admission.report', ['cycle_id' => $cycle->id, 'type' => 'summary', 'format' => 'pdf']) }}" target="_blank">
            <i class="bi bi-file-earmark-pdf me-1"></i> PDF Summary
        </a>
    </div>
    @endif
</div>

{{-- ── CYCLE SELECTOR DROPDOWN (HISTORIC ARCHIVE VIEWER) ── --}}
<div class="card page-card shadow-sm mb-4">
    <div class="card-body p-3">
        <form method="get" action="{{ route('admin.admission.analytics') }}" class="row g-2 align-items-center">
            <div class="col-md-2">
                <label for="analytics-cycle" class="form-label form-label-sm fw-bold text-muted mb-0">
                    <i class="bi bi-clock-history me-1"></i> Admission Cycle:
                </label>
            </div>
            <div class="col-md-7">
                <select id="analytics-cycle" name="cycle_id" class="form-select form-select-sm fw-bold border-primary" onchange="this.form.submit()">
                    @foreach($cycles as $option)
                        <option value="{{ $option->id }}" @selected($cycle?->id === $option->id)>
                            Viewing: {{ $option->displayName }} [{{ $option->isCompleted() ? 'Archived' : ($option->isActive() ? 'Active' : 'Draft') }}]
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 text-end">
                <span class="badge bg-light text-dark border">
                    {{ $cycles->count() }} cycle(s) registered
                </span>
            </div>
        </form>
    </div>
</div>

@if ($cycle)
    {{-- ── HISTORIC ARCHIVE BANNER IF ARCHIVED ── --}}
    @if ($cycle->isCompleted())
        <div class="alert alert-secondary border d-flex align-items-center justify-content-between flex-wrap gap-2 py-3 px-3 mb-4">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-shield-lock-fill fs-3 text-secondary"></i>
                <div>
                    <h6 class="mb-0 fw-bold">Historic Archive Mode: Viewing {{ $cycle->displayName }} [Archived]</h6>
                    <span class="small text-muted">
                        Inspecting past evaluation data for Academic Year <strong>{{ $cycle->academic_year }}</strong>. All records are strictly read-only to preserve institutional historic integrity.
                    </span>
                </div>
            </div>
            @php $activeCycle = \App\Models\AdmissionCycle::active(); @endphp
            @if ($activeCycle && $activeCycle->id !== $cycle->id)
                <a href="{{ route('admin.admission.analytics', ['cycle_id' => $activeCycle->id]) }}" class="btn btn-sm btn-primary">
                    <i class="bi bi-arrow-return-left me-1"></i> Switch to Active Cycle
                </a>
            @endif
        </div>
    @elseif ($cycle->isActive())
        <div class="alert alert-success bg-opacity-10 border border-success d-flex align-items-center gap-2 py-2 px-3 mb-4">
            <i class="bi bi-star-fill text-success fs-5"></i>
            <div>
                <strong>Active Cycle:</strong> Currently inspecting the live operational admission cycle for <strong>{{ $cycle->academic_year }}</strong>.
            </div>
        </div>
    @endif

    @php
        $total = (int) $statuses->sum();
        $qualified = (int) ($statuses['Qualified'] ?? 0);
        $notQualified = (int) ($statuses['Not Qualified'] ?? 0);
        $pending = (int) ($statuses['Pending'] ?? 0);
        $passRate = $total > 0 ? round(($qualified / $total) * 100, 1) : 0;
    @endphp

    {{-- ── 4 EXECUTIVE METRIC CARDS ── --}}
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white border-start border-4 border-primary">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Total Examinees</div>
                        <div class="h2 fw-bold text-dark mb-0 mt-1">{{ number_format($total) }}</div>
                        <div class="text-muted small mt-1">Recorded in Roster</div>
                    </div>
                    <div class="rounded-circle bg-primary bg-opacity-10 p-3 text-primary">
                        <i class="bi bi-people-fill fs-3"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white border-start border-4 border-success">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Qualified</div>
                        <div class="h2 fw-bold text-success mb-0 mt-1">{{ number_format($qualified) }}</div>
                        <div class="text-success small mt-1 fw-semibold">{{ $passRate }}% Qualification Rate</div>
                    </div>
                    <div class="rounded-circle bg-success bg-opacity-10 p-3 text-success">
                        <i class="bi bi-check-circle-fill fs-3"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white border-start border-4 border-danger">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Not Qualified</div>
                        <div class="h2 fw-bold text-danger mb-0 mt-1">{{ number_format($notQualified) }}</div>
                        <div class="text-muted small mt-1">Below Stanine / Quota</div>
                    </div>
                    <div class="rounded-circle bg-danger bg-opacity-10 p-3 text-danger">
                        <i class="bi bi-x-circle-fill fs-3"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white border-start border-4 border-warning">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Pending / In-Review</div>
                        <div class="h2 fw-bold text-warning mb-0 mt-1">{{ number_format($pending) }}</div>
                        <div class="text-muted small mt-1">Awaiting Interview/Exam</div>
                    </div>
                    <div class="rounded-circle bg-warning bg-opacity-10 p-3 text-warning">
                        <i class="bi bi-hourglass-split fs-3"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ── DETAILED HISTORIC BREAKDOWN TABLES ── --}}
    <div class="row g-4">
        {{-- Qualification Tally --}}
        <div class="col-lg-5">
            <div class="card page-card shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <h2 class="h6 section-title mb-0 fw-bold">
                        <i class="bi bi-pie-chart-fill me-1 text-primary"></i> Qualification Distribution
                    </h2>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Status</th>
                                    <th class="text-center">Count</th>
                                    <th class="pe-3 text-end">Percentage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="ps-3 fw-semibold">
                                        <span class="badge bg-success me-2">Qualified</span> Passing Stanine &ge; {{ $cycle->passing_stanine }}
                                    </td>
                                    <td class="text-center fw-bold">{{ number_format($qualified) }}</td>
                                    <td class="pe-3 text-end fw-semibold text-success">{{ $total > 0 ? round(($qualified / $total) * 100, 1) : 0 }}%</td>
                                </tr>
                                <tr>
                                    <td class="ps-3 fw-semibold">
                                        <span class="badge bg-danger me-2">Not Qualified</span> Below Passing Stanine
                                    </td>
                                    <td class="text-center fw-bold">{{ number_format($notQualified) }}</td>
                                    <td class="pe-3 text-end fw-semibold text-danger">{{ $total > 0 ? round(($notQualified / $total) * 100, 1) : 0 }}%</td>
                                </tr>
                                <tr>
                                    <td class="ps-3 fw-semibold">
                                        <span class="badge bg-warning text-dark me-2">Pending</span> Awaiting Scores
                                    </td>
                                    <td class="text-center fw-bold">{{ number_format($pending) }}</td>
                                    <td class="pe-3 text-end fw-semibold text-warning">{{ $total > 0 ? round(($pending / $total) * 100, 1) : 0 }}%</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-light py-2 text-muted small">
                    <i class="bi bi-info-circle me-1"></i> Scoring Weights: Exam {{ $cycle->exam_weight }}%, GWA {{ $cycle->gwa_weight }}%, Interview {{ $cycle->interview_weight }}%.
                </div>
            </div>
        </div>

        {{-- Program / Course Choice Breakdown --}}
        <div class="col-lg-7">
            <div class="card page-card shadow-sm h-100">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h2 class="h6 section-title mb-0 fw-bold">
                        <i class="bi bi-mortarboard-fill me-1 text-primary"></i> Program First Choice Breakdown
                    </h2>
                    <span class="badge bg-light text-dark border">{{ $courseStats->count() }} Programs</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Program Code</th>
                                    <th>Program Title</th>
                                    <th class="text-center">Total</th>
                                    <th class="text-center">Qualified</th>
                                    <th class="pe-3 text-end">Pass Rate</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($courseStats as $cs)
                                    @php
                                        $cTotal = (int) $cs->total;
                                        $cQual = (int) $cs->qualified_count;
                                        $cRate = $cTotal > 0 ? round(($cQual / $cTotal) * 100, 1) : 0;
                                    @endphp
                                    <tr>
                                        <td class="ps-3 fw-bold font-monospace text-primary">{{ $cs->course_choice }}</td>
                                        <td class="small">{{ \App\Support\CourseCatalog::label($cs->course_choice) }}</td>
                                        <td class="text-center fw-semibold">{{ number_format($cTotal) }}</td>
                                        <td class="text-center fw-bold text-success">{{ number_format($cQual) }}</td>
                                        <td class="pe-3 text-end fw-semibold">{{ $cRate }}%</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">No program data recorded for this cycle.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-light py-2 text-end">
                    <a href="{{ route('admin.admission.report', ['cycle_id' => $cycle->id, 'type' => 'summary']) }}" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-file-earmark-text me-1"></i> View Full Evaluation Report
                    </a>
                </div>
            </div>
        </div>
    </div>
@else
    <div class="card page-card shadow-sm p-5 text-center">
        <i class="bi bi-calendar-x fs-1 text-muted mb-2"></i>
        <h4 class="fw-bold">No Admission Cycles Available</h4>
        <p class="text-muted small">No admission cycles available. Please initialize or activate an admission cycle to view analytics.</p>
        <div>
            <a href="{{ route('admin.admission.index') }}" class="btn btn-primary btn-sm">
                Go to Admission Cycle Hub
            </a>
        </div>
    </div>
@endif

@endsection
