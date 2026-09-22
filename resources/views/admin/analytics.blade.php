@extends('layouts.app')

@section('title', 'Guidance Analytics')

@section('content')
<div class="container-fluid px-0">
    {{-- Header with Export Actions --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 small text-muted">
                    <li class="breadcrumb-item"><a href="{{ route($role . '.dashboard') }}" class="text-decoration-none">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $guidanceOnly ? 'Guidance Analytics' : 'Executive Analytics' }}</li>
                </ol>
            </nav>
            <h1 class="h3 fw-bold text-dark mb-1">
                <i class="bi bi-bar-chart-line-fill text-primary me-2"></i>Guidance Analytics
            </h1>
            <p class="text-muted small mb-0">
                Comprehensive institutional metrics for testing, assessments, document requests, and student mental wellness across PSU San Carlos.
            </p>
        </div>
        <div class="d-flex align-items-center gap-2">
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
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white border-start border-4 border-primary">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase tracking-wider">Total Requests</div>
                        <div class="h2 fw-bold text-dark mb-0 mt-1">{{ number_format($totalRequests) }}</div>
                        <div class="text-muted small mt-1">All Services & Testing</div>
                    </div>
                    <div class="rounded-circle bg-primary bg-opacity-10 p-3 text-primary">
                        <i class="bi bi-folder2-open fs-3"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white border-start border-4 border-success">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase tracking-wider">Completed Tests</div>
                        <div class="h2 fw-bold text-success mb-0 mt-1">{{ number_format($completedTests) }}</div>
                        <div class="text-muted small mt-1">Evaluated & Scored</div>
                    </div>
                    <div class="rounded-circle bg-success bg-opacity-10 p-3 text-success">
                        <i class="bi bi-check2-circle fs-3"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white border-start border-4 border-warning">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase tracking-wider">Active In-Queue</div>
                        <div class="h2 fw-bold text-warning mb-0 mt-1">{{ number_format($activeRequests) }}</div>
                        <div class="text-muted small mt-1">Pending / In Progress</div>
                    </div>
                    <div class="rounded-circle bg-warning bg-opacity-10 p-3 text-warning">
                        <i class="bi bi-clock-history fs-3"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 h-100 p-3 bg-white border-start border-4 border-danger">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase tracking-wider">Counselor Red Flags</div>
                        <div class="h2 fw-bold text-danger mb-0 mt-1">{{ number_format($redFlagsCount) }}</div>
                        <div class="text-muted small mt-1">Severe / Clinical Alert</div>
                    </div>
                    <div class="rounded-circle bg-danger bg-opacity-10 p-3 text-danger">
                        <i class="bi bi-exclamation-triangle-fill fs-3"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Main Visual Charts Row 1 --}}
    <div class="row g-3 mb-4">
        {{-- Chart 1: Global Psychometric Severity Distribution --}}
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white border-0 pt-3 px-3 pb-0 d-flex justify-content-between align-items-center">
                    <h2 class="h6 fw-bold text-dark mb-0">
                        <i class="bi bi-pie-chart-fill text-primary me-2"></i>Psychometric Severity Level
                    </h2>
                    <span class="badge bg-light text-secondary border">Aggregated</span>
                </div>
                <div class="card-body p-3 d-flex flex-column justify-content-center">
                    <div style="position: relative; height: 260px; width: 100%;">
                        <canvas id="severityChart"></canvas>
                    </div>
                    <div class="mt-3 pt-2 border-top small text-muted text-center">
                        Screening severity distribution across DASS-21, PHQ-9, and GAD-7 submissions.
                    </div>
                </div>
            </div>
        </div>

        {{-- Chart 2: Program Testing Volume (10 Official Programs) --}}
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white border-0 pt-3 px-3 pb-0 d-flex justify-content-between align-items-center">
                    <h2 class="h6 fw-bold text-dark mb-0">
                        <i class="bi bi-bar-chart-steps text-info me-2"></i>Program Testing Volume
                    </h2>
                    <span class="badge bg-light text-secondary border">10 Campus Programs</span>
                </div>
                <div class="card-body p-3">
                    <div style="position: relative; height: 260px; width: 100%;">
                        <canvas id="programChart"></canvas>
                    </div>
                    <div class="mt-3 pt-2 border-top small text-muted text-center">
                        Total assessment and service request count by enrolled or applied academic degree.
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Main Visual Charts Row 2 --}}
    <div class="row g-3 mb-4">
        {{-- Chart 3: Monthly Request Trends --}}
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white border-0 pt-3 px-3 pb-0 d-flex justify-content-between align-items-center">
                    <h2 class="h6 fw-bold text-dark mb-0">
                        <i class="bi bi-graph-up-arrow text-success me-2"></i>Monthly Request Trends (Last 6 Months)
                    </h2>
                    <span class="badge bg-light text-secondary border">Volume Trend</span>
                </div>
                <div class="card-body p-3">
                    <div style="position: relative; height: 250px; width: 100%;">
                        <canvas id="trendChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        {{-- Socio-Demographic Breakdown --}}
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-4 h-100">
                <div class="card-header bg-white border-0 pt-3 px-3 pb-0 d-flex justify-content-between align-items-center">
                    <h2 class="h6 fw-bold text-dark mb-0">
                        <i class="bi bi-people-fill text-warning me-2"></i>Socio-Demographic Profile
                    </h2>
                    <span class="badge bg-light text-secondary border">Students & Applicants</span>
                </div>
                <div class="card-body p-3">
                    <div class="row align-items-center mb-3">
                        <div class="col-sm-6">
                            <div style="position: relative; height: 160px;">
                                <canvas id="genderChart"></canvas>
                            </div>
                        </div>
                        <div class="col-sm-6">
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

                    @if(!$guidanceOnly)
                    <h3 class="h6 fw-bold text-muted small text-uppercase mb-2 pt-2 border-top">Special Priority Groups</h3>
                    <div class="d-flex flex-wrap gap-2">
                        @foreach($specialCategories as $group => $count)
                            <div class="badge bg-white text-dark border px-3 py-2 rounded-pill shadow-xs d-flex align-items-center gap-2">
                                <span class="fw-bold text-primary">{{ $group }}:</span>
                                <span class="badge bg-primary text-white rounded-pill">{{ number_format($count) }}</span>
                            </div>
                        @endforeach
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Psychometric Scale Breakdowns (Detailed Grids) --}}
    <div class="card border-0 shadow-sm rounded-4 mb-4">
        <div class="card-header bg-white border-0 pt-3 px-3 pb-0 d-flex justify-content-between align-items-center">
            <h2 class="h6 fw-bold text-dark mb-0">
                <i class="bi bi-card-checklist text-primary me-2"></i>Assessment Dimension Severity Breakdown
            </h2>
            <span class="text-muted small">DASS-21, PHQ-9 & GAD-7 breakdown</span>
        </div>
        <div class="card-body p-3">
            <div class="row g-3">
                {{-- DASS-21 Depression --}}
                <div class="col-md-4">
                    <div class="border rounded-3 p-3 bg-light bg-opacity-50 h-100">
                        <div class="fw-bold text-dark mb-2 small text-uppercase"><i class="bi bi-heart-pulse text-danger me-1"></i> DASS-21 Depression</div>
                        @foreach($scaleDistributions['dass21_depression'] as $level => $cnt)
                        <div class="d-flex justify-content-between align-items-center py-1 border-bottom border-white small">
                            <span>{{ $level }}</span>
                            <span class="badge {{ in_array($level, ['Severe', 'Extremely Severe']) ? 'bg-danger' : 'bg-secondary' }}">{{ $cnt }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>

                {{-- DASS-21 Anxiety --}}
                <div class="col-md-4">
                    <div class="border rounded-3 p-3 bg-light bg-opacity-50 h-100">
                        <div class="fw-bold text-dark mb-2 small text-uppercase"><i class="bi bi-lightning text-warning me-1"></i> DASS-21 Anxiety</div>
                        @foreach($scaleDistributions['dass21_anxiety'] as $level => $cnt)
                        <div class="d-flex justify-content-between align-items-center py-1 border-bottom border-white small">
                            <span>{{ $level }}</span>
                            <span class="badge {{ in_array($level, ['Severe', 'Extremely Severe']) ? 'bg-danger' : 'bg-secondary' }}">{{ $cnt }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>

                {{-- DASS-21 Stress --}}
                <div class="col-md-4">
                    <div class="border rounded-3 p-3 bg-light bg-opacity-50 h-100">
                        <div class="fw-bold text-dark mb-2 small text-uppercase"><i class="bi bi-fire text-secondary me-1"></i> DASS-21 Stress</div>
                        @foreach($scaleDistributions['dass21_stress'] as $level => $cnt)
                        <div class="d-flex justify-content-between align-items-center py-1 border-bottom border-white small">
                            <span>{{ $level }}</span>
                            <span class="badge {{ in_array($level, ['Severe', 'Extremely Severe']) ? 'bg-danger' : 'bg-secondary' }}">{{ $cnt }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>

                {{-- PHQ-9 Mood --}}
                <div class="col-md-6">
                    <div class="border rounded-3 p-3 bg-light bg-opacity-50 h-100">
                        <div class="fw-bold text-dark mb-2 small text-uppercase"><i class="bi bi-activity text-info me-1"></i> PHQ-9 Mood Severity</div>
                        @foreach($scaleDistributions['phq9_mood'] as $level => $cnt)
                        <div class="d-flex justify-content-between align-items-center py-1 border-bottom border-white small">
                            <span>{{ $level }}</span>
                            <span class="badge {{ in_array($level, ['Severe', 'Moderately Severe']) ? 'bg-danger' : 'bg-secondary' }}">{{ $cnt }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>

                {{-- GAD-7 Anxiety --}}
                <div class="col-md-6">
                    <div class="border rounded-3 p-3 bg-light bg-opacity-50 h-100">
                        <div class="fw-bold text-dark mb-2 small text-uppercase"><i class="bi bi-shield-exclamation text-warning me-1"></i> GAD-7 Anxiety Severity</div>
                        @foreach($scaleDistributions['gad7_anxiety'] as $level => $cnt)
                        <div class="d-flex justify-content-between align-items-center py-1 border-bottom border-white small">
                            <span>{{ $level }}</span>
                            <span class="badge {{ $level === 'Severe' ? 'bg-danger' : 'bg-secondary' }}">{{ $cnt }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Counselor Red-Flag / Clinical Priority Panel --}}
    <div class="card border-0 shadow-sm rounded-4 border-start border-4 border-danger mb-4">
        <div class="card-header bg-white border-0 pt-3 px-3 pb-0 d-flex justify-content-between align-items-center">
            <div>
                <h2 class="h5 fw-bold text-danger mb-0">
                    <i class="bi bi-shield-exclamation me-2"></i>Counselor Red-Flag Priority Alerts
                </h2>
                <p class="text-muted small mb-0 mt-1">
                    Examinees with Severe or Extremely Severe scoring indicators requiring urgent guidance counselor consultation.
                </p>
            </div>
            <span class="badge bg-danger text-white px-3 py-2 rounded-pill">{{ count($redFlags) }} Priority Alerts</span>
        </div>
        <div class="card-body p-3">
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
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Severity Distribution Chart (Donut)
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
                        labels: { boxWidth: 12, font: { size: 11 } }
                    }
                },
                cutout: '65%'
            }
        });
    }

    // 2. Program Testing Volume Chart (Horizontal Bar)
    const programCtx = document.getElementById('programChart');
    if (programCtx) {
        new Chart(programCtx, {
            type: 'bar',
            data: {
                labels: {!! json_encode(array_keys($programTestingVolume)) !!},
                datasets: [{
                    label: 'Examinees / Requests',
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
                        ticks: { font: { size: 11, weight: '600' } },
                        grid: { display: false }
                    }
                },
                plugins: {
                    legend: { display: false }
                }
            }
        });
    }

    // 3. Monthly Trends Chart (Line / Area)
    const trendCtx = document.getElementById('trendChart');
    if (trendCtx) {
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: {!! json_encode(array_keys($monthlyTrends)) !!},
                datasets: [{
                    label: 'Service Requests & Tests',
                    data: {!! json_encode(array_values($monthlyTrends)) !!},
                    borderColor: '#0f3f97',
                    backgroundColor: 'rgba(15, 63, 151, 0.12)',
                    borderWidth: 2.5,
                    fill: true,
                    tension: 0.35,
                    pointBackgroundColor: '#f3c332',
                    pointBorderColor: '#0f3f97',
                    pointBorderWidth: 2,
                    pointRadius: 5
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

    // 4. Gender Chart (Doughnut)
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
