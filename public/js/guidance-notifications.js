(() => {
    const bell = document.querySelector('[data-request-notifications]');
    if (!bell) return;
    const count = bell.querySelector('[data-notification-count]');
    const list = bell.querySelector('[data-notification-list]');
    const toasts = document.querySelector('[data-request-toasts]');
    const reviewModal = document.getElementById('notification-review-modal');
    const reviewBody = reviewModal?.querySelector('[data-notification-review-body]');
    let cursor = null;
    let polling = false;
    let audioContext;
    function setCount(value) {
        const unread = Math.max(0, Number(value) || 0);
        count.textContent = unread > 99 ? '99+' : String(unread);
        count.dataset.unread = unread;
        count.hidden = unread < 1;
    }
    function unlockAudio() {
        try {
            audioContext ||= new (window.AudioContext || window.webkitAudioContext)();
            audioContext.resume();
        } catch (_) {}
    }
    document.addEventListener('pointerdown', unlockAudio, {once: true});
    document.addEventListener('keydown', unlockAudio, {once: true});

    function chime() {
        try {
            audioContext ||= new (window.AudioContext || window.webkitAudioContext)();
            if (audioContext.state === 'suspended') audioContext.resume();
            const oscillator = audioContext.createOscillator();
            const gain = audioContext.createGain();
            oscillator.type = 'sine'; oscillator.frequency.setValueAtTime(660, audioContext.currentTime);
            oscillator.frequency.exponentialRampToValueAtTime(880, audioContext.currentTime + 0.12);
            gain.gain.setValueAtTime(0.0001, audioContext.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.055, audioContext.currentTime + 0.025);
            gain.gain.exponentialRampToValueAtTime(0.0001, audioContext.currentTime + 0.32);
            oscillator.connect(gain).connect(audioContext.destination);
            oscillator.start(); oscillator.stop(audioContext.currentTime + 0.34);
        } catch (_) { /* Browsers may require a user gesture before audio playback. */ }
    }

    function label(item) { return `${item.student_name} · ${item.module} · ${item.reference}`; }

    function link(item, className) {
        const anchor = document.createElement('a');
        anchor.href = item.url;
        anchor.className = className;
        anchor.textContent = label(item);
        anchor.dataset.notificationId = item.id;
        anchor.dataset.requestId = item.request_id;
        anchor.dataset.moduleType = item.module_type;
        anchor.dataset.studentName = item.student_name;
        anchor.dataset.unread = item.read ? '0' : '1';
        anchor.dataset.readUrl = item.read_url;
        anchor.dataset.reviewUrl = item.review_url;
        return anchor;
    }

    function toast(item) {
        if (!toasts) return;
        const box = document.createElement('div');
        box.className = 'toast show border-0 shadow';
        box.setAttribute('role', 'status');
        const body = document.createElement('div');
        body.className = 'toast-body d-flex align-items-start gap-2';
        const anchor = link(item, 'text-decoration-none flex-grow-1 text-body');
        const close = document.createElement('button');
        close.type = 'button'; close.className = 'btn-close'; close.setAttribute('aria-label', 'Dismiss notification');
        close.addEventListener('click', () => box.remove());
        body.append(anchor, close); box.append(body); toasts.prepend(box);
        setTimeout(() => box.remove(), 5000);
    }

    async function showReview(item) {
        if (!reviewModal || !reviewBody) return;
        reviewBody.textContent = 'Loading request...';
        bootstrap.Modal.getOrCreateInstance(reviewModal).show();
        try {
            const response = await fetch(item.review_url, {headers: {Accept: 'text/html'}, cache: 'no-store'});
            if (!response.ok) throw new Error('Could not load this request.');
            reviewBody.innerHTML = await response.text();
            const queueLink = document.createElement('a');
            queueLink.href = item.url;
            queueLink.className = 'btn btn-outline-primary btn-sm mt-3';
            queueLink.textContent = 'Open module queue';
            reviewBody.append(queueLink);
        } catch (error) { reviewBody.textContent = error.message; }
    }

    async function updateQueue(items) {
        const table = document.querySelector('[data-individual-queue]');
        const target = table && items.find(item => item.module_type === table.dataset.module);
        if (!target) return;
        try {
            const response = await fetch(target.url || location.href, {headers: {Accept: 'text/html'}, cache: 'no-store'});
            if (!response.ok || response.redirected) return;
            const doc = new DOMParser().parseFromString(await response.text(), 'text/html');
            const source = doc.querySelector('[data-individual-queue]');
            if (!source) return;
            const tbody = table.querySelector('tbody');
            for (const item of items) {
                if (item.module_type !== table.dataset.module || tbody.querySelector(`[data-request-id="${item.request_id}"]`)) continue;
                const row = source.querySelector(`[data-request-id="${item.request_id}"]`);
                if (!row) continue;
                tbody.querySelector('[data-empty-row]')?.remove();
                tbody.prepend(row.cloneNode(true));
            }
        } catch (_) { /* The normal queue refresh can recover after a transient failure. */ }
    }

    async function poll() {
        if (polling || document.hidden) return;
        polling = true;
        try {
            const url = new URL(bell.dataset.feedUrl, location.href);
            if (cursor !== null) url.searchParams.set('after', cursor);
            const response = await fetch(url, {headers: {Accept: 'application/json'}, cache: 'no-store'});
            if (!response.ok || response.redirected) return;
            const data = await response.json();
            const first = cursor === null;
            cursor = data.cursor;
            setCount(data.unread);
            list.replaceChildren();
            if (!data.recent.length) {
                const empty = document.createElement('p'); empty.className = 'p-3 mb-0 text-muted'; empty.textContent = 'No recent individual requests.'; list.append(empty);
            } else {
                data.recent.forEach(item => list.append(link(item, `list-group-item list-group-item-action ${item.read ? '' : 'fw-semibold'}`)));
            }
            if (!first && data.new.length) {
                data.new.forEach(toast);
                chime();
                updateQueue(data.new);
                window.dispatchEvent(new Event('guidance:request-arrived'));
            }
            const requestedId = Number(new URL(location.href).searchParams.get('review_notification'));
            if (requestedId && !reviewModal.dataset.opened) {
                const item = data.recent.find(entry => entry.id === requestedId);
                if (item) { reviewModal.dataset.opened = '1'; showReview(item); }
            }
        } catch (_) { /* Retry at the next interval. */ }
        finally { polling = false; }
    }

    document.addEventListener('click', async event => {
        const anchor = event.target.closest('[data-notification-id]');
        if (!anchor) return;
        event.preventDefault();
        const item = {request_id: Number(anchor.dataset.requestId), module_type: anchor.dataset.moduleType, url: anchor.href};
        updateQueue([item]);
        showReview({review_url: anchor.dataset.reviewUrl, url: anchor.href});
        if (anchor.dataset.unread === '1') setCount(Number(count.dataset.unread || 0) - 1);
        document.querySelectorAll(`[data-notification-id="${anchor.dataset.notificationId}"]`).forEach(link => {
            link.dataset.unread = '0'; link.classList.remove('fw-semibold');
        });
        try {
            const response = await fetch(anchor.dataset.readUrl, {method: 'POST', headers: {'X-CSRF-TOKEN': bell.dataset.csrf, Accept: 'application/json'}});
            if (!response.ok) throw new Error('Could not mark notification read.');
            setCount((await response.json()).unread);
        } catch (_) { poll(); }
    });

    async function clearModule(module, navigation = false) {
        if (!module) return;
        const links = [...list.querySelectorAll('[data-module-type]')].filter(link => link.dataset.moduleType === module);
        const visibleUnread = links.filter(link => link.dataset.unread === '1').length;
        setCount(Number(count.dataset.unread || 0) - visibleUnread);
        links.forEach(link => link.remove());
        toasts?.querySelectorAll('[data-module-type]').forEach(link => { if (link.dataset.moduleType === module) link.closest('.toast')?.remove(); });
        try {
            const url = new URL(bell.dataset.clearUrl, location.href);
            url.searchParams.set('module', module);
            const response = await fetch(url, {method: 'POST', keepalive: navigation, headers: {'X-CSRF-TOKEN': bell.dataset.csrf, Accept: 'application/json'}});
            if (!response.ok) throw new Error('Could not clear module notifications.');
            setCount((await response.json()).unread);
        } catch (_) { if (!navigation) poll(); }
    }

    document.addEventListener('click', event => {
        const moduleLink = event.target.closest('[data-module-navigation]');
        if (moduleLink) clearModule(moduleLink.dataset.moduleNavigation, true);
    });
    document.addEventListener('click', event => {
        const receipt = event.target.closest('#notification-review-modal [data-receipt]');
        if (receipt && !document.getElementById('receipt-preview')) window.open(receipt.dataset.receipt, '_blank', 'noopener');
    });
    poll().then(() => clearModule(bell.dataset.currentModule));
    setInterval(poll, 5000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(); });
})();
