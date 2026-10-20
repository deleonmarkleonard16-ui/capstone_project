// Browser-visible focus signals only; OS overlays may not expose any signal.
window.installAdmissionFocusGuard = function ({ active, report, page = document, host = window }) {
    const listeners = [];
    const listen = (target, name, handler, options) => {
        target.addEventListener(name, handler, options);
        listeners.push(() => target.removeEventListener(name, handler, options));
    };
    const isHidden = () => page.hidden === true
        || page.visibilityState === 'hidden'
        || page.webkitHidden === true;
    const check = () => {
        if (!active()) return;
        if (isHidden()) report('tab_switch');
        else if (!page.hasFocus()) report('focus_loss');
        else if (!page.fullscreenElement && !page.webkitFullscreenElement) report('fullscreen_exit');
    };
    listen(host, 'blur', () => { if (active()) report('focus_loss'); });
    listen(page, 'visibilitychange', check);
    listen(page, 'webkitvisibilitychange', check);
    listen(page, 'fullscreenchange', check);
    listen(page, 'webkitfullscreenchange', check);
    listen(host, 'focus', check);
    listen(host, 'pageshow', check);
    listen(host, 'pagehide', () => { if (active()) report('page_exit'); });
    listen(page, 'freeze', () => { if (active()) report('page_backgrounded'); });
    listen(page, 'resume', check);
    // Three or more touches are restricted during this exam. This is a
    // possible capture gesture, not proof of a screenshot. OS-consumed
    // gestures may never reach the page. Scrolling and pinch zoom stay valid.
    listen(page, 'touchstart', event => {
        if (active() && event.touches.length >= 3) report('restricted_gesture');
    }, {capture: true, passive: true});
    // Cancellation alone is not a violation: ordinary scrolling causes it.
    listen(page, 'touchcancel', check, {passive: true});
    const shortcut = event => {
        if (!active() || event.repeat) return;
        const key = (event.key || '').toLowerCase();
        const printScreen = ['printscreen', 'snapshot'].includes(key) || event.code === 'PrintScreen';
        // Key release support covers browsers that omit PrintScreen keydown.
        if (event.type === 'keyup' && !printScreen) return;
        const screenshot = (event.metaKey && event.shiftKey && ['3', '4', '5', '#', '$', '%'].includes(key))
            || (event.metaKey && event.shiftKey && (key === 's' || event.code === 'KeyS'));
        const print = (event.ctrlKey || event.metaKey) && key === 'p';
        if (printScreen || screenshot || print) {
            event.preventDefault();
            report(printScreen ? 'print_screen' : screenshot ? 'screenshot' : 'print');
        }
    };
    listen(page, 'keydown', shortcut, {capture: true});
    listen(page, 'keyup', shortcut, {capture: true});
    listen(host, 'beforeprint', () => { if (active()) report('print'); });
    // Some mobile browsers update hasFocus without delivering a blur event.
    const interval = host.setInterval(check, 100);
    return () => {
        host.clearInterval(interval);
        listeners.forEach(remove => remove());
    };
};
