(() => {
    const intro = document.getElementById('psu-intro');
    if (!intro) return;

    const root = document.documentElement;
    const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const dismiss = () => {
        root.classList.remove('psu-intro-active');
        intro.remove();
    };

    try {
        if (motion.matches || sessionStorage.getItem('psu-intro-seen-v1')) {
            dismiss();
            return;
        }
        sessionStorage.setItem('psu-intro-seen-v1', '1');
    } catch {
        // Keep navigation immediate when browser storage is unavailable.
        dismiss();
        return;
    }

    intro.hidden = false;
    root.classList.add('psu-intro-active');
    intro.addEventListener('animationend', event => {
        if (event.target === intro && event.animationName === 'psu-intro-exit') intro.remove();
    });
    window.setTimeout(dismiss, 2800);
    window.addEventListener('pagehide', dismiss, {once: true});
    motion.addEventListener?.('change', event => { if (event.matches) dismiss(); });
})();
