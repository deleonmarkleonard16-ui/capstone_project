<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $testTitle ?? ($testMeta['title'] ?? 'Psychological Assessment') }} - Digital Management System for Guidance Testing and Admission (DMSGTA)</title>
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

        /* ── Anti-Screenshot, Anti-Selection & Content Protection ── */
        *, *::before, *::after {
            user-select: none !important;
            -webkit-user-select: none !important;
            -moz-user-select: none !important;
            -ms-user-select: none !important;
            -webkit-touch-callout: none !important;
        }

        input[type="text"], input[type="number"], textarea {
            user-select: text !important;
            -webkit-user-select: text !important;
        }

        @media print {
            html, body {
                display: none !important;
                visibility: hidden !important;
            }
        }

        /* ── Dynamic Screenshot & Screen-Record Blackout Masking Overlay ── */
        #security-blackout-mask {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background-color: #000000 !important;
            z-index: 999999 !important;
            display: none;
            pointer-events: all;
            opacity: 1;
            transition: none;
        }

        #security-blackout-mask.active {
            display: block !important;
        }

        .assessment-security-hidden #assessmentContentWrapper {
            visibility: hidden !important;
            opacity: 0 !important;
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
            background: transparent;
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
            position: relative;
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
            font-weight: 600;
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
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }

        .timer-label-text {
            font-size: 0.725rem;
            color: #6b7280;
            margin-bottom: 2px;
            text-transform: uppercase;
            font-weight: 600;
        }

        .timer-countdown-digits {
            font-size: 1.35rem;
            font-weight: 700;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            color: #111827;
            line-height: 1.1;
        }

        .timer-countdown-digits.warning {
            color: #dc2626 !important;
            animation: pulse-danger 1s infinite;
        }

        @keyframes pulse-danger {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.6; }
        }

        .timer-helper-note {
            font-size: 0.75rem;
            color: #9ca3af;
            margin: 0;
            width: 100%;
        }

        /* Security Badges in Header */
        .security-live-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 4px 10px;
            border-radius: 9999px;
            background-color: #ecfdf5;
            color: #047857;
            border: 1px solid #a7f3d0;
        }

        .security-live-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background-color: #10b981;
            animation: blink-dot 1.5s infinite;
        }

        @keyframes blink-dot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.3; transform: scale(0.8); }
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
            color: #16a34a;
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
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .form-meta-field-wrap {
            position: relative;
            display: flex;
            align-items: center;
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

        .copy-ref-btn {
            position: absolute;
            right: 6px;
            background: #ffffff;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            font-size: 0.7rem;
            padding: 2px 7px;
            color: #4b5563;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.15s ease;
        }

        .copy-ref-btn:hover {
            background: #f3f4f6;
            color: #1d4ed8;
            border-color: #93c5fd;
        }

        /* ── Questions 2-Column Grid (Desktop) ── */
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
            border-color: #fca5a5 !important;
            background-color: #fff9f9 !important;
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

        /* Choices Stack (Radio only) */
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
        .choice-item-label.selected,
        .choice-item-label:has(.choice-radio-input:checked) {
            background-color: var(--choice-checked-bg) !important;
            border-color: var(--choice-checked-border) !important;
        }

        .choice-item-label.selected .choice-text-span,
        .choice-item-label:has(.choice-radio-input:checked) .choice-text-span {
            color: #1d4ed8;
            font-weight: 600;
        }

        /* ── Bottom Submit Button Section ── */
        .bottom-submit-wrapper {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 14px;
            border-top: 1px solid #f1f5f9;
        }

        .btn-submit-action {
            background-color: #1d4ed8;
            color: #ffffff;
            font-size: 0.875rem;
            font-weight: 600;
            padding: 8px 22px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            box-shadow: 0 1px 3px rgba(29, 78, 216, 0.2);
            transition: all 0.15s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-submit-action:hover {
            background-color: #1e40af;
            box-shadow: 0 2px 6px rgba(30, 64, 175, 0.3);
        }

        .btn-submit-action:active {
            transform: scale(0.98);
        }

        /* ── Security Modal Overlay & Content ── */
        .security-modal-backdrop {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background: rgba(0, 0, 0, 0.85);
            backdrop-filter: blur(4px);
            z-index: 1000000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .security-modal-backdrop[hidden] {
            display: none !important;
        }

        .security-modal-card {
            background: #ffffff;
            border-radius: 14px;
            max-width: 520px;
            width: 100%;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            border: 2px solid #ef4444;
            animation: modal-pop 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes modal-pop {
            0% { transform: scale(0.92); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }

        .security-modal-header {
            background-color: #fee2e2;
            border-bottom: 1px solid #fecaca;
            padding: 14px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .security-modal-header.strike-2 {
            background-color: #fef2f2;
            border-bottom-color: #fca5a5;
        }

        .security-modal-title {
            font-size: 1.05rem;
            font-weight: 700;
            color: #991b1b;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .security-strike-badge {
            font-size: 0.75rem;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 9999px;
            background: #dc2626;
            color: #ffffff;
        }

        .security-modal-body {
            padding: 22px 24px 18px;
            color: #1f2937;
        }

        .security-modal-message {
            font-size: 0.95rem;
            line-height: 1.5;
            color: #374151;
            margin-bottom: 16px;
        }

        .security-incident-meta {
            background-color: #f9fafb;
            border: 1px dashed #d1d5db;
            border-radius: 8px;
            padding: 10px 14px;
            font-size: 0.8rem;
            color: #4b5563;
            margin-bottom: 18px;
        }

        .security-modal-footer {
            padding: 14px 24px 20px;
            display: flex;
            justify-content: center;
            border-top: 1px solid #f3f4f6;
        }

        .security-resume-btn {
            background-color: #dc2626;
            color: #ffffff;
            font-weight: 600;
            font-size: 0.925rem;
            padding: 10px 28px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            box-shadow: 0 2px 4px rgba(220, 38, 38, 0.3);
            transition: all 0.15s ease;
            width: 100%;
        }

        .security-resume-btn:hover {
            background-color: #b91c1c;
        }

        /* ── Termination Summary Container ── */
        .termination-summary-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            padding: 36px 32px;
            text-align: center;
            max-width: 600px;
            margin: 40px auto;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
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

            .bottom-submit-wrapper {
                flex-direction: column;
                gap: 10px;
            }

            .btn-submit-action {
                width: 100%;
                padding: 10px 16px;
                font-size: 0.925rem;
                justify-content: center;
            }
        }
    </style>
</head>
<body oncontextmenu="return false;">

<!-- ═══ 1. INSTANT SOLID BLACKOUT MASK OVERLAY (z-index: 999999) ═══ -->
<div id="security-blackout-mask" aria-hidden="true"></div>

<!-- ═══ 2. PROGRESSIVE WARNING MODAL (z-index: 1000000) ═══ -->
<div id="securityWarningModalBackdrop" class="security-modal-backdrop" hidden role="dialog" aria-modal="true">
    <div class="security-modal-card" id="securityModalCard">
        <div class="security-modal-header" id="securityModalHeader">
            <h3 class="security-modal-title" id="securityModalTitle">⚠️ WARNING (Strike 1/2)</h3>
            <span class="security-strike-badge" id="securityModalBadge">Strike 1 of 2</span>
        </div>
        <div class="security-modal-body">
            <p class="security-modal-message" id="securityModalMessage">
                Screenshots, screen recordings, and navigating away are strictly prohibited during this assessment. Your screen was blacked out and this incident has been logged and sent to the Guidance Office.
            </p>
            <div class="security-incident-meta">
                <div><strong>Incident Type:</strong> <span id="securityIncidentTypeLabel">Screen Capture Attempt</span></div>
                <div><strong>Timestamp:</strong> <span id="securityIncidentTimeLabel">Just now</span></div>
                <div><strong>Status:</strong> <span class="text-danger fw-semibold">Dispatched to Admin Proctoring Live Dashboard</span></div>
            </div>
        </div>
        <div class="security-modal-footer">
            <button type="button" class="security-resume-btn" id="securityModalActionBtn">Resume Assessment</button>
        </div>
    </div>
</div>

@php
    // Detect active test type (dass21, phq9, gad7, bfpi)
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
    $appointmentId = $appointment->guidance_appointment_id ?? ($appointment->id ?? 1);

    // Metadata per instrument matching the standard PSU guidance tests
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

<div id="assessmentContentWrapper">
    <!-- ═══ TOP BRAND BAR ═══ -->
    <div class="brand-header-bar">
        <img src="{{ asset('images/psu-logo.png') }}" alt="PSU Logo" class="brand-logo-img" onerror="this.style.display='none'">
        <div class="brand-text-block">
            <h2 class="brand-title-text">Pangasinan State University - San Carlos Campus</h2>
            <p class="brand-sub-text">Digital Management System for Guidance Testing and Admission (DMSGTA)</p>
        </div>
    </div>

    <!-- ═══ MAIN DIGITAL ANSWER SHEET CONTAINER ═══ -->
    <main class="sheet-outer-card">
        <!-- Document Title Header -->
        <div class="doc-title-section">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                <div>
                    <h1 class="doc-main-title">{{ $cfg['title'] }}</h1>
                    <p class="doc-meta-subtitle">Psychological Test | {{ strtoupper($respName) }} | Student ID Number <span class="font-monospace fw-semibold">{{ $studentId }}</span></p>
                    <span class="doc-instrument-tag">{{ $cfg['tag'] }}</span>
                </div>
                <div class="security-live-badge" title="Active anti-screenshot and proctoring shield">
                    <span class="security-live-dot"></span>
                    <span>Proctoring Shield Active</span>
                </div>
            </div>
            <p class="doc-date-line">{{ $testDate }}</p>
            <p class="doc-schedule-line">Scheduled guidance appointment</p>
        </div>

        <!-- Beige / Sand Info Box (How to Answer + Time Remaining + Assessment Flow) -->
        <div class="beige-info-card">
            <div class="info-card-header">How to answer</div>
            <div class="info-card-instruction">{{ $cfg['instruction'] }}</div>

            <!-- 40-Minute Global Session Timer Inner Box -->
            <div class="timer-inner-card">
                <div>
                    <div class="timer-label-text">Time Remaining</div>
                    <div class="timer-countdown-digits" id="timerDigits">40:00</div>
                </div>
                <div class="d-none d-sm-block text-end">
                    <span class="badge bg-light text-dark border"><i class="bi bi-shield-lock-fill text-primary me-1"></i> Session Guarded</span>
                </div>
                <p class="timer-helper-note">The answer sheet will automatically submit answers when the 40-minute session timer expires.</p>
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

        <!-- Respondent Profile Form Fields (2-Column Grid with Auto-Copy Reference) -->
        <form method="POST" action="{{ $submitUrl ?? route('guidance.submit', $token ?? '') }}" id="assessmentForm" data-appointment-id="{{ $appointmentId }}">
            @csrf
            <input type="hidden" name="test_type" value="{{ $currentTest }}">

            <div class="respondent-form-grid">
                <div class="form-meta-group">
                    <label class="form-meta-label" for="resp_name">Respondent Name</label>
                    <input type="text" id="resp_name" class="form-meta-field" value="{{ $respName }}" readonly>
                </div>

                <div class="form-meta-group">
                    <label class="form-meta-label" for="resp_student_id">
                        <span>Student ID Number</span>
                        <button type="button" class="copy-ref-btn" data-copy-target="resp_student_id" title="Copy Student ID">
                            <i class="bi bi-clipboard"></i> Copy
                        </button>
                    </label>
                    <div class="form-meta-field-wrap">
                        <input type="text" id="resp_student_id" class="form-meta-field font-monospace" value="{{ $studentId }}" readonly>
                    </div>
                </div>

                <div class="form-meta-group">
                    <label class="form-meta-label" for="resp_or_number">
                        <span>O.R. # / Reference</span>
                        <button type="button" class="copy-ref-btn" data-copy-target="resp_or_number" title="Copy Reference">
                            <i class="bi bi-clipboard"></i> Copy
                        </button>
                    </label>
                    <div class="form-meta-field-wrap">
                        <input type="text" name="or_number" id="resp_or_number" class="form-meta-field font-monospace" placeholder="Enter official receipt number" value="{{ $receiptNo }}">
                    </div>
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

            <!-- Questions in 2-Column Grid (Desktop) - Radio choices only -->
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
                <span class="text-muted small"><i class="bi bi-info-circle me-1"></i> Ensure all questions are answered before continuing.</span>
                <button type="submit" class="btn-submit-action" id="submitBtn">
                    <span>{{ $cfg['submitText'] }}</span>
                    <i class="bi bi-arrow-right-short fs-5"></i>
                </button>
            </div>
        </form>
    </main>
</div>

<!-- ═══ 3. TEST-TERMINATED SUMMARY CONTAINER (Initially Hidden) ═══ -->
<div id="testTerminatedContainer" hidden>
    <div class="termination-summary-card">
        <div class="mb-3">
            <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-danger bg-opacity-10" style="width:72px;height:72px;">
                <i class="bi bi-exclamation-octagon-fill text-danger" style="font-size:2.5rem;"></i>
            </span>
        </div>
        <h2 class="h4 fw-bold text-danger mb-2">Assessment Terminated</h2>
        <p class="text-muted mb-4">
            The assessment was automatically ended due to repeated security protocol violations (3 strikes accumulated for screenshot/screen capture/navigation attempts). Your saved answers have been submitted for review.
        </p>
        <div class="alert alert-secondary text-start small mb-4">
            <strong>Incident Summary:</strong>
            <ul class="mb-0 ps-3 mt-1">
                <li>Violation: Prohibited Screenshot / Screen Record / Tab-Switch Attempts</li>
                <li>Action Taken: Session DOM cleared &amp; responses locked</li>
                <li>Status: Reported to the Guidance Office Proctoring Dashboard</li>
            </ul>
        </div>
        <a href="{{ route('portal.index') }}" class="btn btn-primary px-4">Return to Student Portal</a>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('assessmentForm');
    const blackoutMask = document.getElementById('security-blackout-mask');
    const warningModalBackdrop = document.getElementById('securityWarningModalBackdrop');
    const modalTitle = document.getElementById('securityModalTitle');
    const modalBadge = document.getElementById('securityModalBadge');
    const modalMessage = document.getElementById('securityModalMessage');
    const modalHeader = document.getElementById('securityModalHeader');
    const modalActionBtn = document.getElementById('securityModalActionBtn');
    const incidentTypeLabel = document.getElementById('securityIncidentTypeLabel');
    const incidentTimeLabel = document.getElementById('securityIncidentTimeLabel');
    const contentWrapper = document.getElementById('assessmentContentWrapper');
    const terminatedContainer = document.getElementById('testTerminatedContainer');

    let strikeCount = 0;
    let isTerminated = false;
    let isSubmitting = false;
    let blackoutTimer = null;
    let lastIncidentTime = 0;
    const appointmentId = form?.dataset.appointmentId || '{{ $appointmentId }}';

    // ── 1. DYNAMIC SCREENSHOT & SCREEN-RECORD BLACKOUT MASKING ──
    function triggerBlackout(durationMs = 1500) {
        if (blackoutMask) {
            blackoutMask.classList.add('active');
            document.documentElement.classList.add('assessment-security-hidden');
            clearTimeout(blackoutTimer);
            blackoutTimer = setTimeout(function () {
                if (!document.hidden && document.hasFocus() && !isTerminated) {
                    blackoutMask.classList.remove('active');
                    document.documentElement.classList.remove('assessment-security-hidden');
                }
            }, durationMs);
        }
    }

    function removeBlackout() {
        if (!isTerminated && blackoutMask) {
            blackoutMask.classList.remove('active');
            document.documentElement.classList.remove('assessment-security-hidden');
        }
    }

    // ── 2. REAL-TIME ADMIN PROCTORING INCIDENT LOGGING (AJAX SILENT DISPATCH) ──
    function dispatchStrikeLog(incidentType, strikeNum) {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ||
                          document.querySelector('input[name="_token"]')?.value;

        fetch('/api/test/log-strike', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({
                appointment_id: appointmentId,
                incident_type: incidentType || 'Screenshot / Screen Record Attempt',
                strike_number: strikeNum
            })
        }).catch(function (err) {
            console.warn('Strike log sync error:', err);
        });
    }

    // ── 3. PROGRESSIVE WARNING MODAL SYSTEM (2-STRIKE RULE) ──
    function handleSecurityViolation(incidentType) {
        if (isTerminated || isSubmitting) return;

        const now = Date.now();
        // Debounce rapid successive events (e.g. key repeat + blur)
        if (now - lastIncidentTime < 1000) return;
        lastIncidentTime = now;

        // Instantly trigger solid blackout mask
        triggerBlackout(2000);

        strikeCount++;
        dispatchStrikeLog(incidentType, strikeCount);

        const timeStr = new Date().toLocaleTimeString();
        if (incidentTypeLabel) incidentTypeLabel.textContent = incidentType;
        if (incidentTimeLabel) incidentTimeLabel.textContent = timeStr;

        if (strikeCount === 1) {
            // Strike 1 Warning Modal
            if (modalTitle) modalTitle.textContent = '⚠️ WARNING (Strike 1/2)';
            if (modalBadge) modalBadge.textContent = 'Strike 1 of 2';
            if (modalMessage) {
                modalMessage.textContent = 'Screenshots, screen recordings, and navigating away are strictly prohibited during this assessment. Your screen was blacked out and this incident has been logged and sent to the Guidance Office.';
            }
            if (modalHeader) modalHeader.className = 'security-modal-header';
            if (modalActionBtn) modalActionBtn.textContent = 'Resume Assessment';
            if (warningModalBackdrop) warningModalBackdrop.hidden = false;
        } else if (strikeCount === 2) {
            // Strike 2 Critical Warning Modal
            if (modalTitle) modalTitle.textContent = '🔴 CRITICAL WARNING (Strike 2/2)';
            if (modalBadge) modalBadge.textContent = 'Strike 2 of 2';
            if (modalMessage) {
                modalMessage.textContent = 'You attempted to capture or leave the screen again. Any further violation will result in immediate exam termination and automatic submission of your answers.';
            }
            if (modalHeader) modalHeader.className = 'security-modal-header strike-2';
            if (modalActionBtn) modalActionBtn.textContent = 'Return to Assessment';
            if (warningModalBackdrop) warningModalBackdrop.hidden = false;
        } else if (strikeCount >= 3) {
            // Strike 3 Forced Auto-Submission & Termination
            terminateAndAutoSubmit();
        }
    }

    function terminateAndAutoSubmit() {
        if (isTerminated) return;
        isTerminated = true;
        isSubmitting = true;

        if (warningModalBackdrop) warningModalBackdrop.hidden = true;
        if (blackoutMask) blackoutMask.classList.remove('active');
        document.documentElement.classList.remove('assessment-security-hidden');

        // Release fullscreen mode if active
        if (document.fullscreenElement && document.exitFullscreen) {
            document.exitFullscreen().catch(function () {});
        }

        // Clear session DOM and reveal termination summary
        if (contentWrapper) contentWrapper.hidden = true;
        if (terminatedContainer) terminatedContainer.hidden = false;

        // Trigger forced automatic submission
        if (form) {
            const formData = new FormData(form);
            formData.set('terminated_for_violation', '1');
            
            fetch(form.getAttribute('action'), {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                },
                body: formData
            }).catch(function () {});
        }
    }

    if (modalActionBtn) {
        modalActionBtn.addEventListener('click', function () {
            if (warningModalBackdrop) warningModalBackdrop.hidden = true;
            removeBlackout();
            // Re-focus assessment
            window.focus();
        });
    }

    // ── 4. SCREEN RECORDING API INTERCEPTION ──
    if (navigator.mediaDevices && navigator.mediaDevices.getDisplayMedia) {
        try {
            navigator.mediaDevices.getDisplayMedia = function () {
                triggerBlackout(3000);
                handleSecurityViolation('Screenshot / Screen Record Attempt');
                return Promise.reject(new DOMException('Screen recording is prohibited.', 'NotAllowedError'));
            };
        } catch (_) {}
    }

    // ── 5. EVENT LISTENERS FOR SCREENSHOTS, WINDOW BLUR, TAB SWITCH, AND SHORTCUTS ──
    // Visibility Change / Tab Switching
    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            triggerBlackout(2500);
            handleSecurityViolation('Tab Switch / Window Hidden');
        } else {
            removeBlackout();
        }
    });

    // Window Focus Loss
    window.addEventListener('blur', function () {
        triggerBlackout(2000);
        handleSecurityViolation('Focus Loss / App Switch');
    });

    window.addEventListener('focus', function () {
        removeBlackout();
    });

    // Keyboard Shortcuts Detection (PrintScreen, Win+Shift+S, Cmd+Shift+4, Ctrl+P, DevTools, etc.)
    window.addEventListener('keydown', function (e) {
        const key = e.key ? e.key.toLowerCase() : '';
        const isCmdOrCtrl = e.metaKey || e.ctrlKey;
        const isShift = e.shiftKey;
        const isAlt = e.altKey;

        // PrintScreen Key
        if (key === 'printscreen' || e.keyCode === 44) {
            e.preventDefault();
            triggerBlackout(3000);
            handleSecurityViolation('Screenshot Attempt (PrintScreen)');
            return false;
        }

        // Mac Screen Capture Shortcuts (Cmd + Shift + 3 / 4 / 5 / 6)
        if (e.metaKey && isShift && ['3', '4', '5', '6', '$', '#', '%', '^'].includes(key)) {
            e.preventDefault();
            triggerBlackout(3000);
            handleSecurityViolation('Screenshot Attempt (Cmd+Shift+4)');
            return false;
        }

        // Windows Snipping Shortcuts (Win/Ctrl + Shift + S)
        if (isCmdOrCtrl && isShift && key === 's') {
            e.preventDefault();
            triggerBlackout(3000);
            handleSecurityViolation('Screenshot Attempt (Snipping Tool)');
            return false;
        }

        // Alt + PrintScreen
        if (isAlt && (key === 'printscreen' || e.keyCode === 44)) {
            e.preventDefault();
            triggerBlackout(3000);
            handleSecurityViolation('Screenshot Attempt (Alt+PrintScreen)');
            return false;
        }

        // Print Shortcut (Ctrl + P / Cmd + P)
        if (isCmdOrCtrl && key === 'p') {
            e.preventDefault();
            triggerBlackout(3000);
            handleSecurityViolation('Print Attempt (Ctrl+P)');
            return false;
        }

        // Save Shortcut (Ctrl + S / Cmd + S)
        if (isCmdOrCtrl && key === 's' && !isShift) {
            e.preventDefault();
            return false;
        }

        // DevTools (F12, Ctrl+Shift+I, Ctrl+Shift+J, Ctrl+Shift+C)
        if (key === 'f12' || (isCmdOrCtrl && isShift && ['i', 'j', 'c'].includes(key))) {
            e.preventDefault();
            handleSecurityViolation('Developer Tools Access Attempt');
            return false;
        }
    }, true);

    window.addEventListener('keyup', function (e) {
        const key = e.key ? e.key.toLowerCase() : '';
        if (key === 'printscreen' || e.keyCode === 44) {
            triggerBlackout(3000);
            handleSecurityViolation('Screenshot Attempt (PrintScreen KeyUp)');
        }
    }, true);

    // Prevent Copy / Cut / Drag / Context Menu
    ['copy', 'cut', 'dragstart'].forEach(function (evt) {
        document.addEventListener(evt, function (e) {
            e.preventDefault();
            return false;
        });
    });

    // ── 6. AUTO-COPY TRACKING REFERENCE FEATURE ──
    document.querySelectorAll('.copy-ref-btn').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            const targetId = this.dataset.copyTarget;
            const targetInput = document.getElementById(targetId);
            if (targetInput && targetInput.value) {
                navigator.clipboard.writeText(targetInput.value).then(function () {
                    const originalHtml = btn.innerHTML;
                    btn.innerHTML = '<i class="bi bi-check-lg text-success"></i> Copied!';
                    setTimeout(function () {
                        btn.innerHTML = originalHtml;
                    }, 2000);
                }).catch(function () {
                    targetInput.select();
                    document.execCommand('copy');
                });
            }
        });
    });

    // ── 7. 40-MINUTE GLOBAL SESSION COUNTDOWN TIMER ──
    let remainingSeconds = 40 * 60; // 40 minutes (2400 seconds)
    const timerDigits = document.getElementById('timerDigits');

    const timerInterval = setInterval(function () {
        if (isTerminated) {
            clearInterval(timerInterval);
            return;
        }

        if (remainingSeconds <= 0) {
            clearInterval(timerInterval);
            if (timerDigits) timerDigits.textContent = '00:00';
            alert('Your 40-minute session time has expired. Submitting your answers now.');
            if (form) form.submit();
            return;
        }

        remainingSeconds--;
        const mins = Math.floor(remainingSeconds / 60);
        const secs = remainingSeconds % 60;
        const formatted = String(mins).padStart(2, '0') + ':' + String(secs).padStart(2, '0');

        if (timerDigits) {
            timerDigits.textContent = formatted;
            if (remainingSeconds <= 300) { // Under 5 minutes
                timerDigits.classList.add('warning');
            }
        }
    }, 1000);

    // ── 8. RADIO SELECTION HIGHLIGHT & STEP VALIDATION ──
    if (form) {
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

        // Form Validation on Submit
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
            } else {
                // Scroll to top smoothly upon completing steps
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        });
    }
});
</script>

</body>
</html>
