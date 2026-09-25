@extends('layouts.app')

@section('title', 'Psychological Assessment - ' . ($testTitle ?? 'Digital Answer Sheet'))

@push('styles')
<style>
    /* ── Digital Answer Sheet Executive Styling ── */
    :root {
        --dmsgta-blue: #1d4ed8;
        --dmsgta-blue-hover: #1e40af;
        --dmsgta-blue-light: #eff6ff;
        --dmsgta-border: #e2e8f0;
        --dmsgta-card-bg: #ffffff;
        --dmsgta-text-main: #1e293b;
        --dmsgta-text-muted: #64748b;
    }

    body {
        background-color: #f8fafc;
        color: var(--dmsgta-text-main);
        font-family: system-ui, -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    }

    /* Container max width optimization */
    .assessment-container {
        max-width: 1060px;
        margin: 0 auto;
        padding-bottom: 90px; /* offset for sticky bottom bar */
    }

    /* ── Top Header & Info Card ── */
    .assessment-header-card {
        background: #ffffff;
        border: 1px solid var(--dmsgta-border);
        border-radius: 14px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        padding: 18px 22px;
        margin-bottom: 20px;
    }

    /* Psychological Assessment Flow Stepper */
    .assessment-flow {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }

    .flow-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 14px;
        border-radius: 9999px;
        font-size: 0.8125rem;
        font-weight: 600;
        letter-spacing: 0.02em;
        border: 1px solid transparent;
        transition: all 0.2s ease;
    }

    .flow-badge.done {
        background-color: #ecfdf5;
        color: #065f46;
        border-color: #a7f3d0;
    }

    .flow-badge.current {
        background-color: #2563eb;
        color: #ffffff;
        border-color: #2563eb;
        box-shadow: 0 2px 6px rgba(37, 99, 235, 0.3);
    }

    .flow-badge.pending {
        background-color: #f1f5f9;
        color: #64748b;
        border-color: #cbd5e1;
    }

    .flow-separator {
        color: #cbd5e1;
        font-size: 0.75rem;
    }

    /* Timer Display */
    .timer-display-pill {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: #f8fafc;
        border: 1px solid #cbd5e1;
        border-radius: 10px;
        padding: 6px 14px;
        font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        font-size: 1.15rem;
        font-weight: 700;
        color: #1e293b;
    }

    .timer-display-pill.warning {
        background-color: #fef2f2;
        border-color: #fca5a5;
        color: #dc2626;
        animation: pulseTimer 1.5s infinite ease-in-out;
    }

    @keyframes pulseTimer {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.75; }
    }

    /* ── Respondent Profile Metadata Block ── */
    .respondent-profile-card {
        background: #ffffff;
        border: 1px solid var(--dmsgta-border);
        border-radius: 14px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
        padding: 18px 20px;
        margin-bottom: 24px;
    }

    .metadata-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px 24px;
    }

    .meta-item {
        display: flex;
        flex-direction: column;
    }

    .meta-label {
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--dmsgta-text-muted);
        margin-bottom: 2px;
    }

    .meta-value {
        font-size: 0.9375rem;
        font-weight: 600;
        color: var(--dmsgta-text-main);
        word-break: break-word;
    }

    /* ── Question Cards & 2-Column Grid (Desktop View ≥ 768px) ── */
    .questions-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 16px;
    }

    .question-card {
        background: #ffffff;
        border: 1px solid var(--dmsgta-border);
        border-radius: 14px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
        padding: 18px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        transition: border-color 0.2s, box-shadow 0.2s, transform 0.15s;
    }

    .question-card:hover {
        border-color: #cbd5e1;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }

    .question-card.unanswered {
        border-color: #fca5a5;
        background-color: #fffaf0;
    }

    .question-header {
        margin-bottom: 14px;
    }

    .question-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background-color: #eff6ff;
        color: #1d4ed8;
        font-weight: 700;
        font-size: 0.8125rem;
        padding: 2px 10px;
        border-radius: 6px;
        border: 1px solid #bfdbfe;
        margin-bottom: 6px;
    }

    .question-prompt {
        font-size: 0.95rem;
        font-weight: 600;
        color: #1e293b;
        line-height: 1.45;
        margin-bottom: 4px;
    }

    .question-subtitle {
        font-size: 0.775rem;
        color: #64748b;
    }

    /* ── Grid Choice Options (Stack / Pill Buttons) ── */
    .choices-stack {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .choice-option {
        position: relative;
        display: block;
        cursor: pointer;
        margin-bottom: 0;
    }

    .choice-option input[type="radio"] {
        position: absolute;
        opacity: 0;
        width: 0;
        height: 0;
    }

    .choice-box {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 14px;
        border: 1.5px solid #e2e8f0;
        border-radius: 10px;
        background-color: #ffffff;
        transition: all 0.15s ease;
        user-select: none;
    }

    .choice-option:hover .choice-box {
        border-color: #93c5fd;
        background-color: #f8fafc;
    }

    /* Selected Choice State (Soft Blue Tint) */
    .choice-option input[type="radio"]:checked + .choice-box {
        background-color: #eff6ff !important;
        border-color: #2563eb !important;
        color: #1d4ed8 !important;
        box-shadow: 0 0 0 1px #2563eb;
    }

    .choice-value-pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 24px;
        height: 24px;
        border-radius: 50%;
        background-color: #f1f5f9;
        color: #475569;
        font-size: 0.75rem;
        font-weight: 700;
        flex-shrink: 0;
        transition: all 0.15s ease;
    }

    .choice-option input[type="radio"]:checked + .choice-box .choice-value-pill {
        background-color: #2563eb;
        color: #ffffff;
    }

    .choice-text {
        font-size: 0.875rem;
        font-weight: 500;
        color: #334155;
        flex-grow: 1;
    }

    .choice-option input[type="radio"]:checked + .choice-box .choice-text {
        font-weight: 600;
        color: #1e40af;
    }

    /* ── Sticky Bottom Action Bar ── */
    .sticky-action-bar {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        z-index: 1020;
        background: rgba(255, 255, 255, 0.94);
        backdrop-filter: blur(10px);
        border-top: 1px solid #e2e8f0;
        box-shadow: 0 -4px 16px rgba(0, 0, 0, 0.06);
        padding: 12px 20px;
    }

    .action-bar-inner {
        max-width: 1060px;
        margin: 0 auto;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 16px;
    }

    .progress-summary {
        font-size: 0.875rem;
        color: #475569;
    }

    .progress-bar-container {
        width: 140px;
        height: 8px;
        background-color: #e2e8f0;
        border-radius: 4px;
        overflow: hidden;
        margin-top: 4px;
    }

    .progress-bar-fill {
        height: 100%;
        background-color: #2563eb;
        width: 0%;
        transition: width 0.3s ease;
    }

    .btn-submit-test {
        background: linear-gradient(135deg, #2563eb, #1d4ed8);
        border: none;
        color: #ffffff;
        font-weight: 600;
        font-size: 0.95rem;
        padding: 10px 24px;
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(37, 99, 235, 0.25);
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.2s ease;
        white-space: nowrap;
    }

    .btn-submit-test:hover {
        background: linear-gradient(135deg, #1d4ed8, #1e40af);
        box-shadow: 0 4px 12px rgba(37, 99, 235, 0.35);
        transform: translateY(-1px);
        color: #ffffff;
    }

    /* ── Mobile Viewports (< 768px) ── */
    @media (max-width: 767.98px) {
        .assessment-container {
            padding-left: 12px;
            padding-right: 12px;
            padding-bottom: 96px;
        }

        .assessment-header-card {
            padding: 14px 16px;
            border-radius: 12px;
            margin-bottom: 14px;
        }

        .respondent-profile-card {
            padding: 14px 16px;
            border-radius: 12px;
            margin-bottom: 16px;
        }

        /* 1-column single vertical stack on mobile */
        .metadata-grid {
            grid-template-columns: 1fr;
            gap: 10px;
        }

        .questions-grid {
            grid-template-columns: 1fr;
            gap: 12px;
        }

        /* Optimized mobile card padding */
        .question-card {
            padding: 14px; /* p-3.5 */
            border-radius: 12px;
        }

        /* Enhanced mobile touch targets */
        .choice-box {
            padding: 12px 14px; /* py-3 px-3.5 */
            min-height: 48px;
        }

        .choice-text {
            font-size: 0.9125rem;
        }

        .assessment-flow {
            width: 100%;
            justify-content: flex-start;
            margin-top: 8px;
        }

        .timer-display-pill {
            font-size: 1rem;
            padding: 4px 10px;
        }

        .sticky-action-bar {
            padding: 10px 14px;
        }

        .progress-bar-container {
            width: 90px;
        }

        .btn-submit-test {
            padding: 10px 18px;
            font-size: 0.9rem;
        }
    }
</style>
@endpush

@section('content')
<div class="assessment-container">
    @php
        // Resolve Active Test Type & Test Flow Setup
        $currentTest = strtolower($testType ?? $activeTest ?? 'gad7');
        $flow = $testFlow ?? ['dass21', 'phq9', 'gad7'];
        
        // Resolve respondent profile variables with safe defaults
        $respondent = $profile ?? [];
        $respName   = $respondent['name'] ?? ($appointment->applicant->full_name ?? ($entry->full_name ?? 'DE LEON, MARK LEONARD, ABALOS'));
        $studentId  = $respondent['student_id'] ?? ($appointment->applicant->application_number ?? ($entry->reference ?? '23-SC-4160'));
        $receiptNo  = $respondent['receipt_no'] ?? ($appointment->request_code ?? ($entry->reference ?? 'OR-2026-08942'));
        $testDate   = $respondent['date'] ?? now()->format('F d, Y');
        $courseSec  = $respondent['course_section'] ?? ($entry->course ?? 'BS Information Technology 4-A');
        $sexPurpose = $respondent['sex_purpose'] ?? 'Male / Psychological Assessment & Good Moral';

        // Questions Array
        $questionList = $questions ?? [];
        if (empty($questionList)) {
            $scoringSvc = app(\App\Services\GuidanceTestScoringService::class);
            $assessmentCtrl = app(\App\Http\Controllers\GuidanceAssessmentController::class);
            $questionList = $assessmentCtrl->getQuestionsFor($currentTest);
        }

        // Test Display Metadata
        $testMeta = match($currentTest) {
            'dass21' => [
                'title'       => 'DASS-21 Assessment',
                'description' => 'Depression, Anxiety, and Stress Scales (21 items)',
                'instruction' => 'Please read each statement and select a score (0 to 3) indicating how much it applied to you over the past week.',
                'choices'     => \App\Services\GuidanceTestScoringService::DASS21_CHOICES,
                'submitText'  => 'Submit DASS-21 & Continue',
            ],
            'phq9' => [
                'title'       => 'PHQ-9 Assessment',
                'description' => 'Patient Health Questionnaire for Depression Screening (9 items)',
                'instruction' => 'Over the last 2 weeks, how often have you been bothered by any of the following problems?',
                'choices'     => \App\Services\GuidanceTestScoringService::PHQ_GAD_CHOICES,
                'submitText'  => 'Submit PHQ-9 & Continue',
            ],
            'gad7' => [
                'title'       => 'GAD-7 Assessment',
                'description' => 'Generalized Anxiety Disorder Questionnaire (7 items)',
                'instruction' => 'Over the last 2 weeks, how often have you been bothered by the following problems?',
                'choices'     => \App\Services\GuidanceTestScoringService::PHQ_GAD_CHOICES,
                'submitText'  => 'Submit GAD-7',
            ],
            default => [
                'title'       => strtoupper($currentTest) . ' Assessment',
                'description' => 'Psychological Assessment & Guidance Instrument',
                'instruction' => 'Select the response that best matches your self-evaluation.',
                'choices'     => \App\Services\GuidanceTestScoringService::PHQ_GAD_CHOICES,
                'submitText'  => 'Submit Final Answers',
            ]
        };
    @endphp

    {{-- ═══ 1. MATCH REFERENCE UI: TOP HEADER SECTION ═══ --}}
    <div class="assessment-header-card">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
            <div>
                <h1 class="h5 fw-bold text-primary mb-1 d-flex align-items-center gap-2">
                    <i class="bi bi-file-earmark-medical text-primary"></i>
                    {{ $testMeta['title'] }}
                </h1>
                <p class="text-muted small mb-0">{{ $testMeta['description'] }}</p>
            </div>
            
            {{-- Countdown Timer --}}
            <div class="d-flex align-items-center gap-2">
                <div class="timer-display-pill" id="timerPill" title="Time remaining for this section">
                    <i class="bi bi-clock-history text-secondary"></i>
                    <span id="countdownTimer">10:00</span>
                </div>
            </div>
        </div>

        {{-- Psychological Assessment Flow Stepper (Done / Current / Pending) --}}
        <div class="d-flex flex-wrap justify-content-between align-items-center pt-2 border-top border-slate-100 gap-2">
            <div class="assessment-flow">
                <span class="text-muted small fw-semibold me-1">Assessment Flow:</span>
                @foreach($flow as $index => $step)
                    @php
                        $stepLabel = strtoupper($step);
                        $isCurrent = ($step === $currentTest);
                        $currentIndex = array_search($currentTest, $flow);
                        $stepIndex = array_search($step, $flow);
                        $isDone = ($currentIndex !== false && $stepIndex < $currentIndex);
                        $isPending = ($currentIndex !== false && $stepIndex > $currentIndex);
                    @endphp

                    @if($isDone)
                        <span class="flow-badge done">
                            <i class="bi bi-check-circle-fill"></i> {{ $stepLabel }} <small class="opacity-75">(Done)</small>
                        </span>
                    @elseif($isCurrent)
                        <span class="flow-badge current">
                            <i class="bi bi-play-circle-fill"></i> {{ $stepLabel }} <small class="opacity-90">(Current)</small>
                        </span>
                    @else
                        <span class="flow-badge pending">
                            <i class="bi bi-circle"></i> {{ $stepLabel }} <small class="opacity-75">(Pending)</small>
                        </span>
                    @endif

                    @if(!$loop->last)
                        <i class="bi bi-chevron-right flow-separator"></i>
                    @endif
                @endforeach
            </div>

            <div class="d-none d-md-block">
                <span class="badge bg-light text-secondary border px-3 py-1.5 small">
                    <i class="bi bi-info-circle me-1"></i> Paper &amp; Digital Mode
                </span>
            </div>
        </div>

        {{-- How to answer instructions banner --}}
        <div class="alert alert-light border border-slate-200 mt-3 mb-0 p-2.5 rounded-3 d-flex align-items-start gap-2">
            <i class="bi bi-lightbulb text-warning fs-5 mt-0.5 flex-shrink-0"></i>
            <div class="small text-secondary">
                <strong>How to answer:</strong> {{ $testMeta['instruction'] }} Select the response that best matches the paper test item.
            </div>
        </div>
    </div>

    {{-- ═══ 2. RESPONDENT PROFILE METADATA BLOCK (2-COLUMN GRID) ═══ --}}
    <div class="respondent-profile-card">
        <div class="d-flex align-items-center gap-2 mb-3 pb-2 border-bottom border-slate-100">
            <i class="bi bi-person-badge text-primary fs-5"></i>
            <h2 class="h6 fw-bold mb-0 text-dark">Respondent Information</h2>
        </div>

        <div class="metadata-grid">
            <div class="meta-item">
                <span class="meta-label">Respondent Name</span>
                <span class="meta-value">{{ $respName }}</span>
            </div>
            <div class="meta-item">
                <span class="meta-label">Student ID Number</span>
                <span class="meta-value font-monospace">{{ $studentId }}</span>
            </div>
            <div class="meta-item">
                <span class="meta-label">O.R. # / Receipt Number</span>
                <span class="meta-value font-monospace">{{ $receiptNo }}</span>
            </div>
            <div class="meta-item">
                <span class="meta-label">Date of Test</span>
                <span class="meta-value">{{ $testDate }}</span>
            </div>
            <div class="meta-item">
                <span class="meta-label">Course &amp; Section</span>
                <span class="meta-value">{{ $courseSec }}</span>
            </div>
            <div class="meta-item">
                <span class="meta-label">Sex &amp; Purpose</span>
                <span class="meta-value">{{ $sexPurpose }}</span>
            </div>
        </div>
    </div>

    {{-- ═══ 3. QUESTION CARDS & MULTI-COLUMN GRID (DESKTOP ≥ 768px: 2 COLS, MOBILE < 768px: 1 COL) ═══ --}}
    <form method="POST" action="{{ $submitUrl ?? route('guidance.submit', $token ?? '') }}" id="digitalAnswerSheetForm">
        @csrf
        <input type="hidden" name="test_type" value="{{ $currentTest }}">

        <div class="questions-grid mb-4" id="questionsContainer">
            @foreach($questionList as $num => $questionText)
                @php
                    $inputName = "answers[{$currentTest}][{$num}]";
                    $itemId = "q_{$currentTest}_{$num}";
                    $savedVal = old("answers.{$currentTest}.{$num}", $savedAnswers[$num] ?? null);
                @endphp

                <div class="question-card" id="card_{{ $currentTest }}_{{ $num }}" data-item-num="{{ $num }}">
                    <div class="question-header">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="question-badge">Question {{ $num }}</span>
                            <span class="text-muted small fw-medium">Required</span>
                        </div>
                        <div class="question-prompt">{{ $questionText }}</div>
                        <div class="question-subtitle">Select the response that best matches the paper test item.</div>
                    </div>

                    {{-- Vertical Stack Choice Options --}}
                    <div class="choices-stack" role="radiogroup" aria-label="Question {{ $num }} Choices">
                        @foreach($testMeta['choices'] as $val => $choiceLabel)
                            @php
                                $isChecked = ($savedVal !== null && (string)$savedVal === (string)$val);
                            @endphp
                            <label class="choice-option" for="{{ $itemId }}_{{ $val }}">
                                <input type="radio" 
                                       name="{{ $inputName }}" 
                                       id="{{ $itemId }}_{{ $val }}" 
                                       value="{{ $val }}" 
                                       @checked($isChecked)
                                       required>
                                <div class="choice-box">
                                    <span class="choice-value-pill">{{ $val }}</span>
                                    <span class="choice-text">{{ $val }} - {{ $choiceLabel }}</span>
                                </div>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        {{-- ── Optional PHQ-9 Item 10 / GAD-7 Difficulty Rating Card ── --}}
        @if(in_array($currentTest, ['phq9', 'gad7'], true))
            @php
                $diffSavedVal = old("answers.{$currentTest}.difficulty_rating", $savedAnswers['difficulty_rating'] ?? null);
            @endphp
            <div class="question-card border-primary border-2 mb-4" id="card_difficulty" style="background-color: #f8fafc;">
                <div class="question-header">
                    <span class="badge bg-primary px-2.5 py-1 mb-2">Global Functional Difficulty</span>
                    <div class="question-prompt text-dark">
                        If you checked off any problems, how difficult have these problems made it for you to do your work, take care of things at home, or get along with other people?
                    </div>
                    <div class="question-subtitle text-muted">This rating provides overall context for counselor review and evaluation.</div>
                </div>

                <div class="choices-stack">
                    @foreach(\App\Services\GuidanceTestScoringService::DIFFICULTY_CHOICES as $dVal => $dLabel)
                        @php
                            $isDiffChecked = ($diffSavedVal !== null && (string)$diffSavedVal === (string)$dVal);
                        @endphp
                        <label class="choice-option" for="diff_opt_{{ $dVal }}">
                            <input type="radio" 
                                   name="answers[{{ $currentTest }}][difficulty_rating]" 
                                   id="diff_opt_{{ $dVal }}" 
                                   value="{{ $dVal }}" 
                                   @checked($isDiffChecked)>
                            <div class="choice-box">
                                <span class="choice-value-pill">{{ $dVal }}</span>
                                <span class="choice-text">{{ $dLabel }}</span>
                            </div>
                        </label>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- ═══ 4. STICKY ACTION BUTTON (BOTTOM RIGHT / RESPONSIVE BAR) ═══ --}}
        <div class="sticky-action-bar">
            <div class="action-bar-inner">
                <div>
                    <div class="progress-summary">
                        <strong id="answeredCount">0</strong> of <strong>{{ count($questionList) }}</strong> answered
                    </div>
                    <div class="progress-bar-container">
                        <div class="progress-bar-fill" id="progressBarFill"></div>
                    </div>
                </div>

                <div class="d-flex align-items-center gap-2">
                    <button type="submit" class="btn-submit-test" id="submitBtn">
                        <span>{{ $testMeta['submitText'] }}</span>
                        <i class="bi bi-arrow-right-short fs-5"></i>
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('digitalAnswerSheetForm');
    if (!form) return;

    const totalQuestions = {{ count($questionList) }};
    const answeredCountEl = document.getElementById('answeredCount');
    const progressBarFill = document.getElementById('progressBarFill');
    const timerEl = document.getElementById('countdownTimer');
    const timerPill = document.getElementById('timerPill');

    // ── Update Progress & Card Highlighting ──
    function updateProgress() {
        const questionCards = form.querySelectorAll('.question-card[data-item-num]');
        let answered = 0;

        questionCards.forEach(card => {
            const hasChecked = card.querySelector('input[type="radio"]:checked');
            if (hasChecked) {
                answered++;
                card.classList.remove('unanswered');
            }
        });

        if (answeredCountEl) answeredCountEl.textContent = answered;
        if (progressBarFill) {
            const percentage = totalQuestions > 0 ? (answered / totalQuestions) * 100 : 0;
            progressBarFill.style.width = percentage + '%';
        }
    }

    // Radio change listener for live UI updates
    form.addEventListener('change', function (e) {
        if (e.target.matches('input[type="radio"]')) {
            updateProgress();
        }
    });

    // Initial progress computation
    updateProgress();

    // ── Countdown Timer (10-minute section countdown) ──
    let totalSeconds = 600; // 10 minutes default
    const timerInterval = setInterval(function () {
        if (totalSeconds <= 0) {
            clearInterval(timerInterval);
            if (timerEl) timerEl.textContent = '00:00';
            return;
        }

        totalSeconds--;
        const minutes = Math.floor(totalSeconds / 60);
        const seconds = totalSeconds % 60;
        const formatted = String(minutes).padStart(2, '0') + ':' + String(seconds).padStart(2, '0');

        if (timerEl) timerEl.textContent = formatted;

        // Warning state when under 2 minutes
        if (totalSeconds <= 120 && timerPill) {
            timerPill.classList.add('warning');
        }
    }, 1000);

    // ── Validation on Submit ──
    form.addEventListener('submit', function (e) {
        const questionCards = form.querySelectorAll('.question-card[data-item-num]');
        let firstUnanswered = null;

        questionCards.forEach(card => {
            const hasChecked = card.querySelector('input[type="radio"]:checked');
            if (!hasChecked) {
                card.classList.add('unanswered');
                if (!firstUnanswered) firstUnanswered = card;
            } else {
                card.classList.remove('unanswered');
            }
        });

        if (firstUnanswered) {
            e.preventDefault();
            firstUnanswered.scrollIntoView({ behavior: 'smooth', block: 'center' });
            const firstInput = firstUnanswered.querySelector('input[type="radio"]');
            if (firstInput) firstInput.focus();
        }
    });
});
</script>
@endpush
