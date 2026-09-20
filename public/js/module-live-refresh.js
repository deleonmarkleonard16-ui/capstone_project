(() => {
    const region = document.querySelector('[data-module-live]');
    if (!region) return;
    let busy = false;

    async function refresh() {
        if (busy || document.hidden || document.querySelector('.modal.show, [aria-modal="true"], .modal-backdrop')) return;
        if (region.contains(document.activeElement) && document.activeElement.matches('input, select, textarea')) return;
        if (document.querySelector('input[type=file]')?.files.length) return;
        busy = true;
        try {
            const response = await fetch(location.href, {headers: {Accept: 'text/html'}, cache: 'no-store'});
            if (!response.ok || response.redirected) return;
            const page = new DOMParser().parseFromString(await response.text(), 'text/html');
            const next = page.querySelector('[data-module-live]');
            if (!next || next.dataset.module !== region.dataset.module || next.dataset.view !== region.dataset.view) return;
            if (document.querySelector('.modal.show, [aria-modal="true"], .modal-backdrop')) return;
            const openBatches = new Set([...region.querySelectorAll('details[data-batch][open]')].map(item => item.dataset.batch));
            next.querySelectorAll('.modal[id]').forEach(modal => {
                const existing = document.getElementById(modal.id);
                const copy = modal.cloneNode(true);
                if (existing) existing.replaceWith(copy); else document.body.append(copy);
                modal.remove();
            });
            region.innerHTML = next.innerHTML;
            region.querySelectorAll('details[data-batch]').forEach(item => { item.open = openBatches.has(item.dataset.batch); });
        } catch (_) { /* Keep the last rendered queue until the next poll. */ }
        finally { busy = false; }
    }

    setInterval(refresh, 4000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
    window.addEventListener('guidance:request-arrived', refresh);
})();
