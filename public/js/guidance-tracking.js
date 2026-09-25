(() => {
    const form = document.getElementById('guidance-track');
    if (!form) return;
    let pollTimer, activeReference = '', lastPayload = '', lookupVersion = 0, lookupController;
    let uploading = false;

    // ── Toast notification helper ──────────────────────────────────────────
    function showToast(message, type = 'success') {
        let toast = document.getElementById('portal-toast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'portal-toast';
            toast.className = 'portal-toast';
            toast.setAttribute('role', 'alert');
            toast.style.cssText = `
                position: fixed;
                top: 24px;
                right: 24px;
                z-index: 99999;
                color: #ffffff;
                padding: 14px 22px;
                border-radius: 10px;
                font-weight: 600;
                font-size: 14.5px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.3);
                transition: opacity 0.3s ease, transform 0.3s ease;
                opacity: 0;
                transform: translateY(-12px);
                pointer-events: none;
                max-width: 440px;
                line-height: 1.45;
                display: flex;
                align-items: center;
                gap: 10px;
            `;
            document.body.appendChild(toast);
        }

        const isSuccess = type === 'success';
        const isError = type === 'error' || type === 'danger';
        const isWarn = type === 'warning' || type === 'warn';

        const bg = isSuccess ? '#166534' : isError ? '#991b1b' : isWarn ? '#b45309' : '#1e3a8a';
        const icon = isSuccess ? '✓' : isError ? '⚠️' : isWarn ? '⚠️' : 'ℹ️';

        toast.style.background = bg;
        toast.innerHTML = `<span style="font-size:1.15em;line-height:1;">${icon}</span> <span>${escapeHtml(message)}</span>`;
        toast.style.opacity = '1';
        toast.style.transform = 'translateY(0)';

        clearTimeout(toast._timer);
        toast._timer = setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(-12px)';
        }, 5000);
    }
    window.showPortalToast = showToast;

    function escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    // ── Helper: Show Alert Banner in Form ───────────────────────────────────
    function renderAlertBanner(container, message, type = 'success') {
        let banner = container.querySelector('[data-upload-alert], .upload-alert-banner');
        if (!banner) {
            banner = document.createElement('div');
            banner.className = 'upload-alert-banner';
            banner.setAttribute('role', 'alert');
            const submitBtn = container.querySelector('button');
            if (submitBtn && submitBtn.parentNode) {
                submitBtn.parentNode.insertBefore(banner, submitBtn.nextSibling);
            } else {
                container.appendChild(banner);
            }
        }

        const isSuccess = type === 'success';
        banner.style.cssText = `
            display: block;
            margin-top: 14px;
            padding: 12px 16px;
            border-radius: 8px;
            font-size: 14px;
            line-height: 1.45;
            font-weight: 500;
            background: ${isSuccess ? '#f0fdf4' : '#fef2f2'};
            border: 1px solid ${isSuccess ? '#86efac' : '#fca5a5'};
            color: ${isSuccess ? '#166534' : '#991b1b'};
        `;
        banner.innerHTML = `<strong>${isSuccess ? '✓ Success:' : '⚠️ Notice:'}</strong> ${escapeHtml(message)}`;
    }

    function clearAlertBanner(container) {
        const banner = container.querySelector('[data-upload-alert], .upload-alert-banner');
        if (banner) {
            banner.style.display = 'none';
            banner.textContent = '';
        }
    }

    // ── Helper: Show/Clear Field Error ─────────────────────────────────────
    function showFieldError(formEl, fieldName, message) {
        const field = formEl.querySelector(`[name="${fieldName}"]`);
        if (!field) return;

        field.style.borderColor = '#dc2626';
        let errorEl = field.parentElement ? field.parentElement.querySelector(`.field-error-${fieldName}`) : null;
        if (!errorEl) {
            errorEl = document.createElement('div');
            errorEl.className = `field-error field-error-${fieldName}`;
            errorEl.style.cssText = 'color:#dc2626;font-size:13px;font-weight:500;margin-top:4px;display:block;';
            if (field.nextSibling) {
                field.parentElement.insertBefore(errorEl, field.nextSibling);
            } else {
                field.parentElement.appendChild(errorEl);
            }
        }
        errorEl.textContent = message;
        errorEl.style.display = 'block';

        const clearFn = () => {
            field.style.borderColor = '';
            errorEl.style.display = 'none';
            field.removeEventListener('input', clearFn);
            field.removeEventListener('change', clearFn);
        };
        field.addEventListener('input', clearFn);
        field.addEventListener('change', clearFn);
    }

    function clearAllFieldErrors(formEl) {
        formEl.querySelectorAll('.field-error').forEach(el => {
            el.style.display = 'none';
            el.textContent = '';
        });
        formEl.querySelectorAll('input, select, textarea').forEach(el => {
            el.style.borderColor = '';
        });
    }

    // ── Helper: Update Status Badges in DOM ─────────────────────────────────
    function updateStatusBadges(statusText) {
        document.querySelectorAll('.tracking-status-badge, [data-tracking-status]').forEach(el => {
            el.textContent = statusText;
            el.style.color = '#2563eb';
        });
        const output = document.getElementById('tracking-result');
        if (output) {
            output.textContent = 'Request status: ' + statusText;
        }
    }

    // ── Stub download gates verification ───────────────────────────────────
    function checkStubDownloadGates() {
        document.querySelectorAll('.stub-download-gate').forEach(gate => {
            const ref = gate.dataset.gateRef || activeReference;
            const isDownloaded = ref ? localStorage.getItem('stub_downloaded_' + ref) === 'true' : false;
            const lockMsg = gate.querySelector('.stub-download-lock-msg');
            const uploadBox = gate.querySelector('.receipt-upload-container');

            if (isDownloaded) {
                if (lockMsg) lockMsg.style.display = 'none';
                if (uploadBox) {
                    uploadBox.style.display = 'block';
                    uploadBox.querySelectorAll('input, select, button').forEach(el => el.disabled = false);
                }
            } else {
                if (lockMsg) lockMsg.style.display = 'block';
                if (uploadBox) {
                    uploadBox.style.display = 'none';
                    uploadBox.querySelectorAll('input, select, button').forEach(el => el.disabled = true);
                }
            }
        });
    }

    // ── Print / Download stub event listener ──────────────────────────────
    document.addEventListener('click', event => {
        const button = event.target.closest('[data-download-stub], [data-print-stub]');
        if (!button) return;
        const stub = button.closest('.payment-stub') || document.getElementById('payment-stub');
        const ref = button.dataset.ref || (form.querySelector('[name="reference"]')?.value.trim() || activeReference);

        if (ref) {
            localStorage.setItem('stub_downloaded_' + ref, 'true');
        }

        document.querySelectorAll('.selected-stub').forEach(s => s.classList.remove('selected-stub'));
        if (stub) stub.classList.add('selected-stub');
        document.body.classList.add('printing-guidance-stub');
        window.print();

        setTimeout(() => {
            checkStubDownloadGates();
            showToast("Request Stub downloaded! Payment receipt upload unlocked.", 'success');
        }, 500);
    });

    window.addEventListener('afterprint', () => {
        document.body.classList.remove('printing-guidance-stub');
        document.querySelectorAll('.selected-stub').forEach(stub => stub.classList.remove('selected-stub'));
        checkStubDownloadGates();
    });

    // ── Receipt Upload Handler ─────────────────────────────────────────────
    document.addEventListener('submit', async event => {
        const upload = event.target.closest('[data-guidance-receipt]');
        if (!upload) return;
        event.preventDefault();

        clearAllFieldErrors(upload);
        clearAlertBanner(upload);

        const button = upload.querySelector('button[type="submit"], button');
        const originalButtonText = button ? (button.dataset.originalText || button.textContent) : 'UPLOAD STUB FOR VERIFICATION';
        if (button && !button.dataset.originalText) {
            button.dataset.originalText = originalButtonText;
        }

        const message = upload.querySelector('[data-upload-message]');
        if (message) message.textContent = '';

        // Client-side pre-validations
        const fileInput = upload.querySelector('input[type="file"][name="payment_slip"], input[type="file"][name="receipt"], input[type="file"][name="proof"], input[type="file"]');
        if (!fileInput || !fileInput.files || !fileInput.files[0]) {
            const err = "Please select a valid image file under 5 MB.";
            showFieldError(upload, fileInput ? fileInput.name : 'payment_slip', err);
            renderAlertBanner(upload, err, 'error');
            showToast(err, 'error');
            return;
        }

        const selectedFile = fileInput.files[0];
        const maxBytes = 5 * 1024 * 1024; // 5 MB
        if (selectedFile.size > maxBytes) {
            const err = "The selected file is too large. Please upload an image under 5 MB.";
            showFieldError(upload, fileInput.name, err);
            renderAlertBanner(upload, err, 'error');
            showToast(err, 'error');
            return;
        }

        // Validate file type
        const allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg', 'application/pdf'];
        const allowedExt = /\.(jpe?g|png|webp|pdf)$/i;
        if (!allowedTypes.includes(selectedFile.type) && !allowedExt.test(selectedFile.name)) {
            const err = "Invalid file type. Please upload a valid image (JPG, PNG, WebP) under 5 MB.";
            showFieldError(upload, fileInput.name, err);
            renderAlertBanner(upload, err, 'error');
            showToast(err, 'error');
            return;
        }

        // Validate OR Number & Date if present
        const orNumInput = upload.querySelector('input[name="or_number"]');
        if (orNumInput && orNumInput.hasAttribute('required') && !orNumInput.value.trim()) {
            const err = "Official Receipt (OR) Number is required.";
            showFieldError(upload, 'or_number', err);
            renderAlertBanner(upload, err, 'error');
            showToast(err, 'error');
            return;
        }

        const orDateInput = upload.querySelector('input[name="or_date"]');
        if (orDateInput && orDateInput.hasAttribute('required') && !orDateInput.value.trim()) {
            const err = "Receipt Date is required.";
            showFieldError(upload, 'or_date', err);
            renderAlertBanner(upload, err, 'error');
            showToast(err, 'error');
            return;
        }

        if (orDateInput && orDateInput.value.trim()) {
            const todayStr = new Date().toISOString().split('T')[0];
            if (orDateInput.value > todayStr) {
                const err = "Receipt Date cannot be in the future.";
                showFieldError(upload, 'or_date', err);
                renderAlertBanner(upload, err, 'error');
                showToast(err, 'error');
                return;
            }
        }

        // Form is valid — begin upload
        uploading = true;
        clearTimeout(pollTimer);
        if (button) {
            button.disabled = true;
            button.textContent = "Uploading receipt, please wait...";
        }

        try {
            const formData = new FormData(upload);
            if (selectedFile) {
                formData.set('payment_slip', selectedFile);
                formData.set('proof', selectedFile);
            }
            if (orNumInput && orNumInput.value.trim()) {
                formData.set('or_number', orNumInput.value.trim());
            }
            if (orDateInput && orDateInput.value.trim()) {
                formData.set('or_date', orDateInput.value.trim());
            }
            const refInput = upload.querySelector('input[name="reference"]');
            if (refInput && refInput.value.trim()) {
                formData.set('reference', refInput.value.trim());
            }

            let responseOk = false;
            let httpStatus = 0;
            let responseData = null;

            try {
                const response = await fetch(upload.action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                httpStatus = response.status;
                responseOk = response.ok;

                try {
                    const text = await response.text();
                    responseData = JSON.parse(text);
                } catch (parseErr) {
                    // Non-JSON HTML response (e.g. 500 error page or redirect)
                    responseData = null;
                }
            } catch (networkErr) {
                responseOk = false;
                responseData = null;
            }

            if (responseOk && (responseData === null || responseData.success !== false)) {
                // SUCCESS
                const successMsg = (responseData && responseData.message)
                    ? responseData.message
                    : "Receipt uploaded successfully! Your payment is now pending verification by Guidance Staff.";

                renderAlertBanner(upload, successMsg, 'success');
                showToast(successMsg, 'success');
                updateStatusBadges('Receipt Uploaded / Pending Verification');

                if (refInput && form.querySelector('[name="reference"]')) {
                    form.querySelector('[name="reference"]').value = refInput.value.trim();
                }

                // Hide inputs / replace form after short moment to reflect submitted state
                setTimeout(() => {
                    if (typeof lookup === 'function') {
                        lookup(false);
                    }
                }, 800);

            } else {
                // ERROR / VALIDATION FAILURE
                let friendlyErrorMessage = "Unable to process your upload at this time. Please make sure your file is a valid image (JPG, PNG, WebP) under 5 MB and try again.";

                if (responseData && responseData.errors) {
                    let firstError = '';
                    for (const [field, messages] of Object.entries(responseData.errors)) {
                        const msgText = Array.isArray(messages) ? messages[0] : String(messages);
                        if (!firstError) firstError = msgText;
                        showFieldError(upload, field, msgText);
                        // Also map aliases
                        if (field === 'payment_slip' || field === 'proof' || field === 'receipt') {
                            showFieldError(upload, 'payment_slip', msgText);
                            showFieldError(upload, 'proof', msgText);
                            showFieldError(upload, 'receipt', msgText);
                        }
                    }
                    if (firstError) {
                        friendlyErrorMessage = firstError;
                    } else if (responseData.message) {
                        friendlyErrorMessage = responseData.message;
                    }
                } else if (responseData && responseData.message && !responseData.message.includes('SQL') && !responseData.message.includes('Exception') && !responseData.message.includes('Stack trace')) {
                    friendlyErrorMessage = responseData.message;
                } else if (httpStatus === 413) {
                    friendlyErrorMessage = "The uploaded file is too large. Please select an image under 5 MB.";
                } else if (httpStatus === 422) {
                    friendlyErrorMessage = "Please make sure your file is a valid image (JPG, PNG, WebP) under 5 MB and all fields are complete.";
                } else if (httpStatus === 419) {
                    friendlyErrorMessage = "Your session has expired. Please refresh the page and try again.";
                }

                renderAlertBanner(upload, friendlyErrorMessage, 'error');
                showToast(friendlyErrorMessage, 'error');
            }
        } catch(error) {
            const fallbackMsg = "Unable to process your upload at this time. Please make sure your file is a valid image (JPG, PNG, WebP) under 5 MB and try again.";
            renderAlertBanner(upload, fallbackMsg, 'error');
            showToast(fallbackMsg, 'error');
        } finally {
            uploading = false;
            if (button) {
                button.disabled = false;
                button.textContent = button.dataset.originalText || 'UPLOAD STUB FOR VERIFICATION';
            }
            schedulePoll();
        }
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
            const response = await fetch(form.getAttribute('action'), {
                method:'POST',
                body:new FormData(form),
                headers:{
                    'Accept':'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                cache:'no-store',
                signal:lookupController.signal
            });

            let data = null;
            try {
                const raw = await response.text();
                data = JSON.parse(raw);
            } catch (err) {
                data = null;
            }

            if (version !== lookupVersion) return;
            if (response.status === 429) { schedulePoll(60000); throw new Error('Too many lookups. Retrying in a minute.'); }
            if (response.status === 404 || response.status === 422) activeReference = '';
            if (!response.ok || !data) throw new Error(response.status === 429 ? 'Too many lookups. Try again in a minute.' : (data && data.message) || 'Unable to look up your request. Please verify your reference number and try again.');
            
            output.textContent = 'Request status: ' + data.status;
            if (data.status === 'Completed' || data.passes.length === 0) activeReference = '';
            const payload = JSON.stringify(data);
            if (payload === lastPayload) {
                checkStubDownloadGates();
                return;
            }
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
                } else if (['Pending Payment', 'Receipt Uploaded', 'Receipt Uploaded / Pending Verification'].includes(pass.status)) {
                    const note = document.createElement('p'); note.textContent = data.receipt_uploaded ? 'Receipt received. Awaiting guidance staff verification.' : 'Upload your payment receipt in this tracking section after payment.'; card.append(note);
                }
                passes.append(card);
            }
            checkStubDownloadGates();
        } catch(error) { if (error.name !== 'AbortError' && version === lookupVersion) output.textContent = error.message || 'Unable to connect. Please retry.'; }
        finally { if (version === lookupVersion) { button.disabled = false; if (!pollTimer) schedulePoll(); checkStubDownloadGates(); } }
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

    // Run initial gate check
    checkStubDownloadGates();
})();
