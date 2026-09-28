import {detectAnswers} from './admission-omr.js';

const root = document.getElementById('session-omr-scanner');

if (root) {
    const get = id => document.getElementById(id);
    const modalElement = get('omrScannerModal');
    const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
    const video = get('session-omr-video');
    const canvas = get('session-omr-canvas');
    const context = canvas.getContext('2d', {willReadFrequently: true});
    const guide = get('session-omr-guide');
    const appNumber = get('session-omr-app-number');
    const identity = get('session-omr-identity');
    const review = get('session-omr-review');
    const message = get('session-omr-message');
    const submit = get('session-omr-submit');
    const captureButton = get('session-omr-capture');
    const totalItems = Number(root.dataset.totalItems) || 80;
    const roster = new Map(
        [...document.querySelectorAll('tr[data-application-number]')]
            .map(row => [row.dataset.applicationNumber.toUpperCase(), row])
    );

    let stream = null;
    let qrTimer = null;
    let imageData = null;
    let points = [];
    let selectedApplication = null;
    let recognizedAnswers = null;
    let lookupBusy = false;

    function setMessage(text, type = 'info') {
        message.className = `alert alert-${type} py-2 small mt-3 mb-2`;
        message.textContent = text;
    }

    function stopCamera() {
        clearTimeout(qrTimer);
        qrTimer = null;
        stream?.getTracks().forEach(track => track.stop());
        stream = null;
        video.srcObject = null;
        captureButton.disabled = true;
    }

    function normalizePayload(payload) {
        const value = String(payload || '').trim();
        if (roster.has(value.toUpperCase())) return value.toUpperCase();
        try {
            const url = new URL(value);
            const candidate = url.searchParams.get('application_number') || url.searchParams.get('code');
            if (candidate && roster.has(candidate.toUpperCase())) return candidate.toUpperCase();
        } catch {
            // The printed QR normally contains the application number directly.
        }
        return value.toUpperCase();
    }

    function matchApplicant(payload, announce = true) {
        const code = normalizePayload(payload);
        const row = roster.get(code);
        if (!row) {
            selectedApplication = null;
            identity.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle me-1"></i>The scanned application number is not in this session.</span>';
            submit.disabled = true;
            if (announce) setMessage('Identity mismatch. Scan a sheet assigned to this session.', 'danger');
            return false;
        }

        const name = row.children[2]?.querySelector('.fw-semibold')?.textContent.trim() || 'Examinee';
        selectedApplication = code;
        appNumber.value = code;
        identity.innerHTML = `<div class="alert alert-success py-2 mb-0"><i class="bi bi-person-check-fill me-1"></i><strong>${escapeHtml(name)}</strong><br><span class="font-monospace">${escapeHtml(code)}</span></div>`;
        submit.disabled = !recognizedAnswers;
        if (announce) setMessage('Identity matched. Capture the complete bubble grid.', 'success');
        return true;
    }

    function escapeHtml(value) {
        const node = document.createElement('span');
        node.textContent = value;
        return node.innerHTML;
    }

    async function startCamera() {
        stopCamera();
        try {
            stream = await navigator.mediaDevices.getUserMedia({
                video: {facingMode: 'environment', width: {ideal: 1920}, height: {ideal: 1080}},
                audio: false,
            });
            video.srcObject = stream;
            await video.play();
            guide.classList.add('d-none');
            captureButton.disabled = false;
            setMessage('Camera active. Scan the header QR, then capture the full answer grid.');

            if (!('BarcodeDetector' in window)) {
                setMessage('Automatic QR detection is unavailable in this browser. Use Scan OMR beside an examinee or enter the application number, then capture the grid.', 'warning');
                return;
            }

            const detector = new BarcodeDetector({formats: ['qr_code']});
            const detectQr = async () => {
                if (!stream?.active) return;
                try {
                    const codes = await detector.detect(video);
                    if (codes.length && !lookupBusy) {
                        lookupBusy = true;
                        matchApplicant(codes[0].rawValue);
                        lookupBusy = false;
                    }
                } catch {
                    // A missed or undecodable video frame is expected while aligning paper.
                }
                qrTimer = setTimeout(detectQr, 500);
            };
            detectQr();
        } catch {
            setMessage('Camera unavailable. Allow camera permission on HTTPS, or upload a clear answer-sheet photo.', 'danger');
        }
    }

    function loadImage(source, width, height) {
        if (width < 800 || height < 450) throw new Error('Use an image at least 800 × 450 pixels.');
        const scale = Math.min(1, 2400 / Math.max(width, height));
        canvas.width = Math.round(width * scale);
        canvas.height = Math.round(height * scale);
        context.drawImage(source, 0, 0, canvas.width, canvas.height);
        imageData = context.getImageData(0, 0, canvas.width, canvas.height);
        canvas.classList.remove('d-none');
        resetPoints();
    }

    function resetPoints() {
        points = [];
        recognizedAnswers = null;
        review.replaceChildren();
        submit.disabled = true;
        if (imageData) context.putImageData(imageData, 0, 0);
        setMessage(imageData
            ? 'Select markers in order: top-left, top-right, bottom-right, bottom-left.'
            : 'Capture or upload the complete horizontal answer sheet.');
    }

    function renderReview(results) {
        review.replaceChildren();
        recognizedAnswers = {};
        for (const result of results) {
            const wrapper = document.createElement('div');
            wrapper.className = 'col-6 col-md-3';
            const label = document.createElement('label');
            label.className = 'form-label small mb-1';
            label.textContent = `Item ${result.item}`;
            const select = document.createElement('select');
            select.className = `form-select form-select-sm ${result.answer ? '' : 'border-warning'}`;
            select.dataset.item = result.item;
            for (const [value, text] of [['', 'Blank'], ['A', 'A'], ['B', 'B'], ['C', 'C'], ['D', 'D']]) {
                select.add(new Option(text, value));
            }
            select.value = result.answer || '';
            recognizedAnswers[result.item] = result.answer || null;
            select.addEventListener('change', () => {
                recognizedAnswers[result.item] = select.value || null;
            });
            wrapper.append(label, select);
            review.append(wrapper);
        }
        submit.disabled = !selectedApplication;
        const ambiguous = results.filter(result => !result.answer).length;
        setMessage(`${totalItems - ambiguous} answers detected; ${ambiguous} blank or ambiguous item(s) require review.`, ambiguous ? 'warning' : 'success');
    }

    canvas.addEventListener('click', event => {
        if (!imageData || points.length >= 4) return;
        const bounds = canvas.getBoundingClientRect();
        const point = [
            (event.clientX - bounds.left) * canvas.width / bounds.width,
            (event.clientY - bounds.top) * canvas.height / bounds.height,
        ];
        points.push(point);
        context.fillStyle = '#dc3545';
        context.beginPath();
        context.arc(point[0], point[1], 7, 0, Math.PI * 2);
        context.fill();

        if (points.length < 4) {
            setMessage(`Marker ${points.length} selected. Select marker ${points.length + 1}.`);
            return;
        }

        try {
            renderReview(detectAnswers(imageData, points, totalItems));
        } catch (error) {
            setMessage(error.message, 'danger');
            points = [];
            context.putImageData(imageData, 0, 0);
        }
    });

    async function submitAnswers() {
        if (!selectedApplication || !recognizedAnswers) return;
        submit.disabled = true;
        setMessage('Scoring and recording the OMR sheet…');
        try {
            const response = await fetch(root.dataset.submitUrl, {
                method: 'POST',
                headers: {'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': root.dataset.csrf},
                body: JSON.stringify({
                    application_number: selectedApplication,
                    session_id: Number(root.dataset.sessionId),
                    raw_choices: recognizedAnswers,
                }),
            });
            const data = await response.json();
            if (!response.ok) {
                const validation = data.errors ? Object.values(data.errors).flat()[0] : null;
                throw new Error(validation || data.message || 'Unable to record the OMR sheet.');
            }
            updateRoster(data);
            setMessage(data.message, 'success');
            stopCamera();
            setTimeout(() => modal.hide(), 900);
        } catch (error) {
            setMessage(error.message, 'danger');
            submit.disabled = false;
        }
    }

    function updateRoster(data) {
        const result = data.applicant;
        const row = roster.get(result.application_number.toUpperCase());
        if (!row) return;
        row.querySelector('.js-exam-status').innerHTML = `<span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bi bi-check-lg me-1"></i>Submitted</span><div class="text-muted small" style="font-size:11px">${escapeHtml(result.submitted_at)}</div>`;
        row.querySelector('.js-score-stanine').innerHTML = `<div class="fw-bold text-dark font-monospace">${Number(result.raw_score).toFixed(2)} / ${result.total_items}</div><div class="text-muted small" style="font-size:11px">Stanine: <strong>${result.stanine_rating}</strong></div>`;
        row.querySelector('.js-open-omr')?.remove();
        get('session-submitted-count').textContent = data.session.submitted_count;
        get('session-pending-count').textContent = data.session.pending_count;
        get('session-average-score').textContent = data.session.average_score;
    }

    document.querySelectorAll('.js-open-omr').forEach(button => {
        button.addEventListener('click', () => {
            resetPoints();
            selectedApplication = null;
            appNumber.value = button.dataset.applicationNumber || '';
            identity.textContent = 'No examinee identified.';
            if (appNumber.value) matchApplicant(appNumber.value, false);
            modal.show();
        });
    });

    get('session-omr-start').addEventListener('click', startCamera);
    get('session-omr-capture').addEventListener('click', () => {
        try { loadImage(video, video.videoWidth, video.videoHeight); }
        catch (error) { setMessage(error.message, 'danger'); }
    });
    get('session-omr-reset').addEventListener('click', resetPoints);
    get('session-omr-match').addEventListener('click', () => matchApplicant(appNumber.value));
    appNumber.addEventListener('keydown', event => {
        if (event.key === 'Enter') { event.preventDefault(); matchApplicant(appNumber.value); }
    });
    get('session-omr-file').addEventListener('change', async event => {
        const file = event.target.files[0];
        if (!file) return;
        try {
            if (!['image/png', 'image/jpeg', 'image/webp'].includes(file.type) || file.size > 20 * 1024 * 1024) {
                throw new Error('Choose a JPG, PNG, or WebP image under 20 MB.');
            }
            const bitmap = await createImageBitmap(file);
            try { loadImage(bitmap, bitmap.width, bitmap.height); }
            finally { bitmap.close(); }
        } catch (error) { setMessage(error.message, 'danger'); }
    });
    submit.addEventListener('click', submitAnswers);
    modalElement.addEventListener('hidden.bs.modal', stopCamera);
    window.addEventListener('pagehide', stopCamera);
}
