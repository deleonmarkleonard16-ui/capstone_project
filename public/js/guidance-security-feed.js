(() => {
    const panel = document.querySelector('[data-security-feed]');
    if (!panel) return;

    const status = panel.querySelector('[data-security-feed-status]');
    const history = panel.querySelector('[data-security-history]');
    const toasts = document.querySelector('[data-security-toasts]');
    const seen = new Set();
    let cursor = null;
    let busy = false;

    async function poll() {
        if (busy || document.hidden) return;
        busy = true;
        try {
            const url = new URL(panel.dataset.securityFeed, location.href);
            if (cursor !== null) url.searchParams.set('after', cursor);
            const response = await fetch(url, {headers: {Accept: 'application/json'}, cache: 'no-store'});
            if (!response.ok || response.redirected) throw new Error('Security updates unavailable. Refresh or sign in again.');
            const data = await response.json();
            const initialLoad = cursor === null;

            for (const incident of data.incidents) {
                const appointmentId = Number(incident.appointment_id);
                document.querySelectorAll(`[data-security-count="${appointmentId}"]`).forEach(badge => {
                    badge.textContent = `${incident.strike_count} security strike(s) · Details`;
                });
                document.querySelectorAll(`[data-security-details-wrap="${appointmentId}"]`).forEach(details => {
                    details.hidden = false;
                });
                if (incident.terminated) {
                    document.querySelectorAll(`[data-appointment="${appointmentId}"] [data-status-badge]`).forEach(cell => {
                        const badge = document.createElement('span');
                        badge.className = 'badge text-bg-danger';
                        badge.textContent = 'Terminated - Violation';
                        cell.replaceChildren(badge);
                    });
                }

                if (seen.has(incident.id)) continue;
                seen.add(incident.id);
                const timestamp = new Date(incident.timestamp).toLocaleString();
                const isTermination = incident.terminated;
                const message = `${isTermination ? 'Terminated' : `Strike ${incident.strike_count}/3`} · ${incident.student_name} · ID ${incident.student_id} · ${incident.label} · ${timestamp}`;
                const item = document.createElement('li');
                item.textContent = message;
                if (isTermination) item.className = 'text-danger fw-bold';
                history.prepend(item);
                while (history.children.length > 100) history.lastElementChild.remove();

                if (initialLoad) continue;
                document.querySelectorAll(`[data-security-details="${appointmentId}"]`).forEach(list => {
                    const detail = document.createElement('li');
                    detail.textContent = `Strike ${incident.strike_count}: ${incident.label} · ${timestamp}`;
                    list.append(detail);
                });
                const toast = document.createElement('div');
                toast.className = `toast show border-0 ${isTermination ? 'text-bg-dark' : 'text-bg-danger'}`;
                toast.style.pointerEvents = 'auto';
                toast.setAttribute('role', 'alert');
                const body = document.createElement('div');
                body.className = 'toast-body';
                const close = document.createElement('button');
                close.type = 'button';
                close.className = 'btn-close btn-close-white float-end ms-2';
                close.setAttribute('aria-label', 'Dismiss security alert');
                close.addEventListener('click', () => toast.remove());
                body.append(close, document.createTextNode(message));
                toast.append(body);
                toasts.append(toast);
                while (toasts.children.length > 5) toasts.firstElementChild.remove();
                setTimeout(() => toast.remove(), 15000);
            }

            cursor = data.cursor;
            status.textContent = 'Security monitoring live · Updated ' + new Date().toLocaleTimeString();
        } catch (error) {
            status.textContent = error.message;
        } finally {
            busy = false;
        }
    }

    poll();
    if (window.Echo) window.Echo.private('guidance.proctoring').listen('.guidance.security.strike', poll);
    setInterval(poll, 3000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(); });
})();
