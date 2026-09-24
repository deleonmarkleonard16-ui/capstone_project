<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>PSU-CAT Digital Exam – {{ $applicant->application_number }}</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { height: 100%; background: #0d1b3e; color: #e0e8f8; font-family: Arial, Helvetica, sans-serif; }
        body { user-select: none; -webkit-user-select: none; overflow-x: hidden; }

        /* ── FORCED FULLSCREEN KIOSK LOCKDOWN MODAL ── */
        #kiosk-lock {
            position: fixed; inset: 0; z-index: 9999;
            background: linear-gradient(135deg, #0d1b3e 0%, #1a3066 100%);
            display: flex; align-items: center; justify-content: center;
            flex-direction: column; text-align: center; padding: 32px;
            transition: opacity 0.3s;
        }
        #kiosk-lock.hidden { display: none; }
        #kiosk-lock .psu-logo { font-size: 15px; text-transform: uppercase; letter-spacing: 2px; color: #ffd700; margin-bottom: 8px; }
        #kiosk-lock h1 { font-size: 28px; font-weight: 800; color: #fff; margin-bottom: 6px; }
        #kiosk-lock .sub { color: #a8c0e8; font-size: 14px; margin-bottom: 24px; }
        #kiosk-lock .applicant-badge { background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); border-radius: 12px; padding: 12px 24px; margin-bottom: 28px; }
        #kiosk-lock .applicant-badge .name { font-size: 20px; font-weight: 700; color: #fff; }
        #kiosk-lock .applicant-badge .appno { font-size: 13px; color: #ffd700; font-family: monospace; margin-top: 4px; }
        #kiosk-lock .instructions { font-size: 13px; color: #a8c0e8; max-width: 480px; margin-bottom: 24px; line-height: 1.6; }
        #kiosk-lock .instructions ul { text-align: left; margin: 8px 0 0 0; padding-left: 18px; }
        #kiosk-lock .instructions ul li { margin-bottom: 4px; }
        #enter-btn { background: #ffd700; color: #0d1b3e; border: 0; padding: 14px 40px; font-size: 17px; font-weight: 800; border-radius: 8px; cursor: pointer; letter-spacing: 0.5px; transition: transform 0.1s, box-shadow 0.1s; }
        #enter-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(255,215,0,0.4); }
        #enter-btn:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }
        #lock-msg { font-size: 13px; color: #ff9090; margin-top: 14px; min-height: 20px; }

        /* ── EXAM SHELL (hidden until fullscreen) ── */
        #exam-shell { display: none; max-width: 960px; margin: 0 auto; padding: 20px; }
        #exam-shell.visible { display: block; }

        /* Sticky header */
        #exam-header { position: sticky; top: 0; z-index: 100; background: #0d1b3e; border-bottom: 2px solid #1e3a7c; padding: 12px 0; margin-bottom: 20px; display: flex; align-items: center; justify-content: space-between; }
        #exam-header .left { font-size: 13px; color: #a8c0e8; }
        #exam-header .left strong { color: #fff; font-size: 15px; display: block; }
        #timer { background: #1e3a7c; border-radius: 8px; padding: 8px 20px; font-size: 22px; font-weight: 800; font-family: monospace; color: #ffd700; }
        #timer.danger { background: #7c1e1e; color: #ff6060; animation: pulse 1s infinite; }
        @keyframes pulse { 0%,100% { opacity: 1; } 50% { opacity: 0.6; } }

        /* Answer grid: 4 vertical balanced columns of 20 items */
        .exam-columns-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; }
        @media (max-width: 992px) { .exam-columns-grid { grid-template-columns: repeat(2, 1fr); } }
        @media (max-width: 576px) { .exam-columns-grid { grid-template-columns: 1fr; } }
        .exam-column { display: flex; flex-direction: column; gap: 8px; }
        .question-card { background: #111e45; border-radius: 8px; padding: 10px 12px; display: flex; align-items: center; justify-content: space-between; gap: 8px; border: 1.5px solid transparent; transition: border-color 0.2s; }
        .question-card:hover { border-color: #2a4580; }
        .question-card.answered { border-color: #1e5c3e; background: #0e2a1e; }
        .q-num { font-size: 13px; font-weight: 700; color: #a8c0e8; min-width: 24px; font-family: monospace; }
        .choices { display: flex; gap: 6px; }
        .choice-label { cursor: pointer; display: flex; align-items: center; justify-content: center; width: 28px; height: 28px; font-size: 13px; font-weight: 700; border-radius: 50%; border: 1.5px solid #2a4580; color: #c8d8f0; transition: all 0.15s; }
        .choice-label:hover { border-color: #4a7be0; color: #fff; background: #1e3060; }
        .choice-label input[type="radio"] { display: none; }
        .choice-label input[type="radio"]:checked + span { font-weight: 800; }
        .choice-label:has(input:checked) { background: #ffd700; border-color: #ffd700; color: #0d1b3e; }

        /* Submit bar */
        #submit-bar { text-align: center; padding: 28px; margin-top: 20px; }
        #submit-btn { background: #16a34a; color: #fff; border: 0; padding: 16px 48px; font-size: 18px; font-weight: 800; border-radius: 8px; cursor: pointer; letter-spacing: 0.5px; }
        #submit-btn:hover { background: #15803d; }
        #submit-btn:disabled { opacity: 0.5; cursor: not-allowed; }
        #answered-count { font-size: 13px; color: #a8c0e8; margin-bottom: 12px; }

        /* ── BLACKOUT SCREEN ── */
        #blackout { position: fixed; inset: 0; z-index: 9000; background: #000; display: none; align-items: center; justify-content: center; flex-direction: column; }
        #blackout.active { display: flex; }
        #blackout .msg { color: #fff; font-size: 22px; font-weight: 700; margin-bottom: 8px; }
        #blackout .sub { color: #888; font-size: 14px; }

        /* ── STRIKE WARNING MODAL ── */
        #strike-modal { position: fixed; inset: 0; z-index: 9500; background: rgba(0,0,0,0.85); display: none; align-items: center; justify-content: center; }
        #strike-modal.active { display: flex; }
        #strike-modal-inner { background: #fff; color: #111; border-radius: 12px; padding: 32px; max-width: 440px; text-align: center; }
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
    </style>
</head>
<body>

{{-- ══════════════════════════════════════════════════════════
     FORCED FULLSCREEN KIOSK LOCKDOWN MODAL
     Rendered on page load, hidden only after fullscreen entry
     ══════════════════════════════════════════════════════════ --}}
<div id="kiosk-lock">
    <div class="psu-logo">Pangasinan State University · San Carlos Campus</div>
    <h1>PSU-CAT Digital Exam</h1>
    <div class="sub">PSU College Admission Test · Official Digital Answer Sheet</div>

    <div class="applicant-badge">
        <div class="name">{{ mb_strtoupper($applicant->full_name) }}</div>
        <div class="appno">{{ $applicant->application_number }} · {{ $applicant->course_choice }}</div>
    </div>

    <div class="instructions">
        <strong>Before you begin, read carefully:</strong>
        <ul>
            <li>The exam will run in <strong>mandatory fullscreen kiosk mode</strong>.</li>
            <li>Exiting fullscreen, switching tabs, or losing window focus counts as a <strong>security strike</strong>.</li>
            <li>Using Print Screen, Ctrl+P, or similar keys is <strong>prohibited</strong>.</li>
            <li>3 strikes result in <strong>automatic exam submission</strong>.</li>
            <li>There are <strong>80 questions</strong>. Choose A, B, C, or D for each item.</li>
        </ul>
    </div>

    <button id="enter-btn" type="button">
        🔒 Enter Fullscreen &amp; Begin Exam
    </button>
    <div id="lock-msg"></div>
</div>

{{-- ══════════════════════════════════════════════════════════
     EXAM SHELL (hidden until fullscreen kiosk is entered)
     ══════════════════════════════════════════════════════════ --}}
<div id="exam-shell">
    <div id="exam-header">
        <div class="left">
            <strong>PSU-CAT Exam · {{ $applicant->application_number }}</strong>
            {{ mb_strtoupper($applicant->full_name) }} · {{ $applicant->course_choice }}
        </div>
        <div id="timer">00:00</div>
    </div>

    <div id="answered-count">Answered: <span id="ans-count">0</span> / 80</div>

    <form id="exam-form">
        @csrf
        <div class="exam-columns-grid">
            @for($col = 0; $col < 4; $col++)
                <div class="exam-column">
                    @for($row = 1; $row <= 20; $row++)
                        @php($i = $col * 20 + $row)
                        <div class="question-card" id="qcard-{{ $i }}" data-item="{{ $i }}">
                            <div class="q-num">{{ str_pad($i, 2, '0', STR_PAD_LEFT) }}.</div>
                            <div class="choices">
                                @foreach(['A','B','C','D'] as $letter)
                                <label class="choice-label" for="a{{ $i }}_{{ $letter }}">
                                    <input type="radio" name="answers[{{ $i }}]" id="a{{ $i }}_{{ $letter }}" value="{{ $letter }}">
                                    <span>{{ $letter }}</span>
                                </label>
                                @endforeach
                            </div>
                        </div>
                    @endfor
                </div>
            @endfor
        </div>
    </form>

    <div id="submit-bar">
        <button id="submit-btn" type="button" disabled>Submit Exam</button>
    </div>
</div>

{{-- ── BLACKOUT SCREEN (screenshot / focus-loss protection) ── --}}
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

<script>
const SUBMIT_URL  = @json(route('admission.submit', $token));
const STRIKE_URL  = @json(route('admission.strike', $token));
const COMPLETE_URL = @json(route('admission.complete'));
const CSRF        = document.querySelector('meta[name="csrf-token"]').content;
const INITIAL_STRIKES = {{ (int)$applicant->strike_count }};

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
let strikes        = INITIAL_STRIKES;
let pendingIncident = false;
let lastIncident   = 0;
let totalSecs      = 3600; // 60-minute default
let timerInterval  = null;

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
    submitBtn.disabled = (n < 80 || !started || sending);
}

document.getElementById('exam-form').addEventListener('change', updateAnswerCount);

// ── TIMER ──────────────────────────────────────────────────
function startTimer() {
    timerInterval = setInterval(() => {
        if (!started || sending) return;
        totalSecs = Math.max(0, totalSecs - 1);
        const m = Math.floor(totalSecs / 60);
        const s = totalSecs % 60;
        timer.textContent = `${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
        timer.classList.toggle('danger', totalSecs <= 300);
        if (totalSecs === 0 && !sending) submitExam(true);
    }, 1000);
}

// ── BLACKOUT ───────────────────────────────────────────────
function showBlackout() { blackout.classList.add('active'); }
function hideBlackout() { blackout.classList.remove('active'); }

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
        document.getElementById('strike-continue').textContent = 'Close';
    }

    strikeModal.classList.add('active');
}

document.getElementById('strike-continue').addEventListener('click', () => {
    strikeModal.classList.remove('active');
    hideBlackout();
    if (strikes >= 3) {
        location.replace(COMPLETE_URL);
    } else {
        // Re-enter fullscreen
        if (!document.fullscreenElement) {
            document.documentElement.requestFullscreen().catch(() => {});
        }
    }
});

// ── INCIDENT REPORTING ─────────────────────────────────────
async function reportIncident(type) {
    if (!started || sending || pendingIncident) return;
    if (Date.now() - lastIncident < 800) return;

    pendingIncident = true;
    showBlackout();
    lastIncident = Date.now();

    try {
        const res = await fetch(STRIKE_URL, {
            method:  'POST',
            headers: {
                'Content-Type':  'application/json',
                'Accept':        'application/json',
                'X-CSRF-TOKEN':  CSRF,
            },
            body: JSON.stringify({ incident_type: type, answers: collectAnswers() }),
        });

        if (!res.ok) return;
        const state = await res.json();
        strikes = state.strikes;

        if (state.terminated) {
            sending = true;
            showStrikeModal(3, type);
            return;
        }

        showStrikeModal(strikes, type);
    } catch (err) {
        // Connection lost, keep blackout up
    } finally {
        pendingIncident = false;
    }
}

// ── SUBMIT EXAM ─────────────────────────────────────────────
async function submitExam(timedOut = false) {
    if (sending || !started) return;
    if (!timedOut && !confirm('Are you sure you want to submit your exam? This cannot be undone.')) return;

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
    const isPrint = e.key === 'PrintScreen'
        || (e.ctrlKey && e.key.toLowerCase() === 'p')
        || (e.metaKey && e.shiftKey && e.key === '4');

    if (isPrint) {
        e.preventDefault();
        showBlackout();
        reportIncident(e.key === 'PrintScreen' ? 'print_screen' : 'print');
        return;
    }
    // Block context-menu, save, and other dangerous combos
    if ((e.ctrlKey || e.metaKey) && ['s','u','i','j','a','c'].includes(e.key.toLowerCase())) {
        e.preventDefault();
    }
});

// Disable right-click context menu on exam
document.addEventListener('contextmenu', e => e.preventDefault());

// ── FOCUS-LOSS / TAB-SWITCH BLACKOUT ───────────────────────
window.addEventListener('blur', () => {
    if (started && !sending) {
        showBlackout();
        reportIncident('focus_loss');
    }
});
window.addEventListener('focus', () => {
    if (!strikeModal.classList.contains('active')) hideBlackout();
});
document.addEventListener('visibilitychange', () => {
    if (document.hidden && started && !sending) {
        showBlackout();
        reportIncident('tab_switch');
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
</script>
</body>
</html>
