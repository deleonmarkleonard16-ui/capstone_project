(() => {
    const modal = document.getElementById('guidance-review-modal');
    if (!modal || modal.dataset.reviewInitialized) return;
    modal.dataset.reviewInitialized = 'true';
    if (modal.parentElement !== document.body) {
        document.body.appendChild(modal);
    }
    const body = document.getElementById('guidance-review-body');
    const message = document.getElementById('guidance-review-message');
    const modalBody = modal.querySelector('.modal-body');
    let url = null, controller, renderController, verificationController;
    let generation = 0, visible = false, busy = false, loading = false, lastHtml = '';

    // Abortable frame boundaries let the loading shell paint and let close/new-open
    // events interrupt a long response instead of leaving stale rendering queued.
    function nextFrame(signal) {
        return new Promise((resolve, reject) => {
            if (signal.aborted) return reject(new DOMException('Cancelled', 'AbortError'));
            const abort = () => { cancelAnimationFrame(id); reject(new DOMException('Cancelled', 'AbortError')); };
            const id = requestAnimationFrame(() => { signal.removeEventListener('abort', abort); resolve(); });
            signal.addEventListener('abort', abort, { once: true });
        });
    }

    function loadReceipts(root, signal) {
        root.querySelectorAll('[data-review-receipt]').forEach(image => {
            const preview = image.closest('[data-review-preview]') || image.parentElement;
            const spinner = preview.querySelector('[data-review-receipt-loading]');
            const error = preview.querySelector('[data-review-receipt-error]');
            const finish = failed => {
                if (signal.aborted || !image.isConnected) return;
                if (spinner) spinner.hidden = true;
                if (error) error.hidden = !failed;
                image.hidden = failed;
                image.style.opacity = '1';
                preview.setAttribute('aria-busy', 'false');
            };
            image.addEventListener('load', () => finish(false), { once: true, signal });
            image.addEventListener('error', () => finish(true), { once: true, signal });
            // Source assignment happens only after the modal's shell has painted.
            if (image.dataset.src) {
                // A display:none lazy image never enters the browser's load area.
                image.hidden = false;
                image.style.opacity = '0';
                image.src = image.dataset.src;
            }
            if (image.complete && image.getAttribute('src')) {
                if (image.naturalWidth) finish(false); else finish(true);
            }
        });
    }

    async function render(html, signal, version) {
        await nextFrame(signal);
        const template = document.createElement('template');
        template.innerHTML = html;
        await nextFrame(signal);
        if (version !== generation || !visible) return;
        const scroll = modalBody.scrollTop;
        body.replaceChildren(template.content);
        modalBody.scrollTop = scroll;
        await nextFrame(signal);
        loadReceipts(body, signal);
        await nextFrame(signal);
        if (version !== generation || !visible) return;
        body.querySelectorAll('[data-review-deferred]').forEach(fragment => {
            fragment.replaceWith(fragment.content);
        });
        body.setAttribute('aria-busy', 'false');
    }

    async function refresh(manual = false) {
        if (!visible || !url || busy || (!manual && (loading || document.hidden))) return;
        controller?.abort();
        controller = new AbortController();
        const signal = controller.signal, version = ++generation;
        loading = true;
        try {
            // Give Bootstrap and the loading status a paint before fetching/parsing.
            await nextFrame(signal);
            const response = await fetch(url, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, cache: 'no-store', signal });
            if (!response.ok || response.redirected) throw new Error('Unable to load this request. Refresh or sign in again.');
            const data = await response.json();
            if (version !== generation || !visible) return;
            if (typeof data.html !== 'string') throw new Error('The review response is invalid. Please retry.');
            if (data.html !== lastHtml) {
                renderController?.abort();
                renderController = new AbortController();
                const rendering = renderController;
                const cancelRender = () => rendering.abort();
                signal.addEventListener('abort', cancelRender, { once: true });
                body.setAttribute('aria-busy', 'true');
                try { await render(data.html, rendering.signal, version); }
                finally { signal.removeEventListener('abort', cancelRender); }
                if (version !== generation || !visible) return;
                lastHtml = data.html;
            }
            message.textContent = 'Updated ' + new Date().toLocaleTimeString();
        } catch (error) {
            if (error.name !== 'AbortError' && version === generation) {
                message.textContent = error.message;
                body.setAttribute('aria-busy', 'false');
            }
        } finally {
            if (version === generation) loading = false;
        }
    }

    function stop() {
        visible = false;
        generation++;
        controller?.abort();
        renderController?.abort();
        verificationController?.abort();
        loading = busy = false;
        lastHtml = '';
    }

    document.addEventListener('click', event => {
        const button = event.target.closest('[data-guidance-review]');
        if (!button || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        stop();
        url = button.dataset.guidanceReview;
        body.replaceChildren();
        body.setAttribute('aria-busy', 'true');
        message.textContent = 'Loading request...';
        const title = document.getElementById('guidance-review-title');
        if (title) title.textContent = /submission/i.test(button.textContent) ? 'Review Submission' : 'Review Details';
        visible = true;
        if (modal.parentElement !== document.body) {
            document.body.appendChild(modal);
        }
        bootstrap.Modal.getOrCreateInstance(modal).show();
        refresh(true);
    });
    modal.addEventListener('hide.bs.modal', stop);
    modal.addEventListener('hidden.bs.modal', () => {
        stop();
        body.replaceChildren();
        body.setAttribute('aria-busy', 'false');
        message.textContent = '';
        url = null;
    });

    // Never remove a backdrop owned by another open/opening modal. Bootstrap
    // normally cleans up itself; this also removes leftovers from interrupted hides.
    const opening = new Set();
    document.addEventListener('show.bs.modal', event => { opening.add(event.target); });
    document.addEventListener('shown.bs.modal', event => { opening.delete(event.target); });
    document.addEventListener('hide.bs.modal', event => { opening.delete(event.target); });
    document.addEventListener('hidden.bs.modal', event => {
        opening.delete(event.target);
        if (!event.target.matches('[data-guidance-review-modal], #guidance-review-modal, #receipt-preview')) return;
        const otherOpening = [...opening].some(element => element !== event.target && element.isConnected);
        const otherOpen = Array.from(document.querySelectorAll('.modal, .offcanvas')).some(el => {
            if (el === event.target || !el.isConnected) return false;
            return el.classList.contains('show') || el.getAttribute('aria-modal') === 'true';
        });
        if (otherOpening || otherOpen) return;
        document.querySelectorAll('.modal-backdrop').forEach(backdrop => backdrop.remove());
        document.body.classList.remove('modal-open');
        document.body.style.overflow = 'auto';
        document.body.style.removeProperty('padding-right');
    });

    document.getElementById('guidance-review-refresh').addEventListener('click', () => refresh(true));
    modal.addEventListener('submit', async event => {
        const form = event.target.closest('[data-review-verify]');
        if (!form) return;
        event.preventDefault();
        if (busy) return;
        busy = true;
        const version = ++generation;
        controller?.abort();
        loading = false;
        verificationController = new AbortController();
        const button = form.querySelector('button');
        button.disabled = true;
        message.textContent = 'Verifying receipt and generating QR...';
        try {
            const response = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, signal: verificationController.signal });
            const data = await response.json();
            if (!response.ok) throw new Error(Object.values(data.errors || {}).flat().join(' ') || data.message || 'Verification failed.');
            if (version !== generation || !visible) return;
            message.textContent = data.message;
            busy = false;
            await refresh(true);
        } catch (error) {
            if (error.name !== 'AbortError' && version === generation) message.textContent = error.message;
        } finally {
            if (version === generation) busy = false;
            if (button.isConnected) button.disabled = false;
        }
    });
    setInterval(() => refresh(), 5000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
})();