(() => {
    const form = document.getElementById('assessment-form');
    if (!form) return;
    const lock = document.getElementById('assessment-lock');
    const content = document.getElementById('assessment-content');
    const fields = document.getElementById('answer-fields');
    const start = document.getElementById('start-assessment');
    const message = document.getElementById('submission-message');
    const steps = [...form.querySelectorAll('.assessment-step')];
    const headers = { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': form.querySelector('[name="_token"]').value };
    const securityListeners = new AbortController();
    const securitySignal = securityListeners.signal;
    let timerInterval, stateInterval, strikeInterval;
    let started = form.dataset.started === 'true', sessionReady = false, finished = false, submitting = false, entering = false;
    let deadline = performance.now() + Number(form.dataset.remaining) * 1000;
    let currentStep = 0, saveTimer, chain = Promise.resolve(), revision = 0, savedRevision = 0, retryAfter = 0;

    function notify(text) {
        message.textContent = text;
        document.getElementById('lock-message').textContent = text;
    }
    function pause(reason) {
        if (finished) return;
        fields.disabled = true;
        content.inert = true;
        lock.hidden = false;
        document.getElementById('lock-title').textContent = started ? 'Assessment Paused' : 'Ready to start?';
        start.textContent = started ? 'Resume Assessment in Fullscreen Mode' : 'Start Assessment in Fullscreen Mode';
        notify(reason);
        start.focus();
    }
    function complete() {
        if (finished) return;
        finished = true;
        securityListeners.abort();
        clearInterval(timerInterval); clearInterval(stateInterval); clearInterval(strikeInterval);
        history.replaceState(null, '', location.href);
        document.documentElement.classList.remove('assessment-security-lock');
        document.getElementById('security-blackout')?.setAttribute('hidden', '');
        if (storageKey) { try { sessionStorage.removeItem(storageKey); } catch (_) {} }
        clearTimeout(saveTimer);
        fields.disabled = true;
        lock.hidden = true;
        content.hidden = true;
        document.getElementById('assessment-complete').hidden = false;
        const exit = document.fullscreenElement ? document.exitFullscreen().catch(() => {}) : Promise.resolve();
        if (form.dataset.complete) exit.finally(() => location.replace(form.dataset.complete));
    }
    function applyState(data, sentAt) {
        if (data.status === 'Completed') { complete(); return; }
        if (Number.isInteger(data.section_index)) {
            if (data.section_index !== currentStep) {
                form.querySelectorAll('input[type="radio"]').forEach(input => {
                    const keys = input.name.match(/^answers\[([^\]]+)\](?:\[([^\]]+)\])?$/);
                    if (!keys) return;
                    const test = keys[2] ? keys[1] : steps[0].dataset.test;
                    const item = keys[2] || keys[1];
                    input.checked = data.answers?.[test]?.[item] !== undefined && Number(data.answers[test][item]) === Number(input.value);
                });
            }
            showStep(data.section_index);
        }
        if (Number.isFinite(Number(data.remaining_seconds))) {
            deadline = performance.now() + Math.max(0, Number(data.remaining_seconds) * 1000 - (performance.now() - sentAt));
        }
    }
    async function request(url, method = 'GET', answers) {
        const sentAt = performance.now();
        const response = await fetch(url, { method, headers, cache: 'no-store',
            body: method === 'POST' && answers !== undefined ? JSON.stringify({ answers, section_index: currentStep }) : undefined });
        const data = await response.json().catch(() => ({}));
        if (response.status === 410) { complete(); return { status: 'Completed' }; }
        if (!response.ok) throw new Error(Object.values(data.errors || {}).flat().join(' ') || data.message || 'Connection unavailable. Saved answers will be retried.');
        applyState(data, sentAt);
        return data;
    }
    // Read checked inputs directly: FormData omits answers while the fieldset is locked.
    function answers() {
        const snapshot = {};
        form.querySelectorAll('input[type="radio"]:checked').forEach(input => {
            const keys = input.name.match(/^answers\[([^\]]+)\](?:\[([^\]]+)\])?$/);
            if (!keys) return;
            if (keys[2]) (snapshot[keys[1]] ??= {})[keys[2]] = Number(input.value);
            else snapshot[keys[1]] = Number(input.value);
        });
        return Object.keys(snapshot).length ? snapshot : [];
    }
    function enqueue(task) {
        const work = chain.catch(() => {}).then(task);
        chain = work;
        return work;
    }
    function save() {
        clearTimeout(saveTimer);
        if (!sessionReady || finished || submitting || revision === savedRevision) return Promise.resolve();
        const snapshot = answers(), version = revision;
        return enqueue(async () => {
            if (finished || version <= savedRevision) return;
            await request(form.dataset.progress, 'POST', snapshot);
            savedRevision = version;
            if (!finished) message.textContent = 'Answers saved.';
        }).catch(error => notify(error.message));
    }
    function showStep(index) {
        steps.forEach((step, i) => { step.hidden = i !== index; });
        currentStep = index;
        document.getElementById('current-step-label').textContent = index + 1;

        // -- Fix 1a: Sync progress-bar stepper pills --
        document.querySelectorAll('.stepper-pill').forEach((pill, i) => {
            pill.classList.toggle('active', i === index);
            pill.classList.toggle('completed', i < index);
        });

        // -- Fix 1b: Sync assessment-flow badge highlights --
        document.querySelectorAll('.stepper-flow-badge').forEach((badge, i) => {
            const isActive = i === index;
            const isDone   = i < index;
            badge.classList.toggle('bg-primary',            isActive);
            badge.classList.toggle('text-white',            isActive);
            badge.classList.toggle('bg-success-subtle',     isDone);
            badge.classList.toggle('text-success-emphasis', isDone);
            badge.classList.toggle('bg-light',              !isActive && !isDone);
            badge.classList.toggle('text-secondary',        !isActive && !isDone);
            badge.classList.toggle('border',                !isActive);
        });

        // -- Fix 2: Smooth scroll to top on every step transition --
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
    function validateStep(index) {
        const missing = [...steps[index].querySelectorAll('.item-card')].filter(card => !card.querySelector('input:checked'));
        steps[index].querySelectorAll('.item-card').forEach(card => card.classList.toggle('unanswered', missing.includes(card)));
        if (missing.length) {
            showStep(index);
            missing[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
            missing[0].querySelector('input').focus();
            notify('Answer each item in this section before continuing.');
            return false;
        }
        return true;
    }
    showStep(Number(form.dataset.section || 0));

    start.addEventListener('click', async () => {
        entering = true;
        start.disabled = true;
        try {
            if (!document.fullscreenElement) {
                if (!document.documentElement.requestFullscreen) throw new Error('Fullscreen is required. Use a browser that supports fullscreen.');
                await document.documentElement.requestFullscreen();
            }
            await enqueue(() => request(form.dataset.start, 'POST'));
            if (finished) return;
            started = sessionReady = true;
            if (!document.fullscreenElement || document.hidden || !document.hasFocus()) throw new Error('Return to this window and resume fullscreen.');
            document.documentElement.classList.remove('assessment-security-lock');
            document.getElementById('security-blackout')?.setAttribute('hidden', '');
            lock.hidden = true;
            content.inert = false;
            fields.disabled = false;
            notify('');
        } catch (error) { pause(error.message); }
        finally { entering = false; start.disabled = false; }
    });
    form.addEventListener('change', event => {
        if (!event.target.matches('input[type="radio"]')) return;
        revision++;
        event.target.closest('.item-card').classList.remove('unanswered');
        clearTimeout(saveTimer);
        saveTimer = setTimeout(save, 400);
    });
    form.querySelectorAll('.next-step').forEach(button => button.addEventListener('click', () => {
        if (validateStep(currentStep)) submit();
    }));
    form.querySelectorAll('.prev-step').forEach(button => button.addEventListener('click', () => showStep(currentStep - 1)));

    async function submit(timedOut = false) {
        if (!sessionReady || submitting || finished) return;
        if (!timedOut && (!lock.hidden || !document.fullscreenElement || document.hidden)) return pause('Resume fullscreen before submitting.');
        if (!timedOut && !validateStep(currentStep)) return;
        const snapshot = answers();
        submitting = true;
        fields.disabled = true;
        notify(timedOut ? 'Time expired. Finalizing saved answers...' : 'Submitting answers...');
        try {
            // The server finalizes saved drafts at the deadline, even when the browser was suspended.
            const state = await enqueue(() => timedOut ? request(form.dataset.state) : request(form.getAttribute('action'), 'POST', snapshot));
            if (state.status === 'Completed') complete();
        } catch (error) { notify(error.message + ' Retrying is safe.'); retryAfter = performance.now() + 5000; }
        finally { submitting = false; if (!finished && lock.hidden) fields.disabled = false; }
    }
    form.addEventListener('submit', event => { event.preventDefault(); submit(); });
    timerInterval = setInterval(() => {
        if (finished) return;
        const remaining = started ? Math.max(0, Math.ceil((deadline - performance.now()) / 1000)) : 600;
        const display = document.getElementById('timer-display');
        display.textContent = `${String(Math.floor(remaining / 60)).padStart(2, '0')}:${String(remaining % 60).padStart(2, '0')}`;
        display.classList.toggle('text-bg-danger', remaining <= 300);
        display.classList.toggle('text-bg-primary', remaining > 300);
        if (started && remaining === 0 && performance.now() >= retryAfter) submit(true);
    }, 250);
    stateInterval = setInterval(() => {
        if (!sessionReady || finished || submitting || document.hidden) return;
        save();
        enqueue(() => finished ? null : request(form.dataset.state)).catch(error => notify(error.message));
    }, 3000);
    const pendingStrikes = [];
    const storageKey = form.dataset.token ? 'guidance-strikes:' + form.dataset.token : null;
    const incidentTimes = new Map();
    let sendingStrike = false, terminating = false;
    const persistStrikes = () => {
        if (!storageKey) return;
        try { sessionStorage.setItem(storageKey, JSON.stringify(pendingStrikes)); } catch (_) {}
    };
    if (storageKey) {
        try {
            const stored = JSON.parse(sessionStorage.getItem(storageKey) || '[]');
            if (Array.isArray(stored)) pendingStrikes.push(...stored.slice(0, 100).filter(item => typeof item.event_id === 'string' && typeof item.incident_type === 'string'));
        } catch (_) {}
    }
    function eventId() {
        if (crypto.randomUUID) return crypto.randomUUID();
        const bytes = crypto.getRandomValues(new Uint8Array(16));
        bytes[6] = (bytes[6] & 15) | 64; bytes[8] = (bytes[8] & 63) | 128;
        const hex = [...bytes].map(value => value.toString(16).padStart(2, '0')).join('');
        return `${hex.slice(0,8)}-${hex.slice(8,12)}-${hex.slice(12,16)}-${hex.slice(16,20)}-${hex.slice(20)}`;
    }
    async function flushStrikes() {
        if (sendingStrike || !sessionReady || !form.dataset.strikes || !pendingStrikes.length || !navigator.onLine) return;
        sendingStrike = true;
        try {
            while (pendingStrikes.length) {
                const timeoutController = new AbortController();
                const timeout = setTimeout(() => timeoutController.abort(), 8000);
                let response;
                try {
                    response = await fetch(form.dataset.strikes, {method:'POST', headers, keepalive:true, signal:timeoutController.signal,
                        body:JSON.stringify({...pendingStrikes[0], token:form.dataset.token})});
                } finally { clearTimeout(timeout); }
                if (response.ok || response.status === 422) { pendingStrikes.shift(); persistStrikes(); }
                else if (response.status === 410) { pendingStrikes.length = 0; persistStrikes(); complete(); return; }
                else break;
                if (response.ok && (await response.json().catch(() => ({}))).status === 'Completed') { complete(); return; }
            }
        } catch (_) { /* Retry the same event ID after reconnect; no duplicate strikes. */ }
        finally { sendingStrike = false; }
    }
    const strikeCounters = { back_navigation: 0, screenshot: 0, app_switch: 0, focus_loss: 0 };
    const strikeThreshold = Number(form.dataset.strikeThreshold || 3);
    let totalStrikes = 0;
    const modalEl = document.getElementById('security-warning-modal');
    const modalBadge = document.getElementById('security-modal-badge');
    const modalMsg = document.getElementById('security-modal-message');
    const modalTitle = document.getElementById('security-modal-title');
    const modalAck = document.getElementById('security-modal-ack');

    function showWarningModal(type, strikeNum) {
        if (!modalEl || !modalMsg || !modalBadge || !modalTitle) return;
        let warningText = '';
        if (type === 'back_navigation') {
            if (strikeNum === 1) warningText = 'WARNING (Strike 1/2): Navigating away is prohibited.';
            else if (strikeNum === 2) warningText = 'CRITICAL WARNING (Strike 2/2): One more attempt will terminate your assessment.';
            else warningText = 'CRITICAL WARNING (Strike 3/3): Assessment terminated due to prohibited navigation.';
        } else if (type === 'screenshot' || type === 'print') {
            if (strikeNum === 1) warningText = 'WARNING (Strike 1/2): Screenshots and screen recordings are strictly prohibited.';
            else if (strikeNum === 2) warningText = 'CRITICAL WARNING (Strike 2/2): Further screenshot attempts will terminate your exam.';
            else warningText = 'CRITICAL WARNING (Strike 3/3): Assessment terminated due to unauthorized capture attempt.';
        } else {
            if (strikeNum === 1) warningText = 'WARNING (Strike 1/2): Navigating away or switching apps is prohibited.';
            else if (strikeNum === 2) warningText = 'CRITICAL WARNING (Strike 2/2): One more attempt will terminate your assessment.';
            else warningText = 'CRITICAL WARNING (Strike 3/3): Assessment terminated due to multiple policy violations.';
        }

        modalMsg.textContent = warningText;
        modalBadge.textContent = strikeNum >= 3 ? 'Strike 3 of 3 — Violation' : `Strike ${strikeNum} of 3`;
        modalTitle.textContent = strikeNum >= 2 ? 'CRITICAL SECURITY WARNING' : 'SECURITY WARNING';

        if (window.bootstrap?.Modal) {
            const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
            bsModal.show();
        } else {
            modalEl.classList.add('show');
            modalEl.style.display = 'block';
        }
    }

    if (modalAck) {
        modalAck.addEventListener('click', () => {
            if (window.bootstrap?.Modal) {
                const bsModal = bootstrap.Modal.getInstance(modalEl);
                bsModal?.hide();
            } else {
                modalEl.classList.remove('show');
                modalEl.style.display = 'none';
            }
            if (!document.fullscreenElement && document.documentElement.requestFullscreen) {
                document.documentElement.requestFullscreen().catch(() => {});
            }
        });
    }

    function triggerBlackout(durationMs = 1500) {
        const blackout = document.getElementById('security-blackout');
        document.documentElement.classList.add('assessment-security-lock');
        if (blackout) {
            blackout.hidden = false;
            setTimeout(() => {
                if (!document.hidden && document.hasFocus() && !finished) {
                    blackout.hidden = true;
                }
            }, durationMs);
        }
    }

    function breach(type, reason) {
        if (finished || terminating || !started || !sessionReady) return;
        const group = ['focus_loss','app_switch','fullscreen_exit'].includes(type) ? 'focus_change' : type;
        const now = performance.now();
        if (now - (incidentTimes.get(group) ?? -Infinity) < 1500) return;
        incidentTimes.set(group, now);
        triggerBlackout(1500);
        totalStrikes++;
        strikeCounters[type] = (strikeCounters[type] || 0) + 1;
        const strikeNum = totalStrikes;
        pause(reason + ' The timer continues running.');
        showWarningModal(type, strikeNum);
        save();
        if (pendingStrikes.length < 100) {
            pendingStrikes.push({ event_id: eventId(), incident_type: type });
            persistStrikes();
        }
        flushStrikes();

        if (strikeNum >= strikeThreshold) {
            terminating = true;
            fields.disabled = true;
            content.inert = true;
            notify('Assessment terminated. Submitting saved answers and security incidents.');
            flushStrikes();
        }
    }

    // Intercept Screen Recording API
    if (navigator.mediaDevices?.getDisplayMedia) {
        try {
            const origGetDisplayMedia = navigator.mediaDevices.getDisplayMedia.bind(navigator.mediaDevices);
            navigator.mediaDevices.getDisplayMedia = function(...args) {
                triggerBlackout(3000);
                breach('screenshot', 'Screen recording attempts are prohibited.');
                return Promise.reject(new DOMException('Screen recording is prohibited.', 'NotAllowedError'));
            };
        } catch (_) {}
    }

    document.addEventListener('fullscreenchange', () => {
        if (!document.fullscreenElement && !entering) breach('fullscreen_exit', 'Fullscreen was exited. Resume fullscreen to continue.');
    }, {signal:securitySignal});
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            triggerBlackout(2000);
            breach('app_switch', 'The assessment was hidden or another app was opened.');
        } else if (sessionReady && !finished) {
            document.getElementById('security-blackout')?.setAttribute('hidden', '');
            flushStrikes();
            enqueue(() => request(form.dataset.state)).catch(error => notify(error.message));
        }
    }, {signal:securitySignal});
    window.addEventListener('blur', () => {
        if (!entering) {
            triggerBlackout(1500);
            breach(document.hidden ? 'app_switch' : 'focus_loss', 'The assessment lost focus. Resume fullscreen to continue.');
        }
    }, {signal:securitySignal});
    window.addEventListener('focus', () => { document.getElementById('security-blackout')?.setAttribute('hidden', ''); flushStrikes(); }, {signal:securitySignal});
    window.addEventListener('online', flushStrikes, {signal:securitySignal});
    history.pushState({ assessment: true }, '', location.href);
    window.addEventListener('popstate', () => {
        history.pushState({ assessment: true }, '', location.href);
        breach('back_navigation', 'Back navigation is restricted during the assessment.');
    }, {signal:securitySignal});
    window.addEventListener('beforeunload', event => {
        if (started) { event.preventDefault(); event.returnValue = ''; }
    }, {signal:securitySignal});
    ['contextmenu', 'copy', 'cut', 'selectstart'].forEach(type => document.addEventListener(type, event => {
        event.preventDefault();
        if (type !== 'selectstart') breach(type === 'contextmenu' ? 'context_menu' : 'copy', 'Copying and context menus are restricted.');
    }, {signal:securitySignal}));
    function shortcut(event) {
        const key = event.key.toLowerCase();
        let type;
        if (key === 'printscreen' || ((event.metaKey || event.ctrlKey) && event.shiftKey && ['3','4','5','s'].includes(key))) {
            type = 'screenshot';
            triggerBlackout(2000);
        } else if ((event.ctrlKey && event.shiftKey && ['i','j','c'].includes(key)) || (event.metaKey && event.altKey && ['i','j','c'].includes(key)) || key === 'f12') {
            type = 'devtools';
        } else if ((event.ctrlKey || event.metaKey) && key === 'p') {
            type = 'print';
            triggerBlackout(2000);
        } else if ((event.ctrlKey || event.metaKey) && key === 's') {
            type = 'save';
        } else if ((event.ctrlKey || event.metaKey) && ['c','x'].includes(key)) {
            type = 'copy';
        } else if (event.altKey && key === 'arrowleft') {
            type = 'back_navigation';
        }
        if (type) {
            event.preventDefault();
            if (!event.repeat) breach(type, 'A restricted shortcut was detected. Resume fullscreen to continue.');
        }
    }
    document.addEventListener('keydown', event => {
        shortcut(event);
        if (!lock.hidden && event.key.toLowerCase() === 'tab') {
            const elements = [...lock.querySelectorAll('button:not(:disabled),a[href]')];
            if (event.shiftKey && document.activeElement === elements[0]) { event.preventDefault(); elements.at(-1)?.focus(); }
            else if (!event.shiftKey && document.activeElement === elements.at(-1)) { event.preventDefault(); elements[0]?.focus(); }
        }
    }, {capture:true, signal:securitySignal});
    document.addEventListener('keyup', event => { if (event.key.toLowerCase() === 'printscreen') shortcut(event); }, {capture:true, signal:securitySignal});
    strikeInterval = setInterval(flushStrikes, 3000);
    start.focus();
})();
