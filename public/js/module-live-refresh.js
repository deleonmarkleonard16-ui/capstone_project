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
            const response = await fetch(location.href, {headers: {Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest'}, cache: 'no-store'});
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

    region.addEventListener('submit', async event => {
        const form = event.target;
        if (!form || form.matches('[data-live-filters]') || form.querySelector('input[type=file]')) return;
        const button = form.querySelector('button[type=submit], button:not([type])');
        if (!button) return;
        event.preventDefault();
        button.disabled = true;
        try {
            const csrf = form.querySelector('input[name="_token"]')?.value || document.querySelector('meta[name="csrf-token"]')?.content;
            const response = await fetch(form.action, {
                method: form.method || 'POST',
                body: new FormData(form),
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(csrf ? {'X-CSRF-TOKEN': csrf} : {})
                }
            });
            const data = await response.json().catch(() => ({}));
            if (response.ok && (data.success !== false)) {
                window.location.reload();
            } else {
                alert(data.message || 'Action failed. Please refresh and try again.');
                button.disabled = false;
            }
        } catch (err) {
            window.location.reload();
        }
    });

    setInterval(refresh, 4000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
    window.addEventListener('guidance:request-arrived', refresh);
})();
