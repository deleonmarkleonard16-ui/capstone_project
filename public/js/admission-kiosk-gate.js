(function () {
    const gate = document.getElementById('admission-kiosk-gate');
    const enterButton = document.getElementById('admission-kiosk-enter');
    const status = document.getElementById('admission-kiosk-status');
    if (!gate || !enterButton || !status) return;

    let ready = false;
    let requesting = false;
    let initialAttemptDone = false;
    const root = document.documentElement;

    function isHidden() {
        return document.hidden === true
            || document.visibilityState === 'hidden'
            || document.webkitHidden === true;
    }

    function isFullscreen() {
        return Boolean(document.fullscreenElement || document.webkitFullscreenElement);
    }

    function showGate(message) {
        ready = false;
        root.classList.add('admission-kiosk-locked');
        gate.hidden = false;
        status.textContent = message;
        enterButton.disabled = false;
        window.dispatchEvent(new CustomEvent('admission:kiosk-locked'));
    }

    function unlock() {
        if (!isFullscreen() || isHidden() || !document.hasFocus()) {
            showGate('Fullscreen and an active page focus are required. Close calls or floating windows, then tap to try again.');
            return false;
        }
        ready = true;
        root.classList.remove('admission-kiosk-locked');
        gate.hidden = true;
        window.dispatchEvent(new CustomEvent('admission:kiosk-ready'));
        return true;
    }

    async function enterFullscreen() {
        if (requesting) return;
        requesting = true;
        enterButton.disabled = true;
        status.textContent = 'Checking fullscreen and page focus…';
        try {
            const request = root.requestFullscreen || root.webkitRequestFullscreen;
            if (!request) throw new Error('This browser does not support fullscreen. Open the QR link in Chrome and try again.');
            if (!isFullscreen()) await request.call(root);
            if (!unlock()) return;
        } catch (error) {
            showGate(error.message || 'Fullscreen was blocked. Tap the button again and allow fullscreen.');
        } finally {
            requesting = false;
        }
    }

    function lockIfAway() {
        if (!ready) return;
        if (isHidden()) {
            showGate('The exam page became hidden. Return to this page, close calls or floating windows, and re-enable fullscreen to continue.');
        } else if (!document.hasFocus()) {
            showGate('The browser page lost focus. Close calls or floating windows, then re-enable fullscreen to continue.');
        } else if (!isFullscreen()) {
            showGate('Fullscreen ended. Re-enable fullscreen before continuing.');
        }
    }

    enterButton.addEventListener('click', enterFullscreen);
    document.addEventListener('visibilitychange', lockIfAway);
    document.addEventListener('webkitvisibilitychange', lockIfAway);
    document.addEventListener('fullscreenchange', lockIfAway);
    document.addEventListener('webkitfullscreenchange', lockIfAway);
    window.addEventListener('blur', lockIfAway);
    window.addEventListener('pagehide', lockIfAway);
    document.addEventListener('freeze', lockIfAway);
    window.addEventListener('focus', lockIfAway);
    window.addEventListener('pageshow', lockIfAway);
    window.setInterval(lockIfAway, 100);

    // Browsers often require a user gesture for fullscreen. Try on QR load,
    // then keep the blocking gate visible if the browser rejects the request.
    window.addEventListener('load', function () {
        if (initialAttemptDone) return;
        initialAttemptDone = true;
        enterFullscreen();
    }, { once: true });

    window.admissionKioskIsReady = function () {
        lockIfAway();
        return ready && isFullscreen() && !isHidden() && document.hasFocus();
    };
}());
