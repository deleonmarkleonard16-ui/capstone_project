(() => {
    'use strict';
    let dialog;
    let pending = null;
    let origin;
    const approvedForms = new WeakSet();
    function describe(message) {
        const rules = [
            [/delete batch/i, 'Delete batch?', 'Delete Batch', 'danger'],
            [/delete.*account/i, 'Delete account?', 'Delete account', 'danger'],
            [/delete.*session/i, 'Delete session?', 'Delete Session', 'danger'],
            [/clear all/i, 'Clear selected answers?', 'Clear Answers', 'danger'],
            [/force.?terminate/i, 'Terminate examination?', 'Terminate Exam', 'danger'],
            [/end.*(test|assessment|session)/i, 'End assessment?', 'End Assessment', 'danger'],
            [/completed and archive/i, 'Complete and archive cycle?', 'Complete & Archive', 'warning'],
            [/finish reviewing/i, 'Finish batch review?', 'Finish & Archive', 'warning'],
            [/archive/i, 'Archive records?', 'Archive', 'warning'],
            [/exit maintenance/i, 'Activate admission cycle?', 'Activate Cycle', 'primary'],
            [/maintenance/i, 'Enable maintenance mode?', 'Enable Maintenance', 'warning'],
            [/emergency proctor/i, 'Change emergency proctor override?', 'Change Override', 'warning'],
            [/unlock/i, 'Unlock assessment?', 'Unlock Assessment', 'primary'],
            [/present|checked-in/i, 'Confirm attendance?', 'Mark Present', 'primary'],
            [/absent/i, 'Update attendance?', 'Mark Absent', 'warning'],
            [/claimed/i, 'Complete document claim?', 'Mark Claimed', 'primary'],
            [/start test/i, 'Start test session?', 'Start Test', 'primary'],
            [/compute.*score/i, 'Score answer sheet?', 'Score Answer Sheet', 'primary'],
            [/submit.*exam/i, 'Submit examination?', 'Submit Exam', 'primary'],
            [/warn/i, 'Warn examinee?', 'Send Warning', 'warning'],
            [/pause/i, 'Pause examination?', 'Pause Exam', 'warning'],
        ];
        const rule = rules.find(([pattern]) => pattern.test(message));
        return rule ? {title: rule[1], confirmLabel: rule[2], tone: rule[3]} : {title: 'Confirm action', confirmLabel: 'Continue', tone: 'primary'};
    }
    function createDialog() {
        dialog = document.createElement('dialog');
        dialog.className = 'app-confirm-dialog';
        dialog.setAttribute('aria-labelledby', 'app-confirm-title');
        dialog.setAttribute('aria-describedby', 'app-confirm-message app-confirm-detail');
        // Only static markup is injected. Record names and messages use textContent.
        dialog.innerHTML = '<div class="app-confirm-header"><h2 class="app-confirm-title" id="app-confirm-title"></h2><button type="button" class="app-confirm-close" aria-label="Close">×</button></div><div class="app-confirm-body"><p class="app-confirm-message" id="app-confirm-message"></p><p class="app-confirm-detail" id="app-confirm-detail" hidden></p></div><div class="app-confirm-footer"><button type="button" class="app-confirm-button app-confirm-cancel">Cancel</button><button type="button" class="app-confirm-button app-confirm-accept"></button></div>';
        document.body.appendChild(dialog);
        const close = accepted => { if (pending) dialog.close(accepted ? 'accepted' : 'cancelled'); };
        dialog.querySelector('.app-confirm-close').addEventListener('click', () => close(false));
        dialog.querySelector('.app-confirm-cancel').addEventListener('click', () => close(false));
        dialog.querySelector('.app-confirm-accept').addEventListener('click', () => close(true));
        dialog.addEventListener('cancel', event => { event.preventDefault(); close(false); });
        dialog.addEventListener('close', () => {
            const resolve = pending;
            pending = null;
            if (origin?.isConnected) origin.focus({preventScroll: true});
            resolve?.(dialog.returnValue === 'accepted');
        });
        // Keep an underlying Bootstrap modal from pulling focus out of this top-layer dialog.
        document.addEventListener('focusin', event => {
            if (dialog.open && dialog.contains(event.target)) event.stopImmediatePropagation();
        }, true);
    }
    window.AppConfirm = (message, options = {}) => {
        if (pending) return Promise.resolve(false);
        if (!dialog) createDialog();
        const config = {...describe(message), ...options};
        const split = message.indexOf('?');
        const body = split >= 0 ? message.slice(0, split + 1) : message;
        const detail = options.detail ?? (split >= 0 ? message.slice(split + 1).trim() : '');
        dialog.dataset.tone = config.tone;
        dialog.querySelector('.app-confirm-title').textContent = config.title;
        dialog.querySelector('.app-confirm-message').textContent = body;
        const detailElement = dialog.querySelector('.app-confirm-detail');
        detailElement.textContent = detail;
        detailElement.hidden = !detail;
        dialog.querySelector('.app-confirm-accept').textContent = config.confirmLabel;
        origin = document.activeElement;
        return new Promise(resolve => {
            pending = resolve;
            dialog.returnValue = '';
            dialog.showModal();
            dialog.querySelector('.app-confirm-cancel').focus();
        });
    };
    window.AppConfirm.dismiss = () => { if (pending) dialog.close('cancelled'); };
    document.addEventListener('submit', async event => {
        const form = event.target;
        if (approvedForms.has(form)) { approvedForms.delete(form); return; }
        const trigger = event.submitter;
        const source = trigger?.hasAttribute('data-confirm') ? trigger : form;
        const message = source.dataset.confirm;
        if (!message) return;
        event.preventDefault();
        event.stopImmediatePropagation();
        const accepted = await window.AppConfirm(message, {
            ...describe(message),
            ...(source.dataset.confirmTitle ? {title: source.dataset.confirmTitle} : {}),
            ...(source.dataset.confirmLabel ? {confirmLabel: source.dataset.confirmLabel} : {}),
        });
        if (!accepted || !form.isConnected || (trigger && (!trigger.isConnected || trigger.disabled))) return;
        approvedForms.add(form);
        try { form.requestSubmit(trigger || undefined); }
        finally { approvedForms.delete(form); }
    }, true);
})();
