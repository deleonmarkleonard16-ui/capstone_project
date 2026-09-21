(() => {
    const form = document.getElementById('guidance-track');
    if (!form) return;
    let pollTimer, activeReference = '', lastPayload = '', lookupVersion = 0, lookupController;
    let uploading = false;
    document.addEventListener('click', event => {
        const button = event.target.closest('[data-print-stub]');
        if (!button) return;
        document.querySelectorAll('.selected-stub').forEach(stub => stub.classList.remove('selected-stub'));
        button.closest('.payment-stub').classList.add('selected-stub');
        document.body.classList.add('printing-guidance-stub');
        window.print();
    });
    window.addEventListener('afterprint', () => {
        document.body.classList.remove('printing-guidance-stub');
        document.querySelectorAll('.selected-stub').forEach(stub => stub.classList.remove('selected-stub'));
    });
    document.addEventListener('submit', async event => {
        const upload = event.target.closest('[data-guidance-receipt]');
        if (!upload) return;
        event.preventDefault();
        uploading = true;
        clearTimeout(pollTimer);
        const button = upload.querySelector('button');
        const message = upload.querySelector('[data-upload-message]');
        if (button) button.disabled = true;
        if (message) message.textContent = 'Uploading receipt...';
        try {
            const formData = new FormData(upload);
            const fileInput = upload.querySelector('input[type="file"][name="payment_slip"], input[type="file"][name="proof"], input[type="file"]');
            if (fileInput && fileInput.files && fileInput.files[0]) {
                const selectedFile = fileInput.files[0];
                formData.set('payment_slip', selectedFile);
                formData.set('proof', selectedFile);
            }
            const orNumberInput = upload.querySelector('input[name="or_number"]');
            if (orNumberInput && orNumberInput.value.trim()) {
                formData.set('or_number', orNumberInput.value.trim());
            }
            const orDateInput = upload.querySelector('input[name="or_date"]');
            if (orDateInput && orDateInput.value.trim()) {
                formData.set('or_date', orDateInput.value.trim());
            }
            const refInput = upload.querySelector('input[name="reference"]');
            if (refInput && refInput.value.trim()) {
                formData.set('reference', refInput.value.trim());
            }

            const response = await fetch(upload.action, {method:'POST', body:formData, headers:{Accept:'application/json'}});
            const data = await response.json();
            if (!response.ok) throw new Error(Object.values(data.errors || {}).flat().join(' ') || data.message || 'Receipt upload failed.');
            if (message) message.textContent = data.message;
            if (refInput && form.querySelector('[name="reference"]')) {
                form.querySelector('[name="reference"]').value = refInput.value.trim();
            }
            form.requestSubmit();
        } catch(error) { if (message) message.textContent = error.message || 'Upload failed. Please retry.'; }
        finally { uploading = false; if (button) button.disabled = false; schedulePoll(); }
    });
    function schedulePoll(delay = 10000) {
        clearTimeout(pollTimer);
        pollTimer = null;
        if (activeReference) pollTimer = setTimeout(() => { pollTimer = null; lookup(true); }, delay);
    }
    async function lookup(background = false) {
        if (background && (document.hidden || uploading)) { schedulePoll(); return; }
        const reference = form.querySelector('[name="reference"]').value.trim();
        if (background && reference !== activeReference) { activeReference = ''; return; }
        lookupController?.abort();
        lookupController = new AbortController();
        const version = ++lookupVersion;
        clearTimeout(pollTimer);
        pollTimer = null;
        activeReference = reference;
        const output = document.getElementById('tracking-result');
        const passes = document.getElementById('tracking-passes');
        const button = form.querySelector('button');
        if (!background) { button.disabled = true; passes.replaceChildren(); lastPayload = ''; output.textContent = 'Looking up your request…'; }
        try {
            const response = await fetch(form.getAttribute('action'), {method:'POST', body:new FormData(form), headers:{Accept:'application/json'}, cache:'no-store', signal:lookupController.signal});
            const data = await response.json();
            if (version !== lookupVersion) return;
            if (response.status === 429) { schedulePoll(60000); throw new Error('Too many lookups. Retrying in a minute.'); }
            if (response.status === 404 || response.status === 422) activeReference = '';
            if (!response.ok) throw new Error(response.status === 429 ? 'Too many lookups. Try again in a minute.' : data.message || 'Unable to look up your request.');
            output.textContent = 'Request status: ' + data.status;
            if (data.status === 'Completed' || data.passes.length === 0) activeReference = '';
            const payload = JSON.stringify(data);
            // Preserve a chosen receipt file, keyboard focus and the QR image on unchanged polls.
            if (payload === lastPayload) return;
            if (background && passes.querySelector('input[type="file"]')?.files.length) return;
            lastPayload = payload;
            passes.replaceChildren();
            if (data.details_html) passes.innerHTML = data.details_html;
            const categoryLabels = {
                'psychological': 'Psychological Assessment',
                'personality': 'Personality Test',
                'career': 'Career Test'
            };
            for (const pass of data.passes) {
                const card = document.createElement('section'); card.className = 'card';
                const headingText = pass.category_label || categoryLabels[pass.category] || pass.test;
                const heading = document.createElement('h3'); heading.textContent = headingText;
                const state = document.createElement('p'); state.textContent = pass.status;
                card.append(heading, state);
                if (pass.appointment_at) {
                    const when = document.createElement('p'); when.textContent = 'Appointment: '+new Date(pass.appointment_at).toLocaleString(); card.append(when);
                }
                if (pass.direct_test_link) {
                    const image = document.createElement('img'); image.src = pass.qr_image; image.alt = headingText + ' assessment QR pass';
                    image.width = 260; image.height = 260; image.style.maxWidth = '100%';
                    const link = document.createElement('a'); link.href = pass.direct_test_link;
                    link.textContent = 'Click here if QR scanner is unavailable';
                    link.style.cssText = 'display:block;font-weight:bold;margin-top:12px';
                    card.append(image, link);
                } else if (['Pending Payment', 'Receipt Uploaded'].includes(pass.status)) {
                    const note = document.createElement('p'); note.textContent = data.receipt_uploaded ? 'Receipt received. Awaiting guidance staff verification.' : 'Upload your payment receipt in this tracking section after payment.'; card.append(note);
                }
                passes.append(card);
            }
        } catch(error) { if (error.name !== 'AbortError' && version === lookupVersion) output.textContent = error.message || 'Unable to connect. Please retry.'; }
        finally { if (version === lookupVersion) { button.disabled = false; if (!pollTimer) schedulePoll(); } }
    }
    form.addEventListener('submit', event => { event.preventDefault(); lookup(); });
    document.addEventListener('visibilitychange', () => { if (!document.hidden && activeReference) lookup(true); });
    const autoTrack = () => {
        if (form.querySelector('[name="reference"]').value.trim() && !form.querySelector('button').disabled) {
            form.requestSubmit();
        }
    };
    if (form.dataset.autoTrack === 'true' || window.location.hash === '#track') autoTrack();
    window.addEventListener('hashchange', () => {
        if (window.location.hash === '#track') autoTrack();
    });
})();
