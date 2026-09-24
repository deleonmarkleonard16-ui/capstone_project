@extends('layouts.app')

@section('title', 'Executive Analytics Dashboard')

@section('content')
<div class="executive-analytics container-fluid px-0">
    {{-- Header with Export Actions --}}
    <div class="analytics-heading d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small text-muted">
                    <li class="breadcrumb-item"><a href="{{ route($role . '.dashboard') }}" class="text-decoration-none">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Executive Analytics</li>
                </ol>
            </nav>
            <h1 class="h3 fw-bold text-dark mb-1">
                <i class="bi bi-bar-chart-line-fill text-primary me-2"></i>Executive Institutional Analytics Dashboard
            </h1>
            <p class="text-muted small mb-0">
                Comprehensive executive overview for Admission Testing, Guidance Testing Services, and Document Clearances across PSU San Carlos.
            </p>
        </div>
        <div class="analytics-actions grid grid-cols-2 gap-2 w-full">
            <a href="{{ route($role . '.analytics.export.pdf') }}" target="_blank" class="btn btn-outline-danger btn-sm px-3 shadow-sm d-flex align-items-center gap-2">
                <i class="bi bi-file-earmark-pdf-fill"></i>
                <span>Export PDF Summary</span>
            </a>
            <a href="{{ route($role . '.analytics.export.excel') }}" class="btn btn-success btn-sm px-3 shadow-sm d-flex align-items-center gap-2">
                <i class="bi bi-file-earmark-excel-fill"></i>
                <span>Export Excel</span>
            </a>
        </div>
    </div>

    {{-- Executive Summary Stat Cards --}}
    <div class="analytics-metrics grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 mb-4">
        <div class="analytics-metric">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white border-start border-4 border-primary">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase tracking-wider">Total Institutional Volume</div>
                        <div class="stat-value text-xl fw-bold text-dark mb-0 mt-1">{{ number_format($totalRequests) }}</div>
                        <div class="text-muted small mt-1">All 3 Core Modules</div>
                    </div>
                    <div class="stat-icon w-10 h-10 rounded-circle bg-primary bg-opacity-10 text-primary">
                        <i class="bi bi-folder2-open fs-3"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="analytics-metric">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white border-start border-4 border-info">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase tracking-wider">Admission Applicants</div>
                        <div class="stat-value text-xl fw-bold text-info mb-0 mt-1">{{ number_format($totalAdmissionApplicants) }}</div>
                        <div class="text-muted small mt-1">{{ number_format($qualifiedAdmissionCount) }} Qualified / Passed</div>
                    </div>
                    <div class="stat-icon w-10 h-10 rounded-circle bg-info bg-opacity-10 text-info">
                        <i class="bi bi-mortarboard-fill fs-3"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="analytics-metric">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white border-start border-success">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase tracking-wider">Testing Requests</div>
                        <div class="stat-value text-xl fw-bold text-success mb-0 mt-1">{{ number_format($testingServicesTotal) }}</div>
                        <div class="text-muted small mt-1">{{ number_format($completedTests) }} Tests Completed</div>
                    </div>
                    <div class="stat-icon w-10 h-10 rounded-circle bg-success bg-opacity-10 text-success">
                        <i class="bi bi-brain fs-3"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="analytics-metric">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white border-start border-4 border-warning">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase tracking-wider">Document Clearances</div>
                        <div class="stat-value text-xl fw-bold text-warning mb-0 mt-1">{{ number_format($goodMoralTotal + $exitFormTotal) }}</div>
                        <div class="text-muted small mt-1">{{ number_format($goodMoralClaimed + $exitFormClaimed) }} Claimed &amp; Released</div>
                    </div>
                    <div class="stat-icon w-10 h-10 rounded-circle bg-warning bg-opacity-10 text-warning">
                        <i class="bi bi-file-earmark-check-fill fs-3"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════ --}}
    {{-- SECTION 1: ADMISSION MODULE ANALYTICS --}}
    {{-- ══════════════════════════════════════════════════════════════ --}}
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-white border-bottom border-light pt-3 px-3 pb-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h2 class="h5 fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                    <span class="badge bg-primary px-2 py-1 fs-6">1</span>
                    <span>Admission Module Analytics (PSU-CAT)</span>
                </h2>
                <small class="text-muted">Applicant volume, qualification status, and program application breakdown.</small>
            </div>
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 rounded-pill fw-semibold">
                {{ number_format($totalAdmissionApplicants) }} Total Applicants
            </span>
        </div>
        <div class="card-body p-3">
            {{-- Admission KPI mini row --}}
            <div class="row g-2 mb-3">
                <div class="col-6 col-md-3">
                    <div class="p-2 rounded bg-light text-center">
                        <span class="d-block small text-muted">Total Applicants</span>
                        <span class="h5 fw-bold text-dark mb-0">{{ number_format($totalAdmissionApplicants) }}</span>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="p-2 rounded bg-success bg-opacity-10 text-center">
                        <span class="d-block small text-success fw-semibold">Qualified / Passed</span>
                        <span class="h5 fw-bold text-success mb-0">{{ number_format($qualifiedAdmissionCount) }}</span>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="p-2 rounded bg-danger bg-opacity-10 text-center">
                        <span class="d-block small text-danger fw-semibold">Not Qualified</span>
                        <span class="h5 fw-bold text-danger mb-0">{{ number_format($notQualifiedAdmissionCount) }}</span>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="p-2 rounded bg-warning bg-opacity-10 text-center">
                        <span class="d-block small text-warning fw-semibold">Pending / Under Evaluation</span>
                        <span class="h5 fw-bold text-warning mb-0">{{ number_format($pendingAdmissionCount) }}</span>
                    </div>
                </div>
            </div>

            {{-- Admission Charts --}}
            <div class="row g-3">
                {{-- Total vs Qualified Bar Chart --}}
                <div class="col-lg-8">
                    <div class="border rounded-3 p-3 bg-white h-100">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h3 class="h6 fw-bold text-dark mb-0">Total Applicants vs. Qualified / Passed by Program</h3>
                            <span class="badge bg-light text-muted border small">Per Campus Degree</span>
                        </div>
                        <div style="position: relative; height: 260px; width: 100%;">
                            <canvas id="admissionApplicantsChart"></canvas>
                        </div>
                    </div>
                </div>

                {{-- Status Breakdown Donut --}}
                <div class="col-lg-4">
                    <div class="border rounded-3 p-3 bg-white h-100 d-flex flex-column justify-content-between">
                        <div>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h3 class="h6 fw-bold text-dark mb-0">Application Status Distribution</h3>
                                <span class="badge bg-light text-muted border small">Overall</span>
                            </div>
                            <div style="position: relative; height: 200px; width: 100%;">
                                <canvas id="admissionStatusChart"></canvas>
                            </div>
                        </div>
                        <div class="d-flex justify-content-around text-center pt-2 border-top small text-muted">
                            <div><span class="d-block fw-bold text-success">{{ number_format($qualifiedAdmissionCount) }}</span>Qualified</div>
                            <div><span class="d-block fw-bold text-danger">{{ number_format($notQualifiedAdmissionCount) }}</span>Not Qual.</div>
                            <div><span class="d-block fw-bold text-warning">{{ number_format($pendingAdmissionCount) }}</span>Pending</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════ --}}
    {{-- SECTION 2: REQUEST TESTING SERVICES ANALYTICS --}}
    {{-- ══════════════════════════════════════════════════════════════ --}}
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-white border-bottom border-light pt-3 px-3 pb-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h2 class="h5 fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                    <span class="badge bg-primary px-2 py-1 fs-6">2</span>
                    <span>Request Testing Services Analytics</span>
                </h2>
                <small class="text-muted">Psychological assessments, personality tests, career profiling, and clinical red-flag monitoring.</small>
            </div>
            <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 rounded-pill fw-semibold">
                {{ number_format($testingServicesTotal) }} Testing Requests
            </span>
        </div>
        <div class="card-body p-3">
            {{-- Testing KPI mini row --}}
            <div class="row g-2 mb-3">
                <div class="col-6 col-md-3">
                    <div class="p-2 rounded bg-primary bg-opacity-10 text-center">
                        <span class="d-block small text-primary fw-semibold">Psychological Assessment</span>
                        <span class="h5 fw-bold text-primary mb-0">{{ number_format($psychologicalRequestsCount) }}</span>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="p-2 rounded bg-info bg-opacity-10 text-center">
                        <span class="d-block small text-info fw-semibold">Personality Test (BFPI)</span>
                        <span class="h5 fw-bold text-info mb-0">{{ number_format($personalityRequestsCount) }}</span>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="p-2 rounded bg-success bg-opacity-10 text-center">
                        <span class="d-block small text-success fw-semibold">Career Profiling (RIASEC)</span>
                        <span class="h5 fw-bold text-success mb-0">{{ number_format($careerRequestsCount) }}</span>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="p-2 rounded bg-danger bg-opacity-10 text-center">
                        <span class="d-block small text-danger fw-semibold">Counselor Red Flags</span>
                        <span class="h5 fw-bold text-danger mb-0">{{ number_format($redFlagsCount) }}</span>
                    </div>
                </div>
            </div>

            {{-- Main Visual Charts Row --}}
            <div class="row g-3 mb-4">
                {{-- Severity Donut --}}
                <div class="col-lg-5">
                    <div class="border rounded-3 p-3 bg-white h-100 d-flex flex-column justify-content-between">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h3 class="h6 fw-bold text-dark mb-0">
                                <i class="bi bi-pie-chart-fill text-primary me-1"></i>Psychometric Severity Level
                            </h3>
                            <span class="badge bg-light text-secondary border">Aggregated</span>
                        </div>
                        <div style="position: relative; height: 250px; width: 100%;">
                            <canvas id="severityChart"></canvas>
                        </div>
                        <div class="mt-2 pt-2 border-top small text-muted text-center">
                            Screening severity distribution across DASS-21, PHQ-9, and GAD-7 submissions.
                        </div>
                    </div>
                </div>

                {{-- Program Testing Volume (11 Campus Programs) --}}
                <div class="col-lg-7">
                    <div class="border rounded-3 p-3 bg-white h-100">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h3 class="h6 fw-bold text-dark mb-0">
                                <i class="bi bi-bar-chart-steps text-info me-1"></i>Program Testing Volume
                            </h3>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle fw-bold">
                                {{ count($programTestingVolume) ?: 11 }} Campus Programs
                            </span>
                        </div>
                        <div style="position: relative; height: 260px; width: 100%;">
                            <canvas id="programChart"></canvas>
                        </div>
                        <div class="mt-2 pt-2 border-top small text-muted text-center">
                            Testing request volume distributed across all registered degree programs.
                        </div>
                    </div>
                </div>
            </div>

            {{-- Dimension Severity Breakdowns --}}
            <div class="card border rounded-3 p-3 bg-light bg-opacity-50 mb-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3 class="h6 fw-bold text-dark mb-0">
                        <i class="bi bi-card-checklist text-primary me-1"></i>Assessment Dimension Severity Breakdown
                    </h3>
                    <span class="text-muted small">DASS-21, PHQ-9 &amp; GAD-7 Detailed Sub-Scales</span>
                </div>
                <div class="row g-3">
                    {{-- DASS-21 Depression --}}
                    <div class="col-md-4">
                        <div class="border rounded-3 p-3 bg-white h-100">
                            <div class="fw-bold text-dark mb-2 small text-uppercase"><i class="bi bi-heart-pulse text-danger me-1"></i> DASS-21 Depression</div>
                            @foreach($scaleDistributions['dass21_depression'] as $level => $cnt)
                            <div class="d-flex justify-content-between align-items-center py-1 border-bottom border-light small">
                                <span>{{ $level }}</span>
                                <span class="badge {{ in_array($level, ['Severe', 'Extremely Severe']) ? 'bg-danger' : 'bg-secondary' }}">{{ $cnt }}</span>
                            </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- DASS-21 Anxiety --}}
                    <div class="col-md-4">
                        <div class="border rounded-3 p-3 bg-white h-100">
                            <div class="fw-bold text-dark mb-2 small text-uppercase"><i class="bi bi-lightning text-warning me-1"></i> DASS-21 Anxiety</div>
                            @foreach($scaleDistributions['dass21_anxiety'] as $level => $cnt)
                            <div class="d-flex justify-content-between align-items-center py-1 border-bottom border-light small">
                                <span>{{ $level }}</span>
                                <span class="badge {{ in_array($level, ['Severe', 'Extremely Severe']) ? 'bg-danger' : 'bg-secondary' }}">{{ $cnt }}</span>
                            </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- DASS-21 Stress --}}
                    <div class="col-md-4">
                        <div class="border rounded-3 p-3 bg-white h-100">
                            <div class="fw-bold text-dark mb-2 small text-uppercase"><i class="bi bi-fire text-secondary me-1"></i> DASS-21 Stress</div>
                            @foreach($scaleDistributions['dass21_stress'] as $level => $cnt)
                            <div class="d-flex justify-content-between align-items-center py-1 border-bottom border-light small">
                                <span>{{ $level }}</span>
                                <span class="badge {{ in_array($level, ['Severe', 'Extremely Severe']) ? 'bg-danger' : 'bg-secondary' }}">{{ $cnt }}</span>
                            </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- PHQ-9 Mood --}}
                    <div class="col-md-6">
                        <div class="border rounded-3 p-3 bg-white h-100">
                            <div class="fw-bold text-dark mb-2 small text-uppercase"><i class="bi bi-activity text-info me-1"></i> PHQ-9 Mood Severity</div>
                            @foreach($scaleDistributions['phq9_mood'] as $level => $cnt)
                            <div class="d-flex justify-content-between align-items-center py-1 border-bottom border-light small">
                                <span>{{ $level }}</span>
                                <span class="badge {{ in_array($level, ['Severe', 'Moderately Severe']) ? 'bg-danger' : 'bg-secondary' }}">{{ $cnt }}</span>
                            </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- GAD-7 Anxiety --}}
                    <div class="col-md-6">
                        <div class="border rounded-3 p-3 bg-white h-100">
                            <div class="fw-bold text-dark mb-2 small text-uppercase"><i class="bi bi-shield-exclamation text-warning me-1"></i> GAD-7 Anxiety Severity</div>
                            @foreach($scaleDistributions['gad7_anxiety'] as $level => $cnt)
                            <div class="d-flex justify-content-between align-items-center py-1 border-bottom border-light small">
                                <span>{{ $level }}</span>
                                <span class="badge {{ $level === 'Severe' ? 'bg-danger' : 'bg-secondary' }}">{{ $cnt }}</span>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            {{-- Counselor Red-Flag / Priority Alerts --}}
            <div class="border rounded-3 p-3 bg-white border-start border-4 border-danger">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h3 class="h6 fw-bold text-danger mb-0">
                            <i class="bi bi-shield-exclamation me-1"></i>Counselor Red-Flag Priority Alerts
                        </h3>
                        <p class="text-muted small mb-0">
                            Examinees with Severe or Extremely Severe scoring indicators requiring urgent guidance consultation.
                        </p>
                    </div>
                    <span class="badge bg-danger text-white px-3 py-2 rounded-pill">{{ count($redFlags) }} Priority Alerts</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light small">
                            <tr>
                                <th class="py-2">Student / Examinee Name</th>
                                <th class="py-2">Student ID / Ref</th>
                                <th class="py-2">Program</th>
                                <th class="py-2">Flagged Scale</th>
                                <th class="py-2">Severity Indicator</th>
                                <th class="py-2">Date Assessed</th>
                                <th class="py-2 text-end">Counselor Action</th>
                            </tr>
                        </thead>
                        <tbody class="small">
                            @forelse($redFlags as $flag)
                            <tr>
                                <td class="fw-semibold text-dark">{{ $flag['name'] }}</td>
                                <td><span class="badge bg-light text-secondary border font-monospace">{{ $flag['student_id'] }}</span></td>
                                <td><span class="badge bg-primary bg-opacity-10 text-primary">{{ $flag['course'] }}</span></td>
                                <td><strong>{{ $flag['scale'] }}</strong></td>
                                <td>
                                    <span class="badge {{ $flag['severity'] === 'Extremely Severe' ? 'bg-danger text-white' : 'bg-warning text-dark' }} px-2 py-1">
                                        {{ $flag['severity'] }}
                                    </span>
                                </td>
                                <td class="text-muted">{{ $flag['date'] }}</td>
                                <td class="text-end">
                                    <a href="{{ $flag['view_url'] }}" class="btn btn-sm btn-outline-danger d-inline-flex align-items-center gap-1">
                                        <i class="bi bi-eye-fill"></i>
                                        <span>Review Profile</span>
                                    </a>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    <i class="bi bi-check-circle-fill text-success fs-3 d-block mb-2"></i>
                                    No critical high-risk red flags currently recorded. All scores fall within manageable thresholds.
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════ --}}
    {{-- SECTION 3: DOCUMENT SERVICES & CLEARANCES ANALYTICS --}}
    {{-- ══════════════════════════════════════════════════════════════ --}}
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-white border-bottom border-light pt-3 px-3 pb-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <h2 class="h5 fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                    <span class="badge bg-primary px-2 py-1 fs-6">3</span>
                    <span>Document Services &amp; Clearances Analytics</span>
                </h2>
                <small class="text-muted">Good Moral certificates, exit form clearances, and institutional monthly trends.</small>
            </div>
            <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-3 py-2 rounded-pill fw-semibold">
                {{ number_format($goodMoralTotal + $exitFormTotal) }} Total Clearances
            </span>
        </div>
        <div class="card-body p-3">
            {{-- Document Services KPI mini row --}}
            <div class="row g-2 mb-3">
                <div class="col-6 col-md-3">
                    <div class="p-2 rounded bg-light text-center">
                        <span class="d-block small text-muted">Good Moral Requested</span>
                        <span class="h5 fw-bold text-dark mb-0">{{ number_format($goodMoralTotal) }}</span>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="p-2 rounded bg-success bg-opacity-10 text-center">
                        <span class="d-block small text-success fw-semibold">Good Moral Claimed</span>
                        <span class="h5 fw-bold text-success mb-0">{{ number_format($goodMoralClaimed) }}</span>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="p-2 rounded bg-light text-center">
                        <span class="d-block small text-muted">Exit Forms Recorded</span>
                        <span class="h5 fw-bold text-dark mb-0">{{ number_format($exitFormTotal) }}</span>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="p-2 rounded bg-success bg-opacity-10 text-center">
                        <span class="d-block small text-success fw-semibold">Exit Forms Completed</span>
                        <span class="h5 fw-bold text-success mb-0">{{ number_format($exitFormClaimed) }}</span>
                    </div>
                </div>
            </div>

            {{-- Document Charts Row --}}
            <div class="row g-3">
                {{-- Monthly Request Trends --}}
                <div class="col-lg-7">
                    <div class="border rounded-3 p-3 bg-white h-100">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h3 class="h6 fw-bold text-dark mb-0">
                                <i class="bi bi-graph-up-arrow text-success me-1"></i>Monthly Request Trends (Last 6 Months)
                            </h3>
                            <span class="badge bg-light text-secondary border">Volume Trend</span>
                        </div>
                        <div style="position: relative; height: 240px; width: 100%;">
                            <canvas id="trendChart"></canvas>
                        </div>
                    </div>
                </div>

                {{-- Socio-Demographics --}}
                <div class="col-lg-5">
                    <div class="border rounded-3 p-3 bg-white h-100">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h3 class="h6 fw-bold text-dark mb-0">
                                <i class="bi bi-people-fill text-warning me-1"></i>Socio-Demographic Profile
                            </h3>
                            <span class="badge bg-light text-secondary border">All Examinees</span>
                        </div>
                        <div class="row align-items-center mb-3">
                            <div class="col-6">
                                <div style="position: relative; height: 140px;">
                                    <canvas id="genderChart"></canvas>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="d-flex flex-column gap-2">
                                    <div class="d-flex justify-content-between align-items-center p-2 rounded bg-light">
                                        <span class="small fw-semibold"><i class="bi bi-gender-male text-primary me-1"></i> Male</span>
                                        <span class="fw-bold text-dark">{{ number_format($maleCount) }}</span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center p-2 rounded bg-light">
                                        <span class="small fw-semibold"><i class="bi bi-gender-female text-danger me-1"></i> Female</span>
                                        <span class="fw-bold text-dark">{{ number_format($femaleCount) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="pt-2 border-top">
                            <span class="small text-muted fw-bold text-uppercase d-block mb-2">Special Priority Groups</span>
                            <div class="d-flex flex-wrap gap-1">
                                @foreach($specialCategories as $group => $count)
                                    <span class="badge bg-light text-dark border px-2 py-1 small">
                                        {{ $group }}: <strong>{{ number_format($count) }}</strong>
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // ── 1. Admission Applicants Bar Chart (Total vs. Qualified by Program) ──
    const admCtx = document.getElementById('admissionApplicantsChart');
    if (admCtx) {
        const admissionData = {!! json_encode(array_values($admissionByProgram)) !!};
        const admLabels = admissionData.map(d => d.code);
        const admTotals = admissionData.map(d => d.total);
        const admQualified = admissionData.map(d => d.qualified);

        new Chart(admCtx, {
            type: 'bar',
            data: {
                labels: admLabels,
                datasets: [
                    {
                        label: 'Total Applicants',
                        data: admTotals,
                        backgroundColor: 'rgba(15, 63, 151, 0.75)',
                        borderColor: '#0f3f97',
                        borderWidth: 1,
                        borderRadius: 4
                    },
                    {
                        label: 'Qualified / Passed',
                        data: admQualified,
                        backgroundColor: 'rgba(25, 135, 84, 0.85)',
                        borderColor: '#198754',
                        borderWidth: 1,
                        borderRadius: 4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { size: 10, weight: '600' } }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0 },
                        grid: { color: 'rgba(0,0,0,0.05)' }
                    }
                },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: { boxWidth: 12, font: { size: 11 } }
                    }
                }
            }
        });
    }

    // ── 2. Admission Status Donut Chart ──
    const admStatusCtx = document.getElementById('admissionStatusChart');
    if (admStatusCtx) {
        new Chart(admStatusCtx, {
            type: 'doughnut',
            data: {
                labels: {!! json_encode(array_keys($admissionStatusBreakdown)) !!},
                datasets: [{
                    data: {!! json_encode(array_values($admissionStatusBreakdown)) !!},
                    backgroundColor: [
                        '#198754', // Qualified - Green
                        '#dc3545', // Not Qualified - Red
                        '#ffc107'  // Pending - Yellow
                    ],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { boxWidth: 10, font: { size: 10 } }
                    }
                },
                cutout: '65%'
            }
        });
    }

    // ── 3. Psychometric Severity Donut Chart ──
    const severityCtx = document.getElementById('severityChart');
    if (severityCtx) {
        new Chart(severityCtx, {
            type: 'doughnut',
            data: {
                labels: {!! json_encode(array_keys($severityDistribution)) !!},
                datasets: [{
                    data: {!! json_encode(array_values($severityDistribution)) !!},
                    backgroundColor: [
                        '#198754', // Normal - Green
                        '#0dcaf0', // Mild - Cyan
                        '#ffc107', // Moderate - Yellow/Gold
                        '#fd7e14', // Severe - Orange
                        '#dc3545'  // Extremely Severe - Red
                    ],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { boxWidth: 11, font: { size: 10 } }
                    }
                },
                cutout: '65%'
            }
        });
    }

    // ── 4. Program Testing Volume Chart (Horizontal Bar across all Campus Programs) ──
    const programCtx = document.getElementById('programChart');
    if (programCtx) {
        new Chart(programCtx, {
            type: 'bar',
            data: {
                labels: {!! json_encode(array_keys($programTestingVolume)) !!},
                datasets: [{
                    label: 'Testing Requests',
                    data: {!! json_encode(array_values($programTestingVolume)) !!},
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
                    x: {
                        beginAtZero: true,
                        ticks: { precision: 0, font: { size: 10 } },
                        grid: { color: 'rgba(0,0,0,0.05)' }
                    },
                    y: {
                        ticks: { font: { size: 10, weight: '600' } },
                        grid: { display: false }
                    }
                },
                plugins: {
                    legend: { display: false }
                }
            }
        });
    }

    // ── 5. Monthly Request Trends Line Chart ──
    const trendCtx = document.getElementById('trendChart');
    if (trendCtx) {
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: {!! json_encode(array_keys($monthlyTrends)) !!},
                datasets: [{
                    label: 'Institutional Requests & Tests',
                    data: {!! json_encode(array_values($monthlyTrends)) !!},
                    borderColor: '#0f3f97',
                    backgroundColor: 'rgba(15, 63, 151, 0.12)',
                    borderWidth: 2.5,
                    fill: true,
                    tension: 0.35,
                    pointBackgroundColor: '#f3c332',
                    pointBorderColor: '#0f3f97',
                    pointBorderWidth: 2,
                    pointRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0 },
                        grid: { color: 'rgba(0,0,0,0.05)' }
                    },
                    x: {
                        grid: { display: false }
                    }
                },
                plugins: {
                    legend: { display: false }
                }
            }
        });
    }

    // ── 6. Gender Socio-Demographics Donut Chart ──
    const genderCtx = document.getElementById('genderChart');
    if (genderCtx) {
        new Chart(genderCtx, {
            type: 'doughnut',
            data: {
                labels: ['Male', 'Female'],
                datasets: [{
                    data: [{{ $maleCount }}, {{ $femaleCount }}],
                    backgroundColor: ['#0f3f97', '#d63384'],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                cutout: '70%'
            }
        });
    }
});
</script>
@endpush
