// Browser-visible focus signals only; OS overlays may not expose any signal.
window.installAdmissionFocusGuard = function ({ active, report, page = document, host = window }) {
    const check = () => {
        if (!active()) return;
        if (page.hidden) report('tab_switch');
        else if (!page.hasFocus()) report('focus_loss');
        else if (!page.fullscreenElement) report('fullscreen_exit');
    };
    host.addEventListener('blur', () => { if (active()) report('focus_loss'); });
    page.addEventListener('visibilitychange', check);
    page.addEventListener('fullscreenchange', check);
    host.addEventListener('focus', check);
    // Some mobile browsers update hasFocus without delivering a blur event.
    const interval = host.setInterval(check, 300);
    return () => host.clearInterval(interval);
};
