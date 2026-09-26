@extends('layouts.app')

@section('title', 'Executive Analytics Dashboard')

@section('content')
<div class="executive-analytics container-fluid px-2 px-md-3 py-2">
    {{-- ══════════════════════════════════════════════════════════════ --}}
    {{-- DASHBOARD HEADER & DYNAMIC SYSTEM CONTROLS --}}
    {{-- ══════════════════════════════════════════════════════════════ --}}
    <div class="card border-0 shadow-sm rounded-4 mb-3 bg-white">
        <div class="card-body p-3">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                <div>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-1 small text-muted">
                            <li class="breadcrumb-item"><a href="{{ route($role . '.dashboard') }}" class="text-decoration-none">Dashboard</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Executive Analytics</li>
                        </ol>
                    </nav>
                    <h1 class="h4 fw-bold text-dark mb-1 d-flex align-items-center gap-2">
                        <i class="bi bi-bar-chart-line-fill text-primary"></i>
                        <span>Executive Institutional Analytics Dashboard</span>
                    </h1>
                    <p class="text-muted small mb-0">
                        Digital Management System for Guidance Testing and Admission (DMSGTA) &bull; PSU San Carlos Campus
                    </p>
                </div>

                {{-- Dynamic System Quick Controls --}}
                <div class="d-flex flex-wrap align-items-center gap-2">
                    {{-- 1. Active Admission Cycle Selector --}}
                    <div class="d-flex align-items-center gap-1 bg-light p-1 rounded-3 border">
                        <label for="admissionCycleSelect" class="small fw-semibold text-secondary px-2 mb-0 d-none d-sm-inline">Cycle:</label>
                        <select id="admissionCycleSelect" class="form-select form-select-sm border-0 bg-transparent fw-bold text-primary" style="max-width: 200px;" onchange="switchAdmissionCycle(this.value)">
                            @foreach($controls['admissionCycles'] as $cycle)
                                <option value="{{ $cycle['id'] }}" {{ $selectedCycleId == $cycle['id'] ? 'selected' : '' }}>
                                    {{ $cycle['label'] }} ({{ $cycle['status'] }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- 2. Emergency Proctor Override Toggle --}}
                    <button type="button"
                            id="btnEmergencyProctor"
                            class="btn btn-sm px-3 shadow-sm d-flex align-items-center gap-2 fw-semibold {{ $controls['proctoringPaused'] ? 'btn-danger' : 'btn-outline-danger' }}"
                            onclick="toggleEmergencyProctorOverride()">
                        <i class="bi {{ $controls['proctoringPaused'] ? 'bi-pause-circle-fill' : 'bi-shield-slash' }}"></i>
                        <span id="emergencyProctorText">
                            {{ $controls['proctoringPaused'] ? 'Proctor Override: ACTIVE' : 'Emergency Proctor Override' }}
                        </span>
                    </button>

                    {{-- Export Actions --}}
                    <div class="btn-group btn-group-sm shadow-sm" role="group">
                        <a href="{{ route($role . '.analytics.export.pdf', ['cycle_id' => $selectedCycleId]) }}" target="_blank" class="btn btn-outline-danger d-flex align-items-center gap-1">
                            <i class="bi bi-file-earmark-pdf-fill"></i>
                            <span class="d-none d-md-inline">PDF</span>
                        </a>
                        <a href="{{ route($role . '.analytics.export.excel') }}" class="btn btn-success d-flex align-items-center gap-1">
                            <i class="bi bi-file-earmark-excel-fill"></i>
                            <span class="d-none d-md-inline">Excel</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════ --}}
    {{-- 1. HIGH-PRIORITY ACTION CENTER (TO-DO & PENDING TASKS) --}}
    {{-- ══════════════════════════════════════════════════════════════ --}}
    <div class="card border-0 shadow-sm rounded-4 mb-3 border-top border-4 border-warning bg-white">
        <div class="card-header bg-transparent border-0 pt-3 pb-2 px-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h2 class="h6 fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                    <span class="badge bg-warning text-dark px-2 py-1"><i class="bi bi-lightning-charge-fill me-1"></i>ACTION CENTER</span>
                    <span>High-Priority Pending Tasks &amp; Operational Queues</span>
                </h2>
                <small class="text-muted">Real-time operational alerts requiring immediate guidance staff attention.</small>
            </div>
            <span class="badge bg-light text-dark border px-2 py-1 small" id="liveSyncBadge">
                <i class="bi bi-arrow-repeat spin me-1"></i>Live Synchronized
            </span>
        </div>
        <div class="card-body p-3 pt-0">
            <div class="row g-2 g-md-3">
                {{-- Action 1: Pending Receipt Verification --}}
                <div class="col-12 col-md-4">
                    <div class="p-3 rounded-4 bg-primary bg-opacity-10 border border-primary border-opacity-25 h-100 d-flex flex-column justify-content-between">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <span class="badge bg-primary text-white mb-1">Receipt Verification</span>
                                <h3 class="h6 fw-bold text-primary mb-0">Pending Payment Slips</h3>
                                <p class="text-muted small mb-0 mt-1">Uploaded bank/treasury slips awaiting review &amp; verification.</p>
                            </div>
                            <span class="h3 fw-bold text-primary mb-0" id="badgePendingReceipts">{{ number_format($actionCenter['pendingReceipts']) }}</span>
                        </div>
                        <div class="mt-2 pt-2 border-top border-primary border-opacity-25 d-flex justify-content-between align-items-center">
                            <span class="small text-muted">Action Required</span>
                            <a href="{{ route($role . '.psychological.index', ['filter' => 'proof_review']) }}" class="btn btn-sm btn-primary px-3 rounded-pill fw-semibold">
                                <i class="bi bi-receipt me-1"></i>Open Queue
                            </a>
                        </div>
                    </div>
                </div>

                {{-- Action 2: Unassigned / Pending Test Batches --}}
                <div class="col-12 col-md-4">
                    <div class="p-3 rounded-4 bg-info bg-opacity-10 border border-info border-opacity-25 h-100 d-flex flex-column justify-content-between">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <span class="badge bg-info text-dark mb-1">Testing Sessions</span>
                                <h3 class="h6 fw-bold text-info mb-0">Unassigned Batches</h3>
                                <p class="text-muted small mb-0 mt-1">Created class/group batches waiting to be launched or assigned.</p>
                            </div>
                            <span class="h3 fw-bold text-info mb-0" id="badgePendingBatches">{{ number_format($actionCenter['pendingBatches']) }}</span>
                        </div>
                        <div class="mt-2 pt-2 border-top border-info border-opacity-25 d-flex justify-content-between align-items-center">
                            <span class="small text-muted">Ready for Testing</span>
                            <a href="{{ route($role . '.guidance-batches.index') }}" class="btn btn-sm btn-info text-dark px-3 rounded-pill fw-semibold">
                                <i class="bi bi-qr-code-scan me-1"></i>Launch Batches
                            </a>
                        </div>
                    </div>
                </div>

                {{-- Action 3: Unprocessed Document Requests --}}
                <div class="col-12 col-md-4">
                    <div class="p-3 rounded-4 bg-warning bg-opacity-10 border border-warning border-opacity-25 h-100 d-flex flex-column justify-content-between">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <span class="badge bg-warning text-dark mb-1">Clearances &amp; Forms</span>
                                <h3 class="h6 fw-bold text-warning mb-0">Unprocessed Requests</h3>
                                <p class="text-muted small mb-0 mt-1">Pending Good Moral Certificates &amp; Exit Clearances for release.</p>
                            </div>
                            <span class="h3 fw-bold text-warning mb-0" id="badgeUnprocessedDocs">{{ number_format($actionCenter['unprocessedDocs']) }}</span>
                        </div>
                        <div class="mt-2 pt-2 border-top border-warning border-opacity-25 d-flex justify-content-between align-items-center">
                            <span class="small text-muted">Ready for Pickup</span>
                            <a href="{{ route($role . '.good-moral') }}" class="btn btn-sm btn-warning text-dark px-3 rounded-pill fw-semibold">
                                <i class="bi bi-file-earmark-check me-1"></i>Process Docs
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Executive Summary KPI Stat Row --}}
    <div class="row g-2 g-md-3 mb-3">
        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white border-start border-4 border-primary">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase tracking-wider">Total Volume</div>
                        <div class="stat-value h4 fw-bold text-dark mb-0 mt-1">{{ number_format($totalRequests) }}</div>
                        <div class="text-muted small mt-1">All Core Modules</div>
                    </div>
                    <div class="stat-icon p-2 rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                        <i class="bi bi-folder-fill fs-3"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white border-start border-4 border-info">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase tracking-wider">Admission Applicants</div>
                        <div class="stat-value h4 fw-bold text-info mb-0 mt-1">{{ number_format($totalAdmissionApplicants) }}</div>
                        <div class="text-muted small mt-1">{{ number_format($qualifiedAdmissionCount) }} Qualified</div>
                    </div>
                    <div class="stat-icon p-2 rounded-circle bg-info bg-opacity-10 text-info d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                        <i class="bi bi-mortarboard-fill fs-3"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white border-start border-4 border-success">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase tracking-wider">Testing Requests</div>
                        <div class="stat-value h4 fw-bold text-success mb-0 mt-1">{{ number_format($testingServicesTotal) }}</div>
                        <div class="text-muted small mt-1">{{ number_format($completedTests) }} Tests Completed</div>
                    </div>
                    <div class="stat-icon p-2 rounded-circle bg-success bg-opacity-10 text-success d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                        <i class="bi bi-clipboard-data-fill fs-3"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white border-start border-4 border-warning">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase tracking-wider">Clearances &amp; Docs</div>
                        <div class="stat-value h4 fw-bold text-warning mb-0 mt-1">{{ number_format($goodMoralTotal + $exitFormTotal) }}</div>
                        <div class="text-muted small mt-1">{{ number_format($goodMoralClaimed + $exitFormClaimed) }} Released</div>
                    </div>
                    <div class="stat-icon p-2 rounded-circle bg-warning bg-opacity-10 text-warning d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                        <i class="bi bi-file-earmark-check-fill fs-3"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════ --}}
    {{-- 2. HIGH-RISK / RED-FLAG INTERVENTION PANEL --}}
    {{-- ══════════════════════════════════════════════════════════════ --}}
    <div class="card border-0 shadow-sm rounded-4 mb-3 bg-white border-start border-4 border-danger">
        <div class="card-header bg-white border-bottom border-light pt-3 px-3 pb-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h2 class="h6 fw-bold text-danger mb-0 d-flex align-items-center gap-2">
                    <span class="badge bg-danger px-2 py-1"><i class="bi bi-shield-exclamation me-1"></i>RED-FLAG</span>
                    <span>High-Risk Psychometric Intervention Panel</span>
                </h2>
                <small class="text-muted">Real-time alerts for students scoring in 'Severe' or 'Extremely Severe' ranges across DASS-21, PHQ-9, or GAD-7.</small>
            </div>
            <span class="badge bg-danger text-white px-3 py-2 rounded-pill fw-semibold" id="redFlagCounterBadge">
                {{ count($interventionFlags) }} Priority Cases
            </span>
        </div>
        <div class="card-body p-2 p-md-3">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="interventionTable">
                    <thead class="table-light small">
                        <tr>
                            <th class="py-2">Student Name</th>
                            <th class="py-2">Student ID</th>
                            <th class="py-2">Program</th>
                            <th class="py-2">Flagged Scale</th>
                            <th class="py-2">Severity</th>
                            <th class="py-2">Intervention Status</th>
                            <th class="py-2">Date Assessed</th>
                            <th class="py-2 text-end">Clinical Action Controls</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        @forelse($interventionFlags as $flag)
                        <tr id="intervention-row-{{ $flag['appointment_id'] ?? $flag['response_id'] }}">
                            <td class="fw-bold text-dark">{{ $flag['name'] }}</td>
                            <td><span class="badge bg-light text-secondary border font-monospace">{{ $flag['student_id'] }}</span></td>
                            <td><span class="badge bg-primary bg-opacity-10 text-primary">{{ $flag['course'] }}</span></td>
                            <td><strong>{{ $flag['scale'] }}</strong></td>
                            <td>
                                <span class="badge {{ $flag['severity'] === 'Extremely Severe' ? 'bg-danger text-white' : 'bg-warning text-dark' }} px-2 py-1">
                                    {{ $flag['severity'] }}
                                </span>
                            </td>
                            <td>
                                <span class="badge rounded-pill intervention-status-badge {{ $flag['counseling_status'] === 'Counseling Scheduled' ? 'bg-info text-dark' : ($flag['counseling_status'] === 'In Progress' ? 'bg-primary text-white' : ($flag['counseling_status'] === 'Completed / Resolved' ? 'bg-success text-white' : 'bg-secondary text-white')) }}">
                                    {{ $flag['counseling_status'] }}
                                </span>
                            </td>
                            <td class="text-muted small">{{ $flag['date'] }}</td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    {{-- Action A: View Confidential Profile Modal --}}
                                    <button type="button" class="btn btn-outline-dark btn-sm" onclick="openConfidentialProfileModal({{ $flag['response_id'] }})" title="View Confidential Profile">
                                        <i class="bi bi-file-earmark-person-fill text-danger me-1"></i>View Profile
                                    </button>
                                    {{-- Action B: Flag for Follow-Up / Schedule Session --}}
                                    <button type="button" class="btn btn-outline-info btn-sm" onclick="quickUpdateIntervention({{ $flag['appointment_id'] }}, 'Counseling Scheduled')" title="Schedule Session">
                                        <i class="bi bi-calendar-event me-1"></i>Schedule
                                    </button>
                                    {{-- Action C: Mark as Contacted / In Progress --}}
                                    <button type="button" class="btn btn-outline-success btn-sm" onclick="quickUpdateIntervention({{ $flag['appointment_id'] }}, 'In Progress')" title="Mark as Contacted">
                                        <i class="bi bi-telephone-inbound-fill me-1"></i>Contacted
                                    </button>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">
                                <i class="bi bi-check-circle-fill text-success fs-3 d-block mb-2"></i>
                                No high-risk mental health red flags recorded. All student screening scores fall within normal or mild thresholds.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════ --}}
    {{-- 3 & 4. MIDDLE SECTION: 2-COLUMN GRID (DESKTOP) / 1-COL (MOBILE) --}}
    {{-- Psychometric Severity & Socio-Demographics / Program Heatmap --}}
    {{-- ══════════════════════════════════════════════════════════════ --}}
    <div class="row g-3 mb-3">
        {{-- COLUMN 1: Psychometric Severity Level & Dimension Breakdowns --}}
        <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
                <div class="card-header bg-white border-bottom border-light pt-3 px-3 pb-3 d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="h6 fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                            <i class="bi bi-pie-chart-fill text-primary"></i>
                            <span>Psychometric Severity Distribution</span>
                        </h2>
                        <small class="text-muted">Aggregated across DASS-21, PHQ-9, and GAD-7 submissions</small>
                    </div>
                    <span class="badge bg-light text-secondary border">Clinical Screening</span>
                </div>
                <div class="card-body p-3">
                    <div style="position: relative; height: 230px; width: 100%;">
                        <canvas id="severityChart"></canvas>
                    </div>

                    <div class="mt-3 pt-3 border-top">
                        <h3 class="h6 fw-bold text-dark mb-2 small text-uppercase">
                            <i class="bi bi-card-checklist text-primary me-1"></i>Sub-Scale Severity Indicators
                        </h3>
                        <div class="row g-2 small">
                            <div class="col-6 col-sm-4">
                                <div class="p-2 border rounded bg-light">
                                    <div class="text-muted fw-bold">DASS Depression</div>
                                    <div class="text-danger fw-bold fs-6">
                                        {{ ($scaleDistributions['dass21_depression']['Severe'] ?? 0) + ($scaleDistributions['dass21_depression']['Extremely Severe'] ?? 0) }}
                                        <span class="text-muted fw-normal fs-xs">Severe</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 col-sm-4">
                                <div class="p-2 border rounded bg-light">
                                    <div class="text-muted fw-bold">DASS Anxiety</div>
                                    <div class="text-warning fw-bold fs-6">
                                        {{ ($scaleDistributions['dass21_anxiety']['Severe'] ?? 0) + ($scaleDistributions['dass21_anxiety']['Extremely Severe'] ?? 0) }}
                                        <span class="text-muted fw-normal fs-xs">Severe</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 col-sm-4">
                                <div class="p-2 border rounded bg-light">
                                    <div class="text-muted fw-bold">DASS Stress</div>
                                    <div class="text-secondary fw-bold fs-6">
                                        {{ ($scaleDistributions['dass21_stress']['Severe'] ?? 0) + ($scaleDistributions['dass21_stress']['Extremely Severe'] ?? 0) }}
                                        <span class="text-muted fw-normal fs-xs">Severe</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-6 col-sm-6">
                                <div class="p-2 border rounded bg-light">
                                    <div class="text-muted fw-bold">PHQ-9 Mood (Severe/Mod. Sev)</div>
                                    <div class="text-danger fw-bold fs-6">
                                        {{ ($scaleDistributions['phq9_mood']['Severe'] ?? 0) + ($scaleDistributions['phq9_mood']['Moderately Severe'] ?? 0) }}
                                        <span class="text-muted fw-normal fs-xs">Examinees</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-sm-6">
                                <div class="p-2 border rounded bg-light">
                                    <div class="text-muted fw-bold">GAD-7 Anxiety (Severe)</div>
                                    <div class="text-danger fw-bold fs-6">
                                        {{ $scaleDistributions['gad7_anxiety']['Severe'] ?? 0 }}
                                        <span class="text-muted fw-normal fs-xs">Examinees</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- COLUMN 2: Socio-Demographics & Special Categories + Admission Comparison --}}
        <div class="col-12 col-lg-6">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
                <div class="card-header bg-white border-bottom border-light pt-3 px-3 pb-3 d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="h6 fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                            <i class="bi bi-people-fill text-warning"></i>
                            <span>Socio-Demographic &amp; Institutional Profile</span>
                        </h2>
                        <small class="text-muted">Accreditation metrics &amp; Special Group distribution</small>
                    </div>
                    <span class="badge bg-light text-secondary border">Institutional Target</span>
                </div>
                <div class="card-body p-3">
                    <div class="row align-items-center mb-3">
                        <div class="col-5">
                            <div style="position: relative; height: 140px;">
                                <canvas id="genderChart"></canvas>
                            </div>
                        </div>
                        <div class="col-7">
                            <div class="d-flex flex-column gap-2">
                                <div class="d-flex justify-content-between align-items-center p-2 rounded bg-light">
                                    <span class="small fw-semibold"><i class="bi bi-gender-male text-primary me-1"></i> Male Examinees</span>
                                    <span class="fw-bold text-dark">{{ number_format($demographics['maleCount']) }}</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center p-2 rounded bg-light">
                                    <span class="small fw-semibold"><i class="bi bi-gender-female text-danger me-1"></i> Female Examinees</span>
                                    <span class="fw-bold text-dark">{{ number_format($demographics['femaleCount']) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Special Categories --}}
                    <div class="p-2 border rounded-3 bg-light mb-3">
                        <span class="small text-muted fw-bold text-uppercase d-block mb-1">Special Categories &amp; Equity Target Groups:</span>
                        <div class="d-flex flex-wrap gap-1">
                            @foreach($demographics['specialCategories'] as $group => $count)
                                <span class="badge bg-white text-dark border px-2 py-1 small">
                                    {{ $group }}: <strong class="text-primary">{{ number_format($count) }}</strong>
                                </span>
                            @endforeach
                        </div>
                    </div>

                    {{-- Admission Qualified vs Total --}}
                    <div class="pt-2 border-top">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h3 class="h6 fw-bold text-dark mb-0 small text-uppercase">PSU-CAT Admission Qualification</h3>
                            <span class="badge bg-success bg-opacity-10 text-success">{{ number_format($qualifiedAdmissionCount) }} Qualified</span>
                        </div>
                        <div style="position: relative; height: 130px; width: 100%;">
                            <canvas id="admissionStatusChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Program Participation Heatmap across 11 registered campus degree programs --}}
    <div class="card border-0 shadow-sm rounded-4 mb-3 bg-white">
        <div class="card-header bg-white border-bottom border-light pt-3 px-3 pb-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h2 class="h6 fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                    <i class="bi bi-bar-chart-steps text-info"></i>
                    <span>Program Participation Heatmap &amp; Volume Across All 11 Campus Degrees</span>
                </h2>
                <small class="text-muted">Total testing and clearance requests distributed per registered degree program in PSU San Carlos</small>
            </div>
            <span class="badge bg-info-subtle text-info border border-info-subtle fw-bold">11 Campus Degree Programs</span>
        </div>
        <div class="card-body p-3">
            <div style="position: relative; height: 260px; width: 100%;">
                <canvas id="programChart"></canvas>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════ --}}
    {{-- 5. BOTTOM SECTION: REAL-TIME SECURITY, REVENUE & DOC FULFILLMENT --}}
    {{-- ══════════════════════════════════════════════════════════════ --}}
    <div class="row g-3 mb-3">
        {{-- REAL-TIME SECURITY & PROCTORING INCIDENT FEED --}}
        <div class="col-12 col-lg-7">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-white border-start border-4 border-danger">
                <div class="card-header bg-white border-bottom border-light pt-3 px-3 pb-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div>
                        <h2 class="h6 fw-bold text-danger mb-0 d-flex align-items-center gap-2">
                            <i class="bi bi-shield-lock-fill"></i>
                            <span>Real-Time Security &amp; Proctoring Incident Feed</span>
                        </h2>
                        <small class="text-muted">App-switches, screenshots, back-key events &amp; active strike monitoring</small>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-danger text-white px-2 py-1 fw-bold" id="totalIncidentsBadge">
                            {{ number_format($proctoring['totalIncidents']) }} Total Infractions
                        </span>
                    </div>
                </div>
                <div class="card-body p-3">
                    {{-- Active Session Lockout Feed (>= 3 strikes) --}}
                    <div class="mb-3">
                        <h3 class="h6 fw-bold text-dark mb-2 small text-uppercase d-flex align-items-center justify-content-between">
                            <span><i class="bi bi-exclamation-triangle-fill text-danger me-1"></i>Active Session Lockout Feed (Max Strikes 3/3)</span>
                            <span class="badge bg-danger-subtle text-danger">{{ count($proctoring['lockedAppts']) }} Flagged/Locked</span>
                        </h3>
                        <div class="table-responsive" style="max-height: 200px; overflow-y: auto;">
                            <table class="table table-sm table-bordered align-middle mb-0">
                                <thead class="table-light small">
                                    <tr>
                                        <th>Student / Examinee</th>
                                        <th>Strikes</th>
                                        <th>Status</th>
                                        <th class="text-end">Remote Staff Controls</th>
                                    </tr>
                                </thead>
                                <tbody class="small">
                                    @forelse($proctoring['lockedAppts'] as $locked)
                                    <tr id="locked-row-{{ $locked['appointment_id'] }}">
                                        <td>
                                            <div class="fw-bold">{{ $locked['student_name'] }}</div>
                                            <div class="text-muted fs-xs">{{ $locked['student_id'] }} &bull; Started {{ $locked['started_at'] }}</div>
                                        </td>
                                        <td>
                                            <span class="badge bg-danger text-white">{{ $locked['strike_count'] }}/{{ $locked['threshold'] }} Strikes</span>
                                        </td>
                                        <td>
                                            @if($locked['terminated'])
                                                <span class="badge bg-dark text-white">Terminated</span>
                                            @else
                                                <span class="badge bg-danger text-white">Locked Out</span>
                                            @endif
                                        </td>
                                        <td class="text-end">
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-warning btn-sm" onclick="sendProctorAction({{ $locked['appointment_id'] }}, 'warn')" title="Send Warning">
                                                    <i class="bi bi-exclamation-triangle-fill me-1"></i>Warn
                                                </button>
                                                <button type="button" class="btn btn-outline-info btn-sm" onclick="sendProctorAction({{ $locked['appointment_id'] }}, 'pause')" title="Pause / Extend Time">
                                                    <i class="bi bi-pause-fill me-1"></i>Pause
                                                </button>
                                                <button type="button" class="btn btn-danger btn-sm" onclick="sendProctorAction({{ $locked['appointment_id'] }}, 'force_terminate')" title="Force Terminate">
                                                    <i class="bi bi-x-circle-fill me-1"></i>Terminate
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-3 text-muted">
                                            <i class="bi bi-shield-check text-success me-1"></i>No active lockouts or max-strike infractions currently active.
                                        </td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {{-- Recent Incidents Feed Stream --}}
                    <div>
                        <h3 class="h6 fw-bold text-dark mb-2 small text-uppercase">Live Infraction Stream</h3>
                        <div class="table-responsive" style="max-height: 180px; overflow-y: auto;">
                            <table class="table table-sm table-striped align-middle mb-0">
                                <thead class="table-light small">
                                    <tr>
                                        <th>Student</th>
                                        <th>Infraction Type</th>
                                        <th>Strike #</th>
                                        <th class="text-end">Logged</th>
                                    </tr>
                                </thead>
                                <tbody class="small" id="recentIncidentsBody">
                                    @forelse($proctoring['recentIncidents'] as $inc)
                                    <tr>
                                        <td class="fw-semibold">{{ $inc['student_name'] }}</td>
                                        <td><span class="badge bg-secondary text-white">{{ $inc['type'] }}</span></td>
                                        <td><span class="badge bg-danger text-white">{{ $inc['strike'] }}</span></td>
                                        <td class="text-end text-muted">{{ $inc['time'] }}</td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-2 text-muted">No recent incident logs recorded.</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- DOCUMENT FULFILLMENT & REVENUE COLLECTION ANALYTICS --}}
        <div class="col-12 col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-white border-start border-4 border-success">
                <div class="card-header bg-white border-bottom border-light pt-3 px-3 pb-3 d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="h6 fw-bold text-success mb-0 d-flex align-items-center gap-2">
                            <i class="bi bi-cash-stack"></i>
                            <span>Document Fulfillment &amp; Fee Analytics</span>
                        </h2>
                        <small class="text-muted">Issued vs claimed documents &amp; revenue mapped against O.R. numbers</small>
                    </div>
                    <span class="badge bg-success text-white px-2 py-1 fw-bold">
                        ₱{{ number_format($documentFees['totalRevenue'], 2) }}
                    </span>
                </div>
                <div class="card-body p-3">
                    {{-- Fulfillment Summary --}}
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <div class="p-2 border rounded bg-light text-center">
                                <span class="d-block small text-muted">Good Moral (Claimed/Total)</span>
                                <span class="h6 fw-bold text-dark mb-0">{{ $documentFees['gmClaimed'] }} / {{ $documentFees['gmTotal'] }}</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2 border rounded bg-light text-center">
                                <span class="d-block small text-muted">Exit Forms (Completed/Total)</span>
                                <span class="h6 fw-bold text-dark mb-0">{{ $documentFees['efClaimed'] }} / {{ $documentFees['efTotal'] }}</span>
                            </div>
                        </div>
                    </div>

                    {{-- Revenue Breakdown Chart --}}
                    <div style="position: relative; height: 180px; width: 100%;">
                        <canvas id="revenueChart"></canvas>
                    </div>

                    {{-- Collection Details Table --}}
                    <div class="mt-3 pt-2 border-top">
                        <h3 class="h6 fw-bold text-dark mb-2 small text-uppercase">Collection Summary by Service</h3>
                        <table class="table table-sm table-bordered align-middle mb-0 small">
                            <thead class="table-light">
                                <tr>
                                    <th>Service Description</th>
                                    <th class="text-end">Rate</th>
                                    <th class="text-end">Collections (₱)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($documentFees['revenueBreakdown'] as $serviceName => $revenue)
                                <tr>
                                    <td>{{ $serviceName }}</td>
                                    <td class="text-end text-muted">₱60.00</td>
                                    <td class="text-end fw-bold text-success">₱{{ number_format($revenue, 2) }}</td>
                                </tr>
                                @endforeach
                                <tr class="table-light fw-bold">
                                    <td colspan="2">Total Verified Collections</td>
                                    <td class="text-end text-success">₱{{ number_format($documentFees['totalRevenue'], 2) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════════ --}}
{{-- SECURE MODAL: CONFIDENTIAL STUDENT PSYCHOMETRIC PROFILE --}}
{{-- ══════════════════════════════════════════════════════════════ --}}
<div class="modal fade" id="confidentialProfileModal" tabindex="-1" aria-labelledby="confidentialModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header bg-danger text-white rounded-top-4 py-3">
                <h5 class="modal-title h6 fw-bold d-flex align-items-center gap-2" id="confidentialModalLabel">
                    <i class="bi bi-shield-lock-fill"></i>
                    <span>CONFIDENTIAL PSYCHOMETRIC ASSESSMENT PROFILE</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-3 p-md-4" id="confidentialProfileContent">
                <div class="text-center py-5" id="confidentialLoading">
                    <div class="spinner-border text-danger" role="status"></div>
                    <p class="text-muted mt-2">Loading confidential profile and JSON response details...</p>
                </div>
                <div id="confidentialData" style="display: none;">
                    {{-- Student Header --}}
                    <div class="p-3 bg-light rounded-3 border mb-3">
                        <div class="row g-2">
                            <div class="col-md-7">
                                <h4 class="h6 fw-bold text-dark mb-1" id="cpStudentName">STUDENT NAME</h4>
                                <div class="small text-muted">
                                    ID: <span class="font-monospace fw-semibold" id="cpStudentID">-</span> &bull;
                                    Program: <span class="fw-semibold text-primary" id="cpStudentCourse">-</span>
                                </div>
                            </div>
                            <div class="col-md-5 text-md-end">
                                <div class="small text-muted">Date Assessed: <strong id="cpDateTaken">-</strong></div>
                                <div class="small text-muted">Intervention: <span class="badge bg-warning text-dark" id="cpCounselingStatus">-</span></div>
                            </div>
                        </div>
                    </div>

                    {{-- Score Summaries Cards --}}
                    <h6 class="fw-bold text-dark small text-uppercase mb-2"><i class="bi bi-clipboard-data-fill text-primary me-1"></i>Score Summary &amp; Clinical Interpretations</h6>
                    <div class="row g-2 mb-3" id="cpScoreSummaries">
                        {{-- Injected dynamically --}}
                    </div>

                    {{-- Item-by-Item JSON Breakdown Table --}}
                    <h6 class="fw-bold text-dark small text-uppercase mb-2"><i class="bi bi-list-check text-success me-1"></i>Item-by-Item Response Breakdown</h6>
                    <div class="table-responsive" style="max-height: 250px; overflow-y: auto;">
                        <table class="table table-sm table-bordered align-middle mb-0 small">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 10%;">Item #</th>
                                    <th>Selected Answer / Recorded Value</th>
                                </tr>
                            </thead>
                            <tbody id="cpAnswersTableBody">
                                {{-- Injected dynamically --}}
                            </tbody>
                        </table>
                    </div>

                    {{-- Counselor Notes Section --}}
                    <div class="mt-3 pt-3 border-top">
                        <label for="cpCounselorNotesInput" class="form-label small fw-bold text-dark">Counselor Clinical Notes / Plan:</label>
                        <textarea class="form-control form-control-sm" id="cpCounselorNotesInput" rows="3" placeholder="Enter confidential case notes, scheduled appointment times, or outreach actions..."></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light rounded-bottom-4 py-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary btn-sm" id="btnSaveCounselorNotes" onclick="saveCounselorNotesFromModal()">
                    <i class="bi bi-check-circle me-1"></i>Save Clinical Notes
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
let currentAppointmentId = null;
let currentResponseId = null;

document.addEventListener('DOMContentLoaded', function() {
    // ── 1. Psychometric Severity Donut Chart ──
    const severityCtx = document.getElementById('severityChart');
    if (severityCtx) {
        new Chart(severityCtx, {
            type: 'doughnut',
            data: {
                labels: {!! json_encode(array_keys($severityDistribution)) !!},
                datasets: [{
                    data: {!! json_encode(array_values($severityDistribution)) !!},
                    backgroundColor: ['#198754', '#0dcaf0', '#ffc107', '#fd7e14', '#dc3545'],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 10 } } }
                },
                cutout: '65%'
            }
        });
    }

    // ── 2. Gender Socio-Demographics Donut Chart ──
    const genderCtx = document.getElementById('genderChart');
    if (genderCtx) {
        new Chart(genderCtx, {
            type: 'doughnut',
            data: {
                labels: ['Male', 'Female'],
                datasets: [{
                    data: [{{ $demographics['maleCount'] }}, {{ $demographics['femaleCount'] }}],
                    backgroundColor: ['#0f3f97', '#d63384'],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                cutout: '70%'
            }
        });
    }

    // ── 3. Admission Qualification Status Donut ──
    const admStatusCtx = document.getElementById('admissionStatusChart');
    if (admStatusCtx) {
        new Chart(admStatusCtx, {
            type: 'doughnut',
            data: {
                labels: {!! json_encode(array_keys($admissionStatusBreakdown)) !!},
                datasets: [{
                    data: {!! json_encode(array_values($admissionStatusBreakdown)) !!},
                    backgroundColor: ['#198754', '#dc3545', '#ffc107'],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'right', labels: { boxWidth: 10, font: { size: 10 } } } },
                cutout: '65%'
            }
        });
    }

    // ── 4. Program Participation Heatmap (All 11 Campus Programs) ──
    const programCtx = document.getElementById('programChart');
    if (programCtx) {
        new Chart(programCtx, {
            type: 'bar',
            data: {
                labels: {!! json_encode(array_keys($demographics['programParticipation'])) !!},
                datasets: [{
                    label: 'Testing & Service Volume',
                    data: {!! json_encode(array_values($demographics['programParticipation'])) !!},
                    backgroundColor: 'rgba(15, 63, 151, 0.85)',
                    borderColor: '#0f3f97',
                    borderWidth: 1,
                    borderRadius: 4
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: { beginAtZero: true, ticks: { precision: 0, font: { size: 10 } }, grid: { color: 'rgba(0,0,0,0.05)' } },
                    y: { ticks: { font: { size: 10, weight: '600' } }, grid: { display: false } }
                },
                plugins: { legend: { display: false } }
            }
        });
    }

    // ── 5. Revenue Breakdown Donut Chart ──
    const revCtx = document.getElementById('revenueChart');
    if (revCtx) {
        new Chart(revCtx, {
            type: 'doughnut',
            data: {
                labels: {!! json_encode(array_keys($documentFees['revenueBreakdown'])) !!},
                datasets: [{
                    data: {!! json_encode(array_values($documentFees['revenueBreakdown'])) !!},
                    backgroundColor: ['#ffc107', '#0f3f97', '#0dcaf0', '#198754', '#6c757d'],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'right', labels: { boxWidth: 8, font: { size: 9 } } } },
                cutout: '60%'
            }
        });
    }

    // Start live background sync
    setInterval(syncLiveStats, 10000);
});

// ── Switch Admission Cycle ──
function switchAdmissionCycle(cycleId) {
    const url = new URL(window.location.href);
    url.searchParams.set('cycle_id', cycleId);
    window.location.href = url.toString();
}

// ── Toggle Emergency Proctor Override ──
function toggleEmergencyProctorOverride() {
    if (!confirm('Are you sure you want to toggle the campus-wide Emergency Proctor Override? This will pause/resume all active online test sessions.')) {
        return;
    }

    const btn = document.getElementById('btnEmergencyProctor');
    const text = document.getElementById('emergencyProctorText');

    fetch("{{ route($role . '.analytics.toggle-proctor-override') }}", {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json'
        }
    })
    .then(res => res.json())
    .then(data => {
        if (data.proctoringPaused) {
            btn.className = 'btn btn-sm px-3 shadow-sm d-flex align-items-center gap-2 fw-semibold btn-danger';
            text.innerText = 'Proctor Override: ACTIVE';
            alert(data.message);
        } else {
            btn.className = 'btn btn-sm px-3 shadow-sm d-flex align-items-center gap-2 fw-semibold btn-outline-danger';
            text.innerText = 'Emergency Proctor Override';
            alert(data.message);
        }
    })
    .catch(err => {
        console.error(err);
        alert('Failed to toggle emergency proctor override.');
    });
}

// ── Quick Update Intervention Status ──
function quickUpdateIntervention(appointmentId, status) {
    if (!appointmentId) return;

    fetch(`{{ url($role . '/analytics/intervention') }}/${appointmentId}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json'
        },
        body: JSON.stringify({ counseling_status: status })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            const row = document.getElementById(`intervention-row-${appointmentId}`);
            if (row) {
                const badge = row.querySelector('.intervention-status-badge');
                if (badge) {
                    badge.innerText = status;
                    badge.className = 'badge rounded-pill intervention-status-badge ' +
                        (status === 'Counseling Scheduled' ? 'bg-info text-dark' :
                        (status === 'In Progress' ? 'bg-primary text-white' :
                        (status === 'Completed / Resolved' ? 'bg-success text-white' : 'bg-secondary text-white')));
                }
            }
            alert(data.message);
        }
    })
    .catch(err => {
        console.error(err);
        alert('Failed to update intervention status.');
    });
}

// ── Send Proctor Remote Action (Warn, Pause, Force Terminate) ──
function sendProctorAction(appointmentId, action) {
    const actionLabel = action === 'force_terminate' ? 'FORCE TERMINATE' : action.toUpperCase();
    if (!confirm(`Are you sure you want to ${actionLabel} this examination session?`)) {
        return;
    }

    fetch(`{{ url($role . '/analytics/proctor-action') }}/${appointmentId}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json'
        },
        body: JSON.stringify({ action: action })
    })
    .then(res => res.json())
    .then(data => {
        alert(data.message);
        if (action === 'force_terminate') {
            const row = document.getElementById(`locked-row-${appointmentId}`);
            if (row) row.remove();
        }
    })
    .catch(err => {
        console.error(err);
        alert('Failed to execute proctor action.');
    });
}

// ── Open Confidential Profile Modal ──
function openConfidentialProfileModal(responseId) {
    currentResponseId = responseId;
    const modalEl = document.getElementById('confidentialProfileModal');
    const modal = new bootstrap.Modal(modalEl);
    modal.show();

    document.getElementById('confidentialLoading').style.display = 'block';
    document.getElementById('confidentialData').style.display = 'none';

    fetch(`{{ url($role . '/analytics/confidential-profile') }}/${responseId}`, {
        headers: { 'Accept': 'application/json' }
    })
    .then(res => res.json())
    .then(data => {
        currentAppointmentId = data.appointment_id;
        document.getElementById('cpStudentName').innerText = data.student_name;
        document.getElementById('cpStudentID').innerText = data.student_id;
        document.getElementById('cpStudentCourse').innerText = data.course;
        document.getElementById('cpDateTaken').innerText = data.date_taken;
        document.getElementById('cpCounselingStatus').innerText = data.counseling_status;
        document.getElementById('cpCounselorNotesInput').value = data.counseling_notes || '';

        // Render Score Summaries
        const summariesContainer = document.getElementById('cpScoreSummaries');
        summariesContainer.innerHTML = '';
        if (data.score_summary) {
            for (const [testKey, sum] of Object.entries(data.score_summary)) {
                const interp = sum.interpretation || {};
                let interpStr = '';
                for (const [dim, val] of Object.entries(interp)) {
                    interpStr += `<span class="badge ${val === 'Severe' || val === 'Extremely Severe' ? 'bg-danger' : 'bg-secondary'} me-1">${dim}: ${val}</span>`;
                }
                summariesContainer.innerHTML += `
                    <div class="col-md-6">
                        <div class="p-2 border rounded bg-white h-100">
                            <div class="fw-bold text-dark small text-uppercase">${testKey.toUpperCase()} Assessment</div>
                            <div class="mt-1">${interpStr || '<span class="text-muted small">No interpretation flags</span>'}</div>
                        </div>
                    </div>
                `;
            }
        }

        // Render Itemized Answers Table
        const answersTbody = document.getElementById('cpAnswersTableBody');
        answersTbody.innerHTML = '';
        if (data.answers && Object.keys(data.answers).length > 0) {
            for (const [item, ans] of Object.entries(data.answers)) {
                answersTbody.innerHTML += `
                    <tr>
                        <td class="font-monospace fw-bold">Item ${item}</td>
                        <td>${typeof ans === 'object' ? JSON.stringify(ans) : ans}</td>
                    </tr>
                `;
            }
        } else {
            answersTbody.innerHTML = `<tr><td colspan="2" class="text-center text-muted">No raw item responses available.</td></tr>`;
        }

        document.getElementById('confidentialLoading').style.display = 'none';
        document.getElementById('confidentialData').style.display = 'block';
    })
    .catch(err => {
        console.error(err);
        document.getElementById('confidentialLoading').innerHTML = `<p class="text-danger">Failed to load confidential profile.</p>`;
    });
}

// ── Save Counselor Notes from Modal ──
function saveCounselorNotesFromModal() {
    if (!currentAppointmentId) {
        alert('No active appointment linked.');
        return;
    }

    const notes = document.getElementById('cpCounselorNotesInput').value;

    fetch(`{{ url($role . '/analytics/intervention') }}/${currentAppointmentId}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json'
        },
        body: JSON.stringify({
            counseling_status: 'In Progress',
            counseling_notes: notes
        })
    })
    .then(res => res.json())
    .then(data => {
        alert('Counselor notes saved successfully.');
        const modal = bootstrap.Modal.getInstance(document.getElementById('confidentialProfileModal'));
        if (modal) modal.hide();
    })
    .catch(err => {
        console.error(err);
        alert('Failed to save counselor notes.');
    });
}

// ── Live Stats Sync Polling ──
function syncLiveStats() {
    fetch("{{ route($role . '.analytics.live-stats') }}", {
        headers: { 'Accept': 'application/json' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.actionCenter) {
            const pr = document.getElementById('badgePendingReceipts');
            const pb = document.getElementById('badgePendingBatches');
            const ud = document.getElementById('badgeUnprocessedDocs');
            if (pr) pr.innerText = Number(data.actionCenter.pendingReceipts).toLocaleString();
            if (pb) pb.innerText = Number(data.actionCenter.pendingBatches).toLocaleString();
            if (ud) ud.innerText = Number(data.actionCenter.unprocessedDocs).toLocaleString();
        }
        if (data.proctoring && data.proctoring.totalIncidents !== undefined) {
            const tib = document.getElementById('totalIncidentsBadge');
            if (tib) tib.innerText = Number(data.proctoring.totalIncidents).toLocaleString() + ' Total Infractions';
        }
    })
    .catch(err => console.log('Live sync paused...'));
}
</script>
@endpush
