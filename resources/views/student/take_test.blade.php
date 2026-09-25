<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $testTitle ?? ($testMeta['title'] ?? 'Psychological Assessment') }} - Digital Management System for Guidance Testing and Admission</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --bg-page: #f3f4f8;
            --card-bg: #ffffff;
            --card-border: #e5e7eb;
            --text-main: #111827;
            --text-muted: #6b7280;
            --brand-blue: #1d4ed8;
            --brand-blue-hover: #1e40af;
            --choice-bg: #ffffff;
            --choice-border: #e5e7eb;
            --choice-checked-bg: #eff6ff;
            --choice-checked-border: #3b82f6;
            --beige-box-bg: #faf8f3;
            --beige-box-border: #f1ede2;
        }

        body {
            background-color: var(--bg-page);
            color: var(--text-main);
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell, "Open Sans", "Helvetica Neue", sans-serif;
            margin: 0;
            padding: 16px 12px 40px;
        }

        /* ── Top University Brand Bar ── */
        .brand-header-bar {
            max-width: 900px;
            margin: 0 auto 14px;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0 4px;
        }

        .brand-logo-img {
            width: 40px;
            height: 40px;
            object-fit: contain;
            border-radius: 50%;
            background: #ffffff;
        }

        .brand-text-block {
            line-height: 1.25;
        }

        .brand-title-text {
            font-size: 0.925rem;
            font-weight: 700;
            color: #0f2b61;
            margin: 0;
        }

        .brand-sub-text {
            font-size: 0.75rem;
            color: var(--text-muted);
            margin: 0;
        }

        /* ── Main Outer White Card ── */
        .sheet-outer-card {
            max-width: 900px;
            margin: 0 auto;
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
            padding: 28px 32px 36px;
        }

        /* ── Document Title Header ── */
        .doc-title-section {
            margin-bottom: 20px;
        }

        .doc-main-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #111827;
            margin: 0 0 4px 0;
            letter-spacing: -0.01em;
        }

        .doc-meta-subtitle {
            font-size: 0.8125rem;
            color: #4b5563;
            margin: 0 0 4px 0;
        }

        .doc-instrument-tag {
            font-size: 0.8125rem;
            color: var(--brand-blue);
            text-decoration: none;
            display: inline-block;
            margin-bottom: 10px;
        }

        .doc-date-line {
            font-size: 0.8125rem;
            font-weight: 600;
            color: #1f2937;
            margin: 0;
        }

        .doc-schedule-line {
            font-size: 0.775rem;
            color: #9ca3af;
            margin: 0;
        }

        /* ── Beige / Sand Info Box (How to Answer + Flow) ── */
        .beige-info-card {
            background-color: var(--beige-box-bg);
            border: 1px solid var(--beige-box-border);
            border-radius: 12px;
            padding: 18px 20px;
            margin-bottom: 24px;
        }

        .info-card-header {
            font-size: 0.875rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 4px;
        }

        .info-card-instruction {
            font-size: 0.8125rem;
            color: #4b5563;
            margin-bottom: 12px;
            line-height: 1.4;
        }

        /* Time Remaining Inner Box */
        .timer-inner-card {
            background: #ffffff;
            border: 1px solid #eae6db;
            border-radius: 8px;
            padding: 10px 14px;
            margin-bottom: 14px;
        }

        .timer-label-text {
            font-size: 0.725rem;
            color: #6b7280;
            margin-bottom: 2px;
        }

        .timer-countdown-digits {
            font-size: 1.35rem;
            font-weight: 700;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            color: #111827;
            line-height: 1.1;
            margin-bottom: 3px;
        }

        .timer-helper-note {
            font-size: 0.75rem;
            color: #9ca3af;
            margin: 0;
        }

        /* Psychological Assessment Flow List */
        .flow-section-title {
            font-size: 0.75rem;
            font-weight: 600;
            color: #6b7280;
            margin-bottom: 6px;
        }

        .flow-rows-list {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .flow-row-item {
            background: #ffffff;
            border: 1px solid #eae6db;
            border-radius: 6px;
            padding: 7px 14px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.8125rem;
        }

        .flow-test-name {
            font-weight: 600;
            color: #374151;
        }

        .flow-status-text {
            font-size: 0.775rem;
            font-weight: 600;
        }

        .flow-status-text.current {
            color: #2563eb;
        }

        .flow-status-text.done {
            color: #4b5563;
        }

        .flow-status-text.pending {
            color: #9ca3af;
        }

        /* ── Respondent Profile Metadata Form ── */
        .respondent-form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px 20px;
            margin-bottom: 28px;
        }

        .form-meta-group {
            display: flex;
            flex-direction: column;
        }

        .form-meta-group.full-width {
            grid-column: 1 / -1;
        }

        .form-meta-label {
            font-size: 0.8125rem;
            font-weight: 500;
            color: #374151;
            margin-bottom: 4px;
        }

        .form-meta-field {
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            padding: 7px 12px;
            font-size: 0.875rem;
            color: #1f2937;
            width: 100%;
            outline: none;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }

        .form-meta-field:focus {
            border-color: #3b82f6;
            background-color: #ffffff;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12);
        }

        .form-meta-field::placeholder {
            color: #9ca3af;
            font-size: 0.8125rem;
        }

        /* ── Questions 2-Column Grid ── */
        .questions-two-col-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px 16px;
            margin-bottom: 28px;
        }

        .question-module-card {
            background-color: #fcfcfd;
            border: 1px solid #f1f2f4;
            border-radius: 8px;
            padding: 14px 14px 12px;
            transition: all 0.15s ease;
        }

        .question-module-card.unanswered {
            border-color: #fca5a5;
            background-color: #fff9f9;
        }

        .question-number-title {
            font-size: 0.875rem;
            font-weight: 700;
            color: #111827;
            margin: 0 0 2px 0;
        }

        .question-instructions-sub {
            font-size: 0.725rem;
            color: #6b7280;
            margin: 0 0 10px 0;
        }

        /* Choices Stack */
        .choices-vertical-list {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .choice-item-label {
            display: flex;
            align-items: center;
            gap: 9px;
            background-color: var(--choice-bg);
            border: 1px solid var(--choice-border);
            border-radius: 6px;
            padding: 7px 12px;
            margin: 0;
            cursor: pointer;
            user-select: none;
            transition: all 0.15s ease;
        }

        .choice-item-label:hover {
            border-color: #93c5fd;
            background-color: #f8fafc;
        }

        .choice-radio-input {
            margin: 0;
            width: 15px;
            height: 15px;
            accent-color: #2563eb;
            cursor: pointer;
        }

        .choice-text-span {
            font-size: 0.8125rem;
            color: #374151;
            font-weight: 400;
            line-height: 1.25;
        }

        /* Active Selected State */
        .choice-item-label.selected {
            background-color: var(--choice-checked-bg) !important;
            border-color: var(--choice-checked-border) !important;
        }

        .choice-item-label.selected .choice-text-span {
            color: #1d4ed8;
            font-weight: 600;
        }

        /* ── Bottom Submit Button Section ── */
        .bottom-submit-wrapper {
            display: flex;
            justify-content: flex-end;
            padding-top: 10px;
        }

        .btn-submit-action {
            background-color: #1d4ed8;
            color: #ffffff;
            font-size: 0.875rem;
            font-weight: 600;
            padding: 8px 20px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            box-shadow: 0 1px 3px rgba(29, 78, 216, 0.2);
            transition: all 0.15s ease;
        }

        .btn-submit-action:hover {
            background-color: #1e40af;
            box-shadow: 0 2px 6px rgba(30, 64, 175, 0.3);
        }

        .btn-submit-action:active {
            transform: scale(0.98);
        }

        /* ── Mobile Viewports (< 768px) ── */
        @media (max-width: 767.98px) {
            body {
                padding: 10px 8px 30px;
            }

            .sheet-outer-card {
                padding: 18px 16px 24px;
                border-radius: 12px;
            }

            .doc-main-title {
                font-size: 1.25rem;
            }

            .respondent-form-grid {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .questions-two-col-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .question-module-card {
                padding: 12px 10px;
            }

            .choice-item-label {
                padding: 9px 10px;
            }

            .choice-text-span {
                font-size: 0.85rem;
            }

            .btn-submit-action {
                width: 100%;
                padding: 10px 16px;
                font-size: 0.925rem;
            }
        }
    </style>
</head>
<body>

@php
    // Detect active test type (dass21, phq9, gad7)
    $currentTest = strtolower($testType ?? $activeTest ?? 'dass21');
    $flow = ['dass21', 'phq9', 'gad7'];

    // Respondent details
    $respName   = $profile['name'] ?? ($appointment->applicant->full_name ?? ($entry->full_name ?? 'DE LEON, MARK LEONARD, ABALOS'));
    $studentId  = $profile['student_id'] ?? ($appointment->applicant->application_number ?? ($entry->reference ?? '23-SC-4160'));
    $receiptNo  = $profile['receipt_no'] ?? ($appointment->request_code ?? ($entry->reference ?? '67676'));
    $testDate   = $profile['date'] ?? now()->format('F d, Y');
    $course     = $profile['course'] ?? ($entry->course ?? 'BSIT');
    $section    = $profile['section'] ?? 'Not provided';
    $sex        = $profile['sex'] ?? 'Male';
    $purpose    = $profile['purpose'] ?? 'OJT';

    // Metadata per instrument matching the screenshots exactly
    $testConfigs = [
        'dass21' => [
            'title'       => 'DASS-21 Answer Sheet',
            'tag'         => 'Depression, Anxiety, Stress Scales',
            'instruction' => 'Rate how much each statement applied to you using the DASS-21 scale from 0 to 3.',
            'count'       => 21,
            'choices'     => [
                0 => 'Never',
                1 => 'Sometimes',
                2 => 'Often',
                3 => 'Almost Always',
            ],
            'submitText'  => 'Submit and Continue',
        ],
        'phq9' => [
            'title'       => 'PHQ-9 Answer Sheet',
            'tag'         => 'Patient Health Questionnaire',
            'instruction' => 'Choose how often each problem bothered you over the last two weeks.',
            'count'       => 9,
            'choices'     => [
                0 => 'Not at all',
                1 => 'Several days',
                2 => 'More than half the days',
                3 => 'Nearly every day',
            ],
            'submitText'  => 'Submit and Continue',
        ],
        'gad7' => [
            'title'       => 'GAD-7 Answer Sheet',
            'tag'         => 'Generalized Anxiety Disorder',
            'instruction' => 'Choose how often each problem bothered you over the last two weeks.',
            'count'       => 7,
            'choices'     => [
                0 => 'Not at all',
                1 => 'Several days',
                2 => 'More than half the days',
                3 => 'Nearly every day',
            ],
            'submitText'  => 'Submit GAD-7',
        ],
        'bfpi' => [
            'title'       => 'Big Five Personality Test Answer Sheet',
            'tag'         => 'Big Five Personality Inventory (BFI-44)',
            'instruction' => 'Rate each item from 1 (Disagree) to 5 (Agree) using your paper question booklet.',
            'count'       => 44,
            'choices'     => [
                1 => 'Disagree (D)',
                2 => 'Slightly Disagree (SD)',
                3 => 'Neutral (N)',
                4 => 'Slightly Agree (SA)',
                5 => 'Agree (A)',
            ],
            'submitText'  => 'Submit Personality Test',
        ],
    ];

    $cfg = $testConfigs[$currentTest] ?? $testConfigs['dass21'];
    $itemCount = $cfg['count'];
@endphp

<!-- ═══ TOP BRAND BAR ═══ -->
<div class="brand-header-bar">
    <img src="{{ asset('images/psu-logo.png') }}" alt="PSU Logo" class="brand-logo-img">
    <div class="brand-text-block">
        <h2 class="brand-title-text">Pangasinan State University - San Carlos Campus</h2>
        <p class="brand-sub-text">Digital Management System for Guidance Testing and Admission</p>
    </div>
</div>

<!-- ═══ MAIN DIGITAL ANSWER SHEET CONTAINER ═══ -->
<main class="sheet-outer-card">
    <!-- Document Title Header -->
    <div class="doc-title-section">
        <h1 class="doc-main-title">{{ $cfg['title'] }}</h1>
        <p class="doc-meta-subtitle">Psychological Test | {{ strtoupper($respName) }} | Student ID Number {{ $studentId }}</p>
        <span class="doc-instrument-tag">{{ $cfg['tag'] }}</span>
        <p class="doc-date-line">{{ $testDate }}</p>
        <p class="doc-schedule-line">Scheduled guidance appointment</p>
    </div>

    <!-- Beige / Sand Info Box (How to Answer + Time Remaining + Assessment Flow) -->
    <div class="beige-info-card">
        <div class="info-card-header">How to answer</div>
        <div class="info-card-instruction">{{ $cfg['instruction'] }}</div>

        <!-- Time Remaining Inner Box -->
        <div class="timer-inner-card">
            <div class="timer-label-text">Time Remaining</div>
            <div class="timer-countdown-digits" id="timerDigits">16:04</div>
            <p class="timer-helper-note">The answer sheet will close automatically when the 20-minute timer ends.</p>
        </div>

        <!-- Psychological Assessment Flow Stepper -->
        <div class="flow-section-title">Psychological Assessment Flow</div>
        <div class="flow-rows-list">
            @foreach($flow as $stepTest)
                @php
                    $stepLabel = strtoupper($stepTest);
                    $currentIndex = array_search($currentTest, $flow);
                    $stepIndex = array_search($stepTest, $flow);
                    
                    if ($stepIndex < $currentIndex) {
                        $statusClass = 'done';
                        $statusLabel = 'Done';
                    } elseif ($stepIndex === $currentIndex) {
                        $statusClass = 'current';
                        $statusLabel = 'Current';
                    } else {
                        $statusClass = 'pending';
                        $statusLabel = 'Pending';
                    }
                @endphp
                <div class="flow-row-item">
                    <span class="flow-test-name">{{ $stepLabel }}</span>
                    <span class="flow-status-text {{ $statusClass }}">{{ $statusLabel }}</span>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Respondent Profile Form Fields (2-Column Grid matching Screenshot) -->
    <form method="POST" action="{{ $submitUrl ?? route('guidance.submit', $token ?? '') }}" id="answerSheetForm">
        @csrf
        <input type="hidden" name="test_type" value="{{ $currentTest }}">

        <div class="respondent-form-grid">
            <div class="form-meta-group">
                <label class="form-meta-label" for="resp_name">Respondent Name</label>
                <input type="text" id="resp_name" class="form-meta-field" value="{{ $respName }}" readonly>
            </div>

            <div class="form-meta-group">
                <label class="form-meta-label" for="resp_student_id">Student ID Number</label>
                <input type="text" id="resp_student_id" class="form-meta-field font-monospace" value="{{ $studentId }}" readonly>
            </div>

            <div class="form-meta-group">
                <label class="form-meta-label" for="resp_or_number">O.R. #</label>
                <input type="text" name="or_number" id="resp_or_number" class="form-meta-field font-monospace" placeholder="Enter official receipt number" value="{{ $receiptNo }}">
            </div>

            <div class="form-meta-group">
                <label class="form-meta-label" for="resp_date">Date of Test</label>
                <input type="text" id="resp_date" class="form-meta-field" value="{{ $testDate }}" readonly>
            </div>

            <div class="form-meta-group">
                <label class="form-meta-label" for="resp_course">Course</label>
                <input type="text" id="resp_course" class="form-meta-field" value="{{ $course }}" readonly>
            </div>

            <div class="form-meta-group">
                <label class="form-meta-label" for="resp_section">Section</label>
                <input type="text" id="resp_section" class="form-meta-field" value="{{ $section }}" readonly>
            </div>

            <div class="form-meta-group">
                <label class="form-meta-label" for="resp_sex">Sex</label>
                <input type="text" id="resp_sex" class="form-meta-field" value="{{ $sex }}" readonly>
            </div>

            <div class="form-meta-group full-width">
                <label class="form-meta-label" for="resp_purpose">Purpose</label>
                <input type="text" id="resp_purpose" class="form-meta-field" value="{{ $purpose }}" readonly>
            </div>
        </div>

        <!-- Questions in 2-Column Grid (Desktop) -->
        <div class="questions-two-col-grid" id="questionsGrid">
            @for($i = 1; $i <= $itemCount; $i++)
                @php
                    $inputName = "answers[{$currentTest}][{$i}]";
                    $savedVal = old("answers.{$currentTest}.{$i}", $savedAnswers[$i] ?? null);
                @endphp

                <div class="question-module-card" id="qcard_{{ $i }}" data-question-num="{{ $i }}">
                    <h3 class="question-number-title">Question {{ $i }}</h3>
                    <p class="question-instructions-sub">Select the response that best matches the paper test item.</p>

                    <div class="choices-vertical-list">
                        @foreach($cfg['choices'] as $cVal => $cText)
                            @php
                                $isChecked = ($savedVal !== null && (string)$savedVal === (string)$cVal);
                                $radioId = "q_{$currentTest}_{$i}_{$cVal}";
                            @endphp
                            <label class="choice-item-label {{ $isChecked ? 'selected' : '' }}" for="{{ $radioId }}">
                                <input type="radio" 
                                       class="choice-radio-input" 
                                       name="{{ $inputName }}" 
                                       id="{{ $radioId }}" 
                                       value="{{ $cVal }}" 
                                       @checked($isChecked)
                                       required>
                                <span class="choice-text-span">{{ $cText }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endfor
        </div>

        <!-- Bottom Submit Button Aligned to the Right -->
        <div class="bottom-submit-wrapper">
            <button type="submit" class="btn-submit-action" id="submitBtn">
                {{ $cfg['submitText'] }}
            </button>
        </div>
    </form>
</main>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('answerSheetForm');
    if (!form) return;

    // ── Radio selection highlight styling ──
    form.querySelectorAll('.choice-radio-input').forEach(function (radio) {
        radio.addEventListener('change', function () {
            const card = this.closest('.question-module-card');
            if (card) {
                card.classList.remove('unanswered');
                card.querySelectorAll('.choice-item-label').forEach(function (lbl) {
                    lbl.classList.remove('selected');
                });
            }
            const currentLabel = this.closest('.choice-item-label');
            if (currentLabel) {
                currentLabel.classList.add('selected');
            }
        });
    });

    // ── Countdown Timer Simulation (20 minutes default matching screenshot) ──
    let remainingSeconds = 16 * 60 + 4; // 16:04 initial
    const timerDigits = document.getElementById('timerDigits');

    const timerInterval = setInterval(function () {
        if (remainingSeconds <= 0) {
            clearInterval(timerInterval);
            if (timerDigits) timerDigits.textContent = '00:00';
            return;
        }

        remainingSeconds--;
        const mins = Math.floor(remainingSeconds / 60);
        const secs = remainingSeconds % 60;
        const formatted = String(mins).padStart(2, '0') + ':' + String(secs).padStart(2, '0');

        if (timerDigits) timerDigits.textContent = formatted;
    }, 1000);

    // ── Form Validation ──
    form.addEventListener('submit', function (e) {
        const questionCards = form.querySelectorAll('.question-module-card');
        let firstMissing = null;

        questionCards.forEach(function (card) {
            const hasChecked = card.querySelector('input[type="radio"]:checked');
            if (!hasChecked) {
                card.classList.add('unanswered');
                if (!firstMissing) firstMissing = card;
            } else {
                card.classList.remove('unanswered');
            }
        });

        if (firstMissing) {
            e.preventDefault();
            firstMissing.scrollIntoView({ behavior: 'smooth', block: 'center' });
            const firstRadio = firstMissing.querySelector('input[type="radio"]');
            if (firstRadio) firstRadio.focus();
        }
    });
});
</script>

</body>
</html>
