(() => {
    document.querySelectorAll('[data-live-queue]').forEach(queue => {
        const filters = queue.querySelector('[data-live-filters]');
        const results = queue.querySelector('[data-live-results]');
        const status = queue.querySelector('[data-live-status]');
        let url = new URL(location.href), controller, generation = 0, debounce;
        let posting = false, loading = false;

        async function refresh(next = url, manual = false) {
            if (document.hidden || posting || (!manual && loading)) return;
            if (!manual && (document.querySelector('.modal.show, [aria-modal="true"], .modal-backdrop') || (results.contains(document.activeElement) && document.activeElement.matches('input, select, textarea')) || results.querySelector('input[name="ids[]"]:checked, [data-security-termination][open]'))) return;
            controller?.abort();
            controller = new AbortController();
            const current = ++generation;
            loading = true;
            url = next;
            const open = [...results.querySelectorAll('details[open][data-review]')].map(item => item.dataset.review);
            try {
                const response = await fetch(url, { headers: { Accept: queue.dataset.json === 'true' ? 'application/json' : 'text/html', 'X-Requested-With': 'XMLHttpRequest' }, cache: 'no-store', signal: controller.signal });
                if (!response.ok || response.redirected) throw new Error('Live updates unavailable. Refresh the page or sign in again.');
                const html = queue.dataset.json === 'true' ? (await response.json()).html
                    : new DOMParser().parseFromString(await response.text(), 'text/html').querySelector('[data-live-results]')?.innerHTML;
                if (current !== generation) return;
                if (typeof html !== 'string') throw new Error('Could not refresh requests.');
                results.innerHTML = html;
                results.querySelectorAll('details[data-review]').forEach(item => { item.open = open.includes(item.dataset.review); });
                status.textContent = 'Updated ' + new Date().toLocaleTimeString();
                if (manual) history.replaceState(null, '', url);
            } catch (error) {
                if (error.name !== 'AbortError') status.textContent = error.message;
            } finally {
                if (current === generation) loading = false;
            }
        }
        function search() {
            const next = new URL(location.href);
            next.search = new URLSearchParams(new FormData(filters)).toString();
            refresh(next, true);
        }
        filters?.addEventListener('submit', event => { event.preventDefault(); clearTimeout(debounce); search(); });
        filters?.addEventListener('input', () => { clearTimeout(debounce); debounce = setTimeout(search, 350); });
        queue.addEventListener('click', event => {
            const link = event.target.closest('.pagination a');
            if (link && !event.ctrlKey && !event.metaKey) { event.preventDefault(); refresh(new URL(link.href), true); }
        });
        queue.addEventListener('change', event => {
            if (event.target.matches('[data-select-all]')) results.querySelectorAll('input[name="ids[]"]').forEach(input => { input.checked = event.target.checked; });
        });
        queue.addEventListener('submit', async event => {
            if (event.target === filters) return;
            if (event.target.id === 'archive-selection') {
                if (!results.querySelector('input[name="ids[]"]:checked, [data-security-termination][open]')) {
                    event.preventDefault(); status.textContent = 'Select at least one completed request first.'; return;
                }
                posting = true;
                controller?.abort();
                return;
            }

            const form = event.target;
            const button = form.querySelector('button[type=submit], button:not([type])');
            if (button) {
                event.preventDefault();
                posting = true;
                controller?.abort();
                button.disabled = true;
                status.textContent = 'Processing request...';
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
                    if (response.ok && data.success !== false) {
                        window.location.reload();
                    } else {
                        status.textContent = data.message || 'Action failed. Please refresh and retry.';
                        button.disabled = false;
                        posting = false;
                    }
                } catch (err) {
                    window.location.reload();
                }
            }
        });
        setInterval(() => refresh(), 5000);
        document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
    });
})();
