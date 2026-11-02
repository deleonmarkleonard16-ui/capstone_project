(() => {
    function update(form) {
        const remarks = form.querySelector('[name="remarks"]');
        const recommendation = form.querySelector('[name="recommendations"]:checked');
        const confirmation = form.querySelector('[name="review_confirmed"]');
        const checks = {
            remarks: `Remarks: ${remarks?.value.trim() ? 'complete' : 'required before finalizing'}.`,
            recommendation: `Recommendation: ${recommendation ? 'selected' : 'required before finalizing'}.`,
            confirmation: `Review confirmation: ${confirmation?.checked ? 'confirmed' : 'pending'}.`,
        };
        form.querySelectorAll('[data-evaluation-check]').forEach(item => { item.textContent = checks[item.dataset.evaluationCheck]; });
    }
    function initialize(root) {
        if (root.matches?.('[data-evaluation-form]')) update(root);
        root.querySelectorAll?.('[data-evaluation-form]').forEach(update);
    }
    initialize(document);
    new MutationObserver(changes => changes.forEach(change => change.addedNodes.forEach(initialize)))
        .observe(document.body, {childList: true, subtree: true});
    ['input', 'change'].forEach(type => document.addEventListener(type, event => {
        const form = event.target.closest('[data-evaluation-form]');
        if (!form) return;
        form.querySelectorAll('input, textarea').forEach(input => input.setCustomValidity(''));
        update(form);
    }));
    document.addEventListener('submit', event => {
        const form = event.target;
        if (!form.matches('[data-evaluation-form]')) return;
        const confirmation = form.querySelector('[name="review_confirmed"]');
        const finalizing = ['archive', 'complete_and_archive'].includes(event.submitter?.value);
        if (!finalizing && !confirmation?.checked) return;
        const remarks = form.querySelector('[name="remarks"]');
        const recommendation = form.querySelector('[name="recommendations"]:checked');
        let invalid;
        if (!remarks?.value.trim()) invalid = remarks;
        else if (!recommendation) invalid = form.querySelector('[name="recommendations"]');
        else if (!confirmation?.checked) invalid = confirmation;
        if (invalid) {
            event.preventDefault();
            event.stopImmediatePropagation();
            invalid.setCustomValidity('Complete the evaluation checklist before finalizing.');
            invalid.reportValidity();
        }
    }, true);
})();
