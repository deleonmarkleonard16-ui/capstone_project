(() => {
    if (!document.body.classList.contains('staff-interface')) return;
    let controlNumber = 0;
    const initialized = new WeakSet();
    const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
    function collapseSidebar(collapsed) {
        document.body.classList.toggle('sidebar-collapsed', collapsed);
        if (sidebarToggle) {
            sidebarToggle.setAttribute('aria-expanded', String(!collapsed));
            sidebarToggle.setAttribute('aria-label', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
            sidebarToggle.title = collapsed ? 'Expand sidebar' : 'Collapse sidebar';
        }
    }
    try { collapseSidebar(localStorage.getItem('psu-sidebar-collapsed') === '1'); } catch { /* Optional preference. */ }
    sidebarToggle?.addEventListener('click', () => {
        const collapsed = !document.body.classList.contains('sidebar-collapsed');
        collapseSidebar(collapsed);
        try { localStorage.setItem('psu-sidebar-collapsed', collapsed ? '1' : '0'); } catch { /* Keep this visit usable. */ }
    });
    document.querySelectorAll('.sidebar-link.active').forEach(link => link.setAttribute('aria-current', 'page'));

    function enhance(root) {
        const controls = [...root.querySelectorAll('.form-control, .form-select')];
        if (root.matches?.('.form-control, .form-select')) controls.push(root);
        for (const control of controls) {
            if (initialized.has(control) || !control.form || control.type === 'hidden') continue;
            initialized.add(control);
            const label = control.labels?.[0] || [...(control.parentElement?.children || [])].find(el => el.matches('.form-label:not([for])'));
            if (!label || label.contains(control)) continue;
            if (!control.id) control.id = `workspace-field-${++controlNumber}`;
            label.htmlFor = control.id;
            if (control.required && !label.querySelector('.ui-required')) {
                const mark = document.createElement('span');
                mark.className = 'ui-required'; mark.textContent = '*'; mark.setAttribute('aria-hidden', 'true');
                label.append(mark);
            }
            // Preserve existing server-side validation and custom feedback.
            if (control.classList.contains('is-invalid') || control.parentElement.querySelector('.invalid-feedback')) continue;
            let feedback;
            const validate = () => {
                if (!control.willValidate) return;
                const invalid = !control.validity.valid;
                control.classList.toggle('is-invalid', invalid);
                if (invalid) {
                    if (!feedback) {
                        feedback = document.createElement('span');
                        feedback.id = `${control.id}-workspace-error`;
                        feedback.className = 'ui-field-error';
                        control.insertAdjacentElement('afterend', feedback);
                        const describedBy = control.getAttribute('aria-describedby') || '';
                        control.setAttribute('aria-describedby', `${describedBy} ${feedback.id}`.trim());
                    }
                    feedback.textContent = control.validationMessage;
                    feedback.hidden = false;
                    control.setAttribute('aria-invalid', 'true');
                } else {
                    control.removeAttribute('aria-invalid');
                    if (feedback) feedback.hidden = true;
                }
            };
            control.addEventListener('blur', validate);
            control.addEventListener('invalid', validate);
            control.addEventListener('input', () => { if (feedback) validate(); });
            control.addEventListener('change', () => { if (feedback) validate(); });
        }
        const toggles = [...root.querySelectorAll('[data-row-actions]')];
        if (root.matches?.('[data-row-actions]')) toggles.push(root);
        toggles.forEach(button => {
            if (!window.bootstrap?.Dropdown || initialized.has(button)) return;
            initialized.add(button);
            bootstrap.Dropdown.getOrCreateInstance(button, {popperConfig: {strategy: 'fixed'}});
        });
    }
    enhance(document);
    new MutationObserver(records => {
        for (const record of records) for (const node of record.addedNodes) if (node.nodeType === 1) enhance(node);
    }).observe(document.body, {childList: true, subtree: true});

    function notify(message, type = 'success') {
        let container = document.querySelector('.ui-notices');
        if (!container) {
            container = document.createElement('div'); container.className = 'ui-notices';
            document.body.append(container);
        }
        const notice = document.createElement('div');
        notice.className = `alert alert-${['success', 'danger', 'warning', 'info'].includes(type) ? type : 'info'}`;
        notice.setAttribute('role', type === 'danger' ? 'alert' : 'status');
        const text = document.createElement('span'); text.textContent = message;
        const close = document.createElement('button'); close.type = 'button'; close.className = 'btn-close';
        close.setAttribute('aria-label', 'Dismiss notification'); close.onclick = () => notice.remove();
        notice.append(text, close); container.append(notice);
        // Errors stay visible until dismissed so users can read and act on them.
        if (type === 'success') setTimeout(() => notice.remove(), 6500);
    }
    function setLoading(region, loading) {
        if (!region) return;
        region.classList.toggle('ui-loading', loading);
        region.setAttribute('aria-busy', String(loading));
    }
    window.WorkspaceUI = {notify, setLoading};

    // Visual feedback only: keep named submitters and native form validation intact.
    document.addEventListener('submit', event => {
        const button = event.submitter;
        queueMicrotask(() => {
            if (event.defaultPrevented || !button || event.target.method.toLowerCase() === 'get' || event.target.target === '_blank') return;
            button.classList.add('ui-saving'); button.setAttribute('aria-busy', 'true');
            setTimeout(() => { button.classList.remove('ui-saving'); button.removeAttribute('aria-busy'); }, 15000);
        });
    });
    window.addEventListener('pageshow', () => {
        document.querySelectorAll('.ui-saving').forEach(button => { button.classList.remove('ui-saving'); button.removeAttribute('aria-busy'); });
    });
})();
