@extends('layouts.app')

@section('content')
@if($appointment->test_category === 'career')<a class="btn btn-primary mb-3" href="{{ route(auth()->user()->role.'.guidance-appointments.career-report', $appointment) }}">Career assessment report / PDF</a>@endif
<div class="d-flex justify-content-between align-items-center mb-4 no-print">
    <div>
        <h1 class="h3 mb-1">Psychological Assessment Results</h1>
        <p class="text-muted mb-0">Official scoring summary and interpretation for Guidance Counselor review.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route(auth()->user()->role.'.guidance-appointments.completed') }}" class="btn btn-outline-secondary">← Completed List</a>
        <button type="button" class="btn btn-primary" onclick="window.print()">🖨️ Print Report</button>
    </div>
</div>

<div class="card shadow-sm mb-4 print-card">
    <div class="card-body p-4">
        <div class="text-center pb-3 mb-4 border-bottom">
            <h2 class="h5 mb-1 text-uppercase fw-bold">Pangasinan State University – San Carlos Campus</h2>
            <div class="text-muted small">Guidance and Counseling Services Office</div>
            <div class="fw-semibold mt-2">CONFIDENTIAL PSYCHOLOGICAL ASSESSMENT REPORT</div>
        </div>

        <div class="row g-3 mb-4 pb-3 border-bottom">
            <div class="col-md-4">
                <span class="text-muted small d-block">Name</span>
                <strong>{{ $appointment->applicant->full_name }}</strong>
            </div>
            <div class="col-md-4">
                <span class="text-muted small d-block">Student Number / Course</span>
                <strong>{{ $appointment->serviceRequest?->student_number ?: 'N/A' }} · {{ $appointment->serviceRequest?->course ?: 'N/A' }}</strong>
            </div>
            <div class="col-md-4">
                <span class="text-muted small d-block">Course / Section</span>
                <strong>{{ $appointment->serviceRequest?->courseLabel() ?: 'N/A' }}{{ $appointment->serviceRequest?->year_section ? ' / '.$appointment->serviceRequest->year_section : '' }}</strong>
            </div>
            <div class="col-md-4">
                <span class="text-muted small d-block">Status</span>
                <span class="badge text-bg-success">{{ $appointment->status }}</span>
            </div>
            <div class="col-md-4">
                <span class="text-muted small d-block">Reference Code</span>
                <code>{{ $appointment->serviceRequest?->reference ?? $appointment->request_code }}</code>
            </div>
            <div class="col-md-4">
                <span class="text-muted small d-block">Date</span>
                <strong>{{ $appointment->response?->created_at?->timezone('Asia/Manila')->format('M d, Y g:i A') ?? 'N/A' }}</strong>
            </div>
            <div class="col-md-4">
                <span class="text-muted small d-block">Verified By</span>
                <span>{{ $appointment->verifier?->name ?: 'Guidance Staff' }}</span>
            </div>
        </div>

        @php
            $summary = $appointment->response?->score_summary ?? [];
            $tests = $appointment->response?->testSummaries() ?? [];
        @endphp

        <h3 class="h5 mb-3 fw-bold text-primary">Assessment Rubric &amp; Interpretation</h3>

        @if(empty($tests))
            <div class="alert alert-info">No score summary available for this appointment.</div>
        @else
            @foreach($tests as $testKey => $testData)
                @php
                    $testLabel = \App\Services\GuidanceTestScoringService::LABELS[$testKey] ?? strtoupper($testKey);
                    $instrumentLabel = [
                        'dass21' => 'DASS-21',
                        'phq9' => 'PHQ-9',
                        'gad7' => 'GAD-7',
                    ][$testKey] ?? null;
                @endphp
                <div class="card bg-light border mb-4">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <strong class="text-uppercase">
                            {{ $testLabel }}@if($instrumentLabel) <span class="text-primary">&mdash; {{ $instrumentLabel }}</span>@endif
                        </strong>
                        @if(isset($testData['completion']))
                            <span class="badge {{ $testData['completion'] === 'Completed' ? 'text-bg-success' : 'text-bg-warning' }}">
                                {{ $testData['completion'] }} ({{ $testData['answered_items'] ?? 0 }}/{{ $testData['total_items'] ?? 0 }} items)
                            </span>
                        @endif
                    </div>
                    <div class="card-body">
                        @if($testKey === 'dass21' && isset($testData['scores']))
                            <div class="row g-3">
                                @foreach(['depression' => 'Depression', 'anxiety' => 'Anxiety', 'stress' => 'Stress'] as $scale => $label)
                                    @php
                                        $score = $testData['scores'][$scale] ?? 0;
                                        $interp = $testData['interpretation'][$scale] ?? 'Normal';
                                        $badgeColor = match($interp) {
                                            'Normal' => 'success',
                                            'Mild' => 'info',
                                            'Moderate' => 'warning',
                                            'Severe' => 'danger',
                                            'Extremely Severe' => 'dark',
                                            default => 'secondary'
                                        };
                                    @endphp
                                    <div class="col-md-4">
                                        <div class="border rounded p-3 bg-white h-100">
                                            <div class="text-muted small">{{ $label }} Scale</div>
                                            <div class="fs-4 fw-bold my-1">{{ $score }}</div>
                                            <span class="badge text-bg-{{ $badgeColor }} fs-6">{{ $interp }}</span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @elseif(in_array($testKey, ['gad7', 'phq9']) && isset($testData['scores']['total']))
                            @php
                                $total = $testData['scores']['total'];
                                $severity = $testData['interpretation']['severity'] ?? 'N/A';
                                $badgeColor = match($severity) {
                                    'Minimal' => 'success',
                                    'Mild' => 'info',
                                    'Moderate' => 'warning',
                                    'Moderately Severe', 'Severe' => 'danger',
                                    default => 'secondary'
                                };
                            @endphp
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="border rounded p-3 bg-white">
                                        <div class="text-muted small">Total Score</div>
                                        <div class="fs-3 fw-bold">{{ $total }} <span class="fs-6 text-muted">/ {{ $testKey === 'gad7' ? 21 : 27 }}</span></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="border rounded p-3 bg-white">
                                        <div class="text-muted small">Clinical Severity Level</div>
                                        <span class="badge text-bg-{{ $badgeColor }} fs-6 mt-1">{{ $severity }}</span>
                                    </div>
                                </div>
                            </div>
                        @elseif($testKey === 'career' && isset($testData['scores']))
                            <p><strong>Top RIASEC Traits:</strong> {{ $testData['interpretation']['top_traits'] }}</p>
                            <table class="table table-sm"><thead><tr><th>Interest trait</th><th>Rating (1–5)</th><th>Interpretation</th></tr></thead><tbody>
                            @foreach($testData['scores'] as $trait => $score)<tr><td>{{ $trait }}</td><td>{{ $score }}</td><td>{{ $testData['interpretation'][$trait] }}</td></tr>@endforeach
                            </tbody></table><p class="small text-muted">{{ $testData['interpretation']['review'] }}</p>
                        @elseif($testKey === 'bfpi' && isset($testData['scores']))
                            <div class="row g-3">
                                @foreach($testData['scores'] as $trait => $mean)
                                    @php
                                        $rawSum = $testData['raw_sums'][$trait] ?? null;
                                        $interp = $testData['interpretation'][$trait] ?? 'Average';
                                        $badgeColor = match($interp) {
                                            'Very High' => 'success',
                                            'High' => 'primary',
                                            'Average' => 'info',
                                            'Low' => 'warning',
                                            'Very Low' => 'secondary',
                                            default => 'dark'
                                        };
                                    @endphp
                                    <div class="col-md-4 col-sm-6">
                                        <div class="border rounded p-3 bg-white h-100 shadow-sm">
                                            <div class="text-muted small fw-bold text-uppercase">{{ $trait }}</div>
                                            <div class="fs-4 fw-bold my-1 text-primary">
                                                {{ number_format($mean, 2) }}
                                                @if($rawSum !== null)
                                                    <span class="fs-6 text-muted fw-normal">(Sum: {{ $rawSum }})</span>
                                                @endif
                                            </div>
                                            <span class="badge text-bg-{{ $badgeColor }} fs-6">{{ $interp }}</span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="text-muted">
                                {{ $testData['interpretation']['review'] ?? 'Recorded for counselor review.' }}
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        @endif

        <div class="mt-4 pt-4 border-top">
            <h3 class="h5">Raw Item Choices</h3>
            @foreach($tests as $testKey => $testData)
                @php
                    $rawAnswers = $appointment->response->answers[$testKey] ?? (!$appointment->test_types ? $appointment->response->answers : []);
                @endphp
                <h4 class="h6 mt-3">{{ \App\Services\GuidanceTestScoringService::LABELS[$testKey] ?? $testKey }}</h4>
                <div class="table-responsive"><table class="table table-sm table-bordered"><thead><tr><th>Item</th><th>Selected choice</th></tr></thead><tbody>
                @for($item = 1; $item <= ($testData['total_items'] ?? count($rawAnswers)); $item++)
                <tr><td>{{ $item }}</td><td>{{ $rawAnswers[$item] ?? 'Unanswered' }}</td></tr>
                @endfor
                </tbody></table></div>
            @endforeach
            <p class="small text-muted">Psychological Assessment scores shown above are raw subscale sums (0–21), classified using raw-score cutoffs. Screening severity is for counselor review.</p>
        </div>
        <section class="hard-copy-packet">
            @php
                foreach ($tests as $testKey => $testData) {
            @endphp
                @php
                    $definition = app(\App\Services\GuidanceTestScoringService::class)->definition($testKey);
                    $questions = app(\App\Http\Controllers\GuidanceAssessmentController::class)->getQuestionsFor($testKey);
                    $rawAnswers = $appointment->response->answers[$testKey] ?? (!$appointment->test_types ? $appointment->response->answers : []);
                    $itemCount = $testData['total_items'] ?? $definition['items'];
                    $instrument = ['dass21' => 'DASS-21', 'phq9' => 'PHQ-9', 'gad7' => 'GAD-7', 'bfpi' => 'BFPI', 'career' => 'RIASEC'][$testKey] ?? strtoupper($testKey);
                @endphp
                <article class="hard-copy-test">
                    <div class="hard-copy-heading">
                        <div><div class="small text-uppercase">Guidance and Counseling Services Office</div><h3>Hard-Copy Questionnaire &amp; Answer Sheet — {{ $instrument }}</h3></div>
                        <div class="text-end small">Date: {{ $appointment->response?->created_at?->timezone('Asia/Manila')->format('M d, Y') ?? 'N/A' }}</div>
                    </div>
                    <div class="hard-copy-profile">
                        <span><b>Name:</b> {{ $appointment->applicant->full_name }}</span>
                        <span><b>Course / Section:</b> {{ $appointment->serviceRequest?->courseLabel() ?: 'N/A' }}{{ $appointment->serviceRequest?->year_section ? ' / '.$appointment->serviceRequest->year_section : '' }}</span>
                        <span><b>Student Number:</b> {{ $appointment->serviceRequest?->student_number ?: 'N/A' }}</span>
                    </div>
                    <p class="hard-copy-instruction">Questionnaire with recorded answers. The filled circle is the response saved for this assessment.</p>
                    <div class="hard-copy-questions">
                        @forelse($questions as $item => $question)
                            <div class="hard-copy-question"><span class="question-no">{{ $item }}.</span><span>{{ $question }}</span></div>
                        @empty
                            <p class="text-muted">Use the approved questionnaire booklet for this instrument.</p>
                        @endforelse
                        @if($testKey === 'bfpi' && count($questions) < $itemCount)
                            <p class="small text-muted mb-0">Continue with the approved BFPI question booklet for items {{ count($questions) + 1 }}–{{ $itemCount }}.</p>
                        @endif
                    </div>
                    <h4 class="hard-copy-answer-title">Recorded Answer Sheet</h4>
                    <table class="hard-copy-answer-sheet">
                        <thead><tr><th>Item</th>@foreach($definition['choices'] as $choice => $choiceLabel)<th>{{ $choice }}</th>@endforeach</tr></thead>
                        <tbody>@for($item = 1; $item <= $itemCount; $item++)<tr><td>{{ $item }}</td>@foreach($definition['choices'] as $choice => $choiceLabel)<td class="{{ isset($rawAnswers[$item]) && (string) $rawAnswers[$item] === (string) $choice ? 'selected' : '' }}">{{ isset($rawAnswers[$item]) && (string) $rawAnswers[$item] === (string) $choice ? '●' : '○' }}</td>@endforeach</tr>@endfor</tbody>
                    </table>
                </article>
            @php
                }
            @endphp
        </section>
        <div class="mt-4 pt-4 border-top">
            <h4 class="h6 fw-bold">Counselor Remarks &amp; Recommendations:</h4>
            <div class="border rounded p-3 bg-light mb-4" style="min-height: 80px;">
                <p class="text-muted mb-0 small fst-italic">Official clinical notes filed during intake counseling.</p>
            </div>

            <div class="row pt-4 text-center">
                <div class="col-6 offset-6">
                    <div style="border-top: 1px solid #333; width: 220px; margin: 0 auto 4px auto;"></div>
                    <div class="fw-bold small">Licensed Guidance Counselor</div>
                    <div class="text-muted small">PRC License / Guidance Office Seal</div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.hard-copy-packet { display: none; }
@media print {
    .no-print, nav, header, footer, .sidebar { display: none !important; }
    body { background: #fff !important; margin: 0; padding: 0; font-size: 12pt; }
    .print-card { border: none !important; box-shadow: none !important; }
    .hard-copy-packet { display: block; }
    .hard-copy-test { break-before: page; page-break-before: always; font-size: 10pt; color: #000; }
    .hard-copy-heading { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #111; padding-bottom: 8px; margin-bottom: 10px; }
    .hard-copy-heading h3 { font-size: 15pt; margin: 3px 0 0; }
    .hard-copy-profile { display: grid; grid-template-columns: 1fr 1fr; gap: 6px 18px; padding: 8px 0; border-bottom: 1px solid #777; }
    .hard-copy-profile span:last-child { grid-column: span 2; }
    .hard-copy-instruction { margin: 10px 0; font-style: italic; }
    .hard-copy-questions { columns: 2; column-gap: 28px; }
    .hard-copy-question { break-inside: avoid; display: flex; gap: 5px; margin-bottom: 7px; line-height: 1.25; }
    .question-no { font-weight: 700; min-width: 22px; }
    .hard-copy-answer-title { font-size: 12pt; margin: 14px 0 5px; }
    .hard-copy-answer-sheet { width: 100%; border-collapse: collapse; text-align: center; font-size: 9pt; }
    .hard-copy-answer-sheet th, .hard-copy-answer-sheet td { border: 1px solid #444; padding: 3px 5px; }
    .hard-copy-answer-sheet th { background: #eee !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .hard-copy-answer-sheet .selected { background: #d9d9d9 !important; font-weight: 700; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}
</style>
@endsection
