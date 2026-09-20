(() => {
    const waiting = document.getElementById('batch-wait');
    if (waiting) {
        const checkState = async () => {
            try {
                const response = await fetch(waiting.dataset.state, {headers:{Accept:'application/json'},cache:'no-store'});
                if (!response.ok) throw new Error('Unable to check the session. Refresh to verify your identity if this persists.');
                const state = await response.json();
                if (state.url) { location.replace(state.url); return; }
                waiting.textContent = state.attendance === 'Ready' && state.status !== 'Completed' ? 'Ready in Session. Waiting for the proctor to start the assessment.' : state.attendance;
                if (state.status === 'Completed' || state.attendance === 'Absent') return;
            } catch (error) { waiting.textContent = error.message; }
            setTimeout(checkState, 2000);
        };
        checkState();

        if (window.Echo && waiting.dataset.batchId) {
            try {
                window.Echo.private(`guidance.batch.${waiting.dataset.batchId}`)
                    .listen('.guidance.batch.started', () => {
                        checkState();
                    });
            } catch (_) {}
        }
    }
    const file = document.getElementById('receipt-file');
    if (!file) return;
    const video = document.getElementById('receipt-camera'), capture = document.getElementById('camera-capture'), message = document.getElementById('camera-message'), preview = document.getElementById('receipt-preview');
    let stream, previewUrl;
    const stop = () => { stream?.getTracks().forEach(track => track.stop()); video.srcObject = null; video.hidden = capture.hidden = true; };
    const showPreview = () => { if (previewUrl) URL.revokeObjectURL(previewUrl); if (file.files[0]) { preview.src = previewUrl = URL.createObjectURL(file.files[0]); preview.hidden = false; } };
    document.getElementById('camera-start').onclick = async () => {
        stop();
        try {
            if (!navigator.mediaDevices?.getUserMedia) throw new Error('Camera requires HTTPS or localhost. Use Upload Receipt File.');
            stream = await navigator.mediaDevices.getUserMedia({video:{facingMode:{ideal:'environment'}},audio:false});
            video.srcObject = stream; video.hidden = capture.hidden = false; message.textContent = '';
        } catch (error) { message.textContent = `${error.message} You can upload an image instead.`; }
    };
    document.getElementById('file-choice').onclick = () => { stop(); file.click(); };
    file.onchange = () => { stop(); showPreview(); };
    capture.onclick = () => {
        if (!video.videoWidth) return;
        const canvas = document.getElementById('receipt-canvas');
        const scale = Math.min(1, 2000 / Math.max(video.videoWidth, video.videoHeight));
        canvas.width = Math.round(video.videoWidth * scale); canvas.height = Math.round(video.videoHeight * scale);
        canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
        canvas.toBlob(blob => {
            if (!blob) { message.textContent = 'Capture failed. Please retry or upload a file.'; return; }
            try { const transfer = new DataTransfer(); transfer.items.add(new File([blob], 'receipt.jpg', {type:'image/jpeg'})); file.files = transfer.files; showPreview(); stop(); }
            catch (_) { message.textContent = 'This browser cannot attach camera captures. Use Upload Receipt File.'; stop(); }
        }, 'image/jpeg', 0.85);
    };
    window.addEventListener('pagehide', stop);
})();
