<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>PSU-CAT Digital Exam – {{ $applicant->application_number }}</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { height: 100%; background-color: #ffffff !important; color: #111827; font-family: Arial, Helvetica, sans-serif; }
        body { user-select: none; -webkit-user-select: none; overflow: hidden; }

        /* ── FORCED FULLSCREEN KIOSK LOCKDOWN MODAL ── */
        #kiosk-lock {
            position: fixed; inset: 0; z-index: 9999;
            background-color: #ffffff !important;
            display: block; text-align: center;
            overflow-y: auto; overflow-x: hidden;
            touch-action: pan-y pinch-zoom; -webkit-overflow-scrolling: touch;
            overscroll-behavior-y: contain;
            transition: opacity 0.3s;
        }
        #kiosk-lock.hidden { display: none; }
        #kiosk-lock .kiosk-details {
            width: 100%; max-width: 744px; margin: 0 auto; padding: 24px;
        }
        #kiosk-lock .kiosk-actions {
            position: sticky; bottom: 0; background: #fff; z-index: 1;
            padding: 12px 24px max(16px, env(safe-area-inset-bottom));
            border-top: 1px solid #e2e8f0;
        }
        #kiosk-lock .kiosk-details, #exam-shell { touch-action: pan-y pinch-zoom; -webkit-overflow-scrolling: touch; }
        #kiosk-lock .kiosk-details .instructions { margin-left: auto; margin-right: auto; }
        #kiosk-lock #enter-btn { max-width: 100%; }
        #kiosk-lock .psu-logo { font-size: 15px; text-transform: uppercase; letter-spacing: 2px; color: #92400e; margin-bottom: 8px; }
        #kiosk-lock h1 { font-size: 28px; font-weight: 800; color: #111827; margin-bottom: 6px; }
        #kiosk-lock .sub { color: #475569; font-size: 14px; margin-bottom: 24px; }
        #kiosk-lock .applicant-badge { background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 12px; padding: 12px 24px; margin-bottom: 20px; }
        #kiosk-lock .applicant-badge .name { font-size: 20px; font-weight: 700; color: #111827; }
        #kiosk-lock .applicant-badge .appno { font-size: 13px; color: #92400e; font-family: monospace; margin-top: 4px; }
        #kiosk-lock .instructions { font-size: 13px; color: #334155; max-width: 560px; margin-bottom: 24px; line-height: 1.6; }
        .security-notice { max-width: 680px; margin-bottom: 20px; padding: 16px; border: 2px solid #dc2626; background: #fef2f2; color: #991b1b; font-weight: 800; line-height: 1.5; }
        #kiosk-lock .instructions ul { text-align: left; margin: 8px 0 0 0; padding-left: 18px; }
        #kiosk-lock .instructions ul li { margin-bottom: 4px; }
        #enter-btn { background: #ffd700; color: #0d1b3e; border: 0; padding: 14px 40px; font-size: 17px; font-weight: 800; border-radius: 8px; cursor: pointer; letter-spacing: 0.5px; transition: transform 0.1s, box-shadow 0.1s; }
        #enter-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(255,215,0,0.4); }
        #enter-btn:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
        #lock-msg { font-size: 13px; color: #ff9090; margin-top: 14px; min-height: 20px; }

        /* ── EXAM SHELL (hidden until fullscreen) ── */
        #exam-shell { display: none; max-width: 960px; height: 100%; height: 100dvh; overflow-y: auto; overflow-x: hidden; overscroll-behavior-y: contain; margin: 0 auto; padding: 20px; background-color: #ffffff !important; }
        #exam-shell.visible { display: block; }

        /* Sticky header */
        #exam-header { position: sticky; top: 0; z-index: 100; background: #ffffff; border-bottom: 2px solid #cbd5e1; padding: 12px 0; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; }
        #exam-header .left { font-size: 13px; color: #a8c0e8; }
        #exam-header .left strong { color: #fff; font-size: 15px; display: block; }
        #timer { background: #1e3a7c; border-radius: 8px; padding: 8px 20px; font-size: 22px; font-weight: 800; font-family: monospace; color: #ffd700; }
        #timer.danger { background: #7c1e1e; color: #ff6060; animation: pulse 1s infinite; }
        @keyframes pulse { 0%,100% { opacity: 1; } 50% { opacity: 0.6; } }

        /* Admission answer sheet: one full-width item card per vertical row. */
        .exam-item-list { display: flex; flex-direction: column; gap: 12px; width: 100%; min-width: 0; }
        .question-card { width: 100%; background: #111e45; border-radius: 12px; padding: 14px; display: flex; align-items: center; justify-content: space-between; gap: 16px; border: 1.5px solid #2a4580; transition: border-color 0.2s, background 0.2s; }
        .question-card:hover { border-color: #2a4580; }
        .question-card.answered { border-color: #1e5c3e; background: #0e2a1e; }
        .q-num { font-size: 14px; font-weight: 700; color: #e0e8f8; min-width: 88px; font-family: monospace; }
        .choices { display: flex; flex-wrap: nowrap; gap: 10px; }
        .choice-label { cursor: pointer; display: flex; align-items: center; justify-content: center; min-width: 48px; min-height: 42px; padding: 10px 16px; font-size: 14px; font-weight: 700; border-radius: 8px; border: 1.5px solid #2a4580; color: #c8d8f0; transition: all 0.15s; }
        .choice-label:hover { border-color: #4a7be0; color: #fff; background: #1e3060; }
        .choice-label input[type="radio"] { display: none; }
        .choice-label input[type="radio"]:checked + span { font-weight: 800; }
        .choice-label:has(input:checked) { background: #dbeafe; border-color: #3b82f6; color: #102a63; }
        @media (max-width: 575.98px) {
            #exam-shell { padding: 14px; }
            .question-card { align-items: center; flex-direction: row; gap: 8px; }
            .q-num { min-width: 0; flex-shrink: 0; font-size: 12px; }
            .choices { min-width: 0; flex: 1; justify-content: space-between; gap: 8px; }
            .choice-label { flex: 1 1 0; min-width: 0; padding: 10px 8px; }
        }

        /* Submit bar */
        #submit-bar { text-align: center; padding: 28px; margin-top: 20px; }
        #submit-btn { background: #16a34a; color: #fff; border: 0; padding: 16px 48px; font-size: 18px; font-weight: 800; border-radius: 8px; cursor: pointer; letter-spacing: 0.5px; }
        #submit-btn:hover { background: #15803d; }
        #submit-btn:disabled { opacity: 0.5; cursor: not-allowed; }
        #answered-count { font-size: 13px; color: #a8c0e8; margin-bottom: 12px; }

        /* ── BLACKOUT SCREEN ── */
        #blackout { position: fixed; inset: 0; z-index: 9000; background: #000; display: none; align-items: center; justify-content: center; flex-direction: column; }
        #blackout.active { display: flex; }
        html.exam-protected #exam-shell,
        html.exam-protected #exam-watermark { visibility: hidden !important; }
        html.exam-protected, html.exam-protected body { background: #000 !important; }
        html.exam-away #strike-modal,
        html.exam-away #blackout > * { visibility: hidden !important; }
        #blackout .msg { color: #fff; font-size: 22px; font-weight: 700; margin-bottom: 8px; }
        #blackout .sub { color: #888; font-size: 14px; }

        /* ── STRIKE WARNING MODAL ── */
        #strike-modal { position: fixed; inset: 0; z-index: 9500; background: rgba(0,0,0,0.85); display: none; align-items: center; justify-content: center; }
        #strike-modal.active { display: flex; }
        #strike-modal-inner { background: #fff; color: #111; border-radius: 12px; padding: 32px; max-width: 440px; max-height: 100%; overflow-y: auto; touch-action: pan-y pinch-zoom; text-align: center; }
        #strike-modal-inner h2 { font-size: 22px; font-weight: 800; margin-bottom: 8px; }
        #strike-modal-inner h2.s1 { color: #b45309; }
        #strike-modal-inner h2.s2 { color: #c2410c; }
        #strike-modal-inner h2.s3 { color: #dc2626; }
        #strike-badge { display: inline-block; margin-bottom: 12px; padding: 4px 14px; border-radius: 999px; font-size: 12px; font-weight: 700; text-transform: uppercase; }
        .s1-badge { background: #fef3c7; color: #92400e; }
        .s2-badge { background: #ffedd5; color: #9a3412; }
        .s3-badge { background: #fee2e2; color: #991b1b; }
        #strike-modal-inner p { font-size: 14px; color: #555; margin-bottom: 20px; line-height: 1.6; }
        #strike-continue { background: #0d1b3e; color: #fff; border: 0; padding: 12px 32px; border-radius: 8px; font-size: 15px; font-weight: 700; cursor: pointer; }
        @media print { body { visibility: hidden; } }
        .question-card { background-color: #ffffff !important; color: #111827; border-color: #cbd5e1; }
        .q-num, #exam-header .left strong { color: #111827; }
        #exam-header .left, #answered-count { color: #475569; }
        .choice-label { color: #334155; }
        #exam-header { gap: 8px; flex-wrap: wrap; }

        /* Personalized deterrent watermark. It remains above the answer sheet
           in captured images, but never intercepts exam touches/clicks. */
        #exam-watermark {
            position: fixed; inset: 0; z-index: 90; pointer-events: none;
            display: grid; grid-template-columns: repeat(3, 1fr);
            grid-template-rows: repeat(4, 1fr); overflow: hidden;
            opacity: 0.16; transform: rotate(-24deg) scale(1.25);
            transform-origin: center; user-select: none; -webkit-user-select: none;
        }
        #exam-watermark span {
            display: flex; align-items: center; justify-content: center;
            color: #0d1b3e; font-size: clamp(13px, 2.3vw, 25px);
            font-weight: 800; letter-spacing: .06em; text-transform: uppercase;
            white-space: nowrap;
        }
    </style>
</head>
<body>

{{-- ══════════════════════════════════════════════════════════
     FORCED FULLSCREEN KIOSK LOCKDOWN MODAL
     Rendered on page load, hidden only after fullscreen entry
     ══════════════════════════════════════════════════════════ --}}
<div id="kiosk-lock">
    <div class="kiosk-details">
    <div class="psu-logo">Pangasinan State University · San Carlos Campus</div>
    <h1>PSU-CAT Digital Exam</h1>
    <div class="sub">PSU College Admission Test · Official Digital Answer Sheet</div>

    <div class="applicant-badge">
        <div class="name">{{ mb_strtoupper($applicant->full_name) }}</div>
        <div class="appno">{{ $applicant->application_number }} · {{ $applicant->course_choice }}</div>
    </div>

    <div class="security-notice" role="alert">
        SECURITY NOTICE: Screenshots and screen recording are prohibited. Detectable screenshot shortcuts, focus loss, tab changes, and fullscreen exits trigger warnings and admin reports. Some phone screenshots and system overlays do not send browser signals and cannot be detected by this website.
    </div>

    <div class="instructions">
        <strong>Before you begin, read carefully:</strong>
        <ul>
            <li>The exam will run in <strong>mandatory fullscreen kiosk mode</strong>.</li>
            <li>Only the answer sheet may remain on screen. Exiting fullscreen, switching tabs/apps, navigating away, or losing window focus counts as a <strong>security strike</strong>.</li>
            <li>Using Print Screen, Ctrl+P, or similar keys is <strong>prohibited</strong>.</li>
            <li>Use one finger to answer or scroll. Gestures using <strong>three or more fingers</strong> are restricted and count as a security strike when detected.</li>
            <li>3 strikes result in <strong>automatic exam submission</strong>.</li>
            <li>There are <strong>{{ $totalItems }} items</strong>. Choose A, B, C, or D for each item.</li>
        </ul>
    </div>

    </div>
    <div class="kiosk-actions">
    <button id="enter-btn" type="button">
        Enable Fullscreen
    </button>
    <div id="lock-msg"></div>
    </div>
</div>

{{-- ══════════════════════════════════════════════════════════
     EXAM SHELL (hidden until fullscreen kiosk is entered)
     ══════════════════════════════════════════════════════════ --}}
<div id="exam-shell">
    <div id="exam-header">
        <div class="left">
            <strong>PSU-CAT Exam · {{ $applicant->application_number }}</strong>
            {{ mb_strtoupper($applicant->full_name) }} · {{ $applicant->course_choice }}
            <div>Student ID: {{ $applicant->student_id ?: $applicant->application_number }}</div>
        </div>
        <div id="timer">00:00</div>
    </div>

    <div id="answered-count">Answered: <span id="ans-count">0</span> / {{ $totalItems }}</div>

    @php
        $savedAnswers = is_array($applicant->answers) ? $applicant->answers : (json_decode($applicant->answers, true) ?: []);
    @endphp

    <form id="exam-form">
        @csrf
        <div class="exam-item-list flex flex-col gap-3" aria-label="Admission exam answers">
            @for($i = 1; $i <= $totalItems; $i++)
                <div class="question-card {{ !empty($savedAnswers[$i]) ? 'answered' : '' }}" id="qcard-{{ $i }}" data-item="{{ $i }}">
                    <div class="q-num">Item #{{ str_pad($i, 2, '0', STR_PAD_LEFT) }}</div>
                    <div class="choices" role="radiogroup" aria-label="Answer for item {{ $i }}">
                        @foreach(['A','B','C','D'] as $letter)
                        <label class="choice-label" for="a{{ $i }}_{{ $letter }}">
                            <input type="radio" name="answers[{{ $i }}]" id="a{{ $i }}_{{ $letter }}" value="{{ $letter }}" {{ ($savedAnswers[$i] ?? null) === $letter ? 'checked' : '' }}>
                            <span>{{ $letter }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>
            @endfor
        </div>
    </form>

    <div id="submit-bar">
        <button id="submit-btn" type="button" disabled>Submit Exam</button>
    </div>
</div>

{{-- ── BLACKOUT SCREEN (screenshot / focus-loss protection) ── --}}
{{-- Name-only watermark: screenshots remain attributable without showing an ID. --}}
<div id="exam-watermark" aria-hidden="true">
    @for($watermark = 0; $watermark < 12; $watermark++)
        <span>{{ $applicant->full_name }}</span>
    @endfor
</div>

<div id="blackout">
    <div class="msg">⛔ Screen Protected</div>
    <div class="sub">Return focus to the exam window to continue.</div>
</div>

{{-- ── PROGRESSIVE STRIKE WARNING MODAL ── --}}
<div id="strike-modal">
    <div id="strike-modal-inner">
        <span id="strike-badge"></span>
        <h2 id="strike-title"></h2>
        <p id="strike-body"></p>
        <button id="strike-continue" type="button">I Understand – Continue Exam</button>
    </div>
</div>

<script src="{{ asset('js/admission-focus-guard.js') }}?v={{ filemtime(public_path('js/admission-focus-guard.js')) }}"></script>
<script>
const SUBMIT_URL      = @json(route('admission.submit', $token));
// Admission security events must go to the admission endpoint.  The guidance
// endpoint expects a guidance appointment and therefore cannot record an
// admission examinee's screenshot attempt.
const STRIKE_URL      = @json(route('admission.strike', $token));
const COMPLETE_URL    = @json(route('admission.complete'));
const TERMINATED_URL  = @json(route('admission.terminated', ['token' => $token]));
const CSRF            = document.querySelector('meta[name="csrf-token"]').content;
const INITIAL_STRIKES = {{ (int)$applicant->strike_count }};
const TOTAL_ITEMS     = {{ (int)$totalItems }};

const kioskLock    = document.getElementById('kiosk-lock');
const examShell    = document.getElementById('exam-shell');
const enterBtn     = document.getElementById('enter-btn');
const lockMsg      = document.getElementById('lock-msg');
const blackout     = document.getElementById('blackout');
const strikeModal  = document.getElementById('strike-modal');
const timer        = document.getElementById('timer');
const submitBtn    = document.getElementById('submit-btn');
const ansCount     = document.getElementById('ans-count');

let started        = false;
let sending        = false;
let confirmingSubmission = false;
let strikes        = INITIAL_STRIKES;
let pendingIncident = false;
let securityLockActive = false;
const EXAM_DURATION_SECONDS = 3600; // 60-minute default
let totalSecs      = EXAM_DURATION_SECONDS;
let timerInterval  = null;
let deadlineAt      = null;

// ── ANSWER COLLECTION ───────────────────────────────────────
function collectAnswers() {
    const answers = {};
    document.querySelectorAll('#exam-form input[type="radio"]:checked').forEach(input => {
        const m = input.name.match(/answers\[(\d+)\]/);
        if (m) answers[m[1]] = input.value;
    });
    return answers;
}

function updateAnswerCount() {
    const n = Object.keys(collectAnswers()).length;
    ansCount.textContent = n;
    document.querySelectorAll('.question-card').forEach(card => {
        const item = card.dataset.item;
        const checked = card.querySelector('input:checked');
        card.classList.toggle('answered', !!checked);
    });
    // Unanswered questions are valid blank answers.  An examinee may submit
    // whenever they choose; the server scores only the choices received.
    submitBtn.disabled = (!started || sending);
}

document.getElementById('exam-form').addEventListener('change', updateAnswerCount);

// ── TIMER ──────────────────────────────────────────────────
function startTimer() {
    // Calculate against a fixed deadline instead of counting intervals.  This
    // prevents background-tab timer throttling from giving extra exam time.
    deadlineAt = deadlineAt || (Date.now() + (EXAM_DURATION_SECONDS * 1000));

    const tick = () => {
        totalSecs = Math.max(0, Math.ceil((deadlineAt - Date.now()) / 1000));
        const m = Math.floor(totalSecs / 60);
        const s = totalSecs % 60;
        timer.textContent = `${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
        timer.classList.toggle('danger', totalSecs <= 300);
        if (totalSecs === 0 && !sending) submitExam(true);
    };

    tick();
    timerInterval = setInterval(() => {
        if (!started || sending) return;
        tick();
    }, 1000);
}

// ── BLACKOUT ───────────────────────────────────────────────
function showBlackout() {
    document.documentElement.classList.add('exam-protected');
    examShell.inert = true;
    blackout.classList.add('active');
}
function hideBlackout() {
    // A detected violation must be acknowledged through the warning dialog;
    // returning window focus alone must never reveal the exam again.
    if (!securityLockActive) {
        blackout.classList.remove('active');
        document.documentElement.classList.remove('exam-protected');
        examShell.inert = false;
    }
}

// Keep the protected page completely black while another window/app has
// focus. The warning becomes readable when the applicant returns.
function updateAwayMask(forceAway = false) {
    if (!started || sending) return;
    if (forceAway || document.hidden || !document.hasFocus()) {
        document.documentElement.classList.add('exam-away');
    } else {
        document.documentElement.classList.remove('exam-away');
    }
}
window.addEventListener('blur', () => updateAwayMask(true));
window.addEventListener('focus', () => updateAwayMask());
document.addEventListener('visibilitychange', () => updateAwayMask());

// ── PROGRESSIVE STRIKE MODAL ───────────────────────────────
function showStrikeModal(strikeNum, type) {
    const badge  = document.getElementById('strike-badge');
    const title  = document.getElementById('strike-title');
    const body   = document.getElementById('strike-body');

    badge.className = `s${Math.min(strikeNum, 3)}-badge`;

    if (strikeNum === 1) {
        badge.textContent  = 'Strike 1 of 3 — Warning';
        title.className    = 'h2 s1';
        title.textContent  = 'Security Warning';
        body.textContent   = `You have committed 1 of 3 allowed security violations. Navigating away, taking screenshots, or switching applications is strictly prohibited. Please remain in the exam window at all times.`;
    } else if (strikeNum === 2) {
        badge.textContent  = 'Strike 2 of 3 — Critical Warning';
        title.className    = 'h2 s2';
        title.textContent  = 'CRITICAL WARNING';
        body.textContent   = `This is your SECOND security violation. One more violation will AUTOMATICALLY SUBMIT your current answers and terminate your exam session immediately.`;
    } else {
        badge.textContent  = 'Strike 3 of 3 — Exam Terminated';
        title.className    = 'h2 s3';
        title.textContent  = 'Exam Terminated';
        body.textContent   = 'Your exam has been automatically submitted due to 3 security violations. Please see the proctor for further instructions.';
        document.getElementById('strike-continue').textContent = 'View Lockout Status';
    }

    if (type === 'focus_loss') {
        body.textContent = 'Possible Screenshot / Screen Overlay: The answer sheet lost focus. A screenshot preview, notification panel, or another application may have taken focus. This focus-loss incident has been reported to the admin. ' + body.textContent;
    }
    if (type === 'restricted_gesture') {
        body.textContent = 'Restricted Multi-touch / Possible Screenshot: A gesture using three or more fingers was detected and reported to the admin. Use one finger to answer or scroll. ' + body.textContent;
    }
    if (type === 'screenshot' || type === 'print_screen') {
        body.textContent = 'Screenshot shortcut detected and reported to the admin. The answer sheet has been hidden. ' + body.textContent;
    }

    strikeModal.classList.add('active');
}

document.getElementById('strike-continue').addEventListener('click', async () => {
    if (strikes >= 3) {
        location.replace(TERMINATED_URL);
    } else {
        try {
            if (!document.fullscreenElement) await document.documentElement.requestFullscreen();
            if (document.hidden || !document.hasFocus()) return;
            strikeModal.classList.remove('active');
            securityLockActive = false;
            hideBlackout();
        } catch (_) {
            document.getElementById('strike-body').textContent = 'Fullscreen could not be restored. Tap Continue again or ask the proctor for help.';
        }
    }
});

// ── INCIDENT REPORTING ─────────────────────────────────────
async function reportIncident(type) {
    if (!started || sending || confirmingSubmission || pendingIncident || securityLockActive) return;

    pendingIncident = true;
    securityLockActive = true;
    showBlackout();
    updateAwayMask();

    try {
        const res = await fetch(STRIKE_URL, {
            method:  'POST',
            keepalive: true,
            headers: {
                'Content-Type':  'application/json',
                'Accept':        'application/json',
                'X-CSRF-TOKEN':  CSRF,
            },
            body: JSON.stringify({
                incident_type: type,
                answers: collectAnswers()
            }),
        });

        if (!res.ok) throw new Error('Incident reporting failed');
        const state = await res.json();
        strikes = state.strikes;

        if (state.terminated) {
            sending = true;
            showStrikeModal(3, type);
            setTimeout(() => {
                location.replace(TERMINATED_URL);
            }, 3000);
            return;
        }

        showStrikeModal(strikes, type);
    } catch (err) {
        // Do not unlock or blindly retry: the server may already have recorded
        // this request. A proctor must reconcile an uncertain response.
        document.getElementById('strike-title').textContent = 'Security Reporting Interrupted';
        document.getElementById('strike-body').textContent = 'The exam is protected, but the security report could not be confirmed. Please ask the proctor for help before continuing.';
        document.getElementById('strike-continue').hidden = true;
        strikeModal.classList.add('active');
    } finally {
        pendingIncident = false;
    }
}

// ── SUBMIT EXAM ─────────────────────────────────────────────
async function submitExam(timedOut = false) {
    if (sending || !started) return;
    if (!timedOut && (securityLockActive || pendingIncident)) return;
    if (!timedOut) {
        // Native confirmation dialogs may blur the page on some browsers.
        confirmingSubmission = true;
        let confirmed;
        try {
            confirmed = confirm('Are you sure you want to submit your exam? This cannot be undone.');
        } finally {
            confirmingSubmission = false;
        }
        if (!confirmed) return;
    }

    sending = true;
    submitBtn.disabled = true;
    submitBtn.textContent = 'Submitting...';
    clearInterval(timerInterval);

    try {
        const answers = collectAnswers();
        const res = await fetch(SUBMIT_URL, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
            body:    JSON.stringify({ answers }),
        });

        if (res.ok) {
            location.replace(COMPLETE_URL);
        } else {
            submitBtn.textContent = 'Submit Failed – Try Again';
            submitBtn.disabled = false;
            sending = false;
        }
    } catch (err) {
        submitBtn.textContent = 'Network Error – Retry';
        submitBtn.disabled = false;
        sending = false;
    }
}

submitBtn.addEventListener('click', () => submitExam(false));

// ── BACK-BUTTON TRAP (pushState) ────────────────────────────
history.pushState({ exam: true }, '', location.href);
window.addEventListener('popstate', () => {
    history.pushState({ exam: true }, '', location.href);
    if (started) reportIncident('back_button');
});

// ── SCREENSHOT / PRINT HOTKEY BLACKOUT ─────────────────────
document.addEventListener('keydown', e => {
    // Block context-menu, save, and other dangerous combos
    if ((e.ctrlKey || e.metaKey) && ['s','u','i','j','a','c'].includes(e.key.toLowerCase())) {
        e.preventDefault();
    }
});

// Disable right-click context menu on exam
document.addEventListener('contextmenu', e => e.preventDefault());

// ── FOCUS-LOSS / TAB-SWITCH BLACKOUT ───────────────────────
installAdmissionFocusGuard({
    active: () => started && !sending && !confirmingSubmission && !securityLockActive,
    report: reportIncident,
});

// Covers browser navigation, closing/minimizing the page, and Chrome placing
// the page in its background lifecycle. keepalive on reportIncident lets the
// violation request leave even while the browser is changing pages.
window.addEventListener('pagehide', () => {
    if (started && !sending) {
        showBlackout();
        reportIncident('page_exit');
    }
});
document.addEventListener('freeze', () => {
    if (started && !sending) {
        showBlackout();
        reportIncident('page_backgrounded');
    }
});

// ── FULLSCREEN CHANGE ───────────────────────────────────────
document.addEventListener('fullscreenchange', () => {
    if (started && !document.fullscreenElement && !sending) {
        showBlackout();
        reportIncident('fullscreen_exit');
    }
});

// ── ENTER FULLSCREEN & START ────────────────────────────────
enterBtn.addEventListener('click', async () => {
    enterBtn.disabled = true;
    lockMsg.textContent = '';
    try {
        if (!document.documentElement.requestFullscreen) {
            throw new Error('Fullscreen is not supported on this browser. Please use Chrome or Firefox.');
        }
        await document.documentElement.requestFullscreen();
        started = true;
        kioskLock.classList.add('hidden');
        examShell.classList.add('visible');
        updateAnswerCount();
        startTimer();
    } catch (err) {
        lockMsg.textContent = err.message || 'Fullscreen failed. Please use a compatible browser.';
        enterBtn.disabled = false;
    }
});

// Attempt kiosk mode as soon as the admin-controlled redirect completes.
// If the browser requires a user gesture, the security notice remains over the questions.
async function launchKiosk() {
    try {
        await document.documentElement.requestFullscreen();
        started = true;
        kioskLock.classList.add('hidden');
        examShell.classList.add('visible');
        updateAnswerCount();
        startTimer();
    } catch (_) {
        lockMsg.textContent = 'Your browser requires one tap to enable mandatory fullscreen mode.';
    }
}
window.addEventListener('load', launchKiosk, { once: true });
</script>
</body>
</html>
