import {detectAnswers} from './admission-omr.js';

const root = document.getElementById('paper-scanner');
const byId = id => document.getElementById(id);
const canvas = byId('sheet'), context = canvas.getContext('2d', {willReadFrequently:true});
const message = byId('message'), video = byId('preview'), form = byId('answers'), videoOverlay = byId('video-overlay');
// Read the active cycle's total item count injected by scanner.blade.php
const TOTAL_ITEMS = parseInt(root.dataset.totalItems, 10) || 80;
let stream, pixels, points = [], applicant, generation = 0;
const stop = () => { stream?.getTracks().forEach(track=>track.stop()); };
window.addEventListener('pagehide', stop);
function clearIdentity() {
    applicant = null;
    form.removeAttribute('action');
    byId('record').disabled = true;
    byId('confirm-match').checked = false;
    byId('identity-panel').classList.add('d-none');
    byId('identity-placeholder').classList.remove('d-none');
    byId('identity-placeholder').textContent = 'No verified QR for this paper. Capture or upload a complete sheet with a readable QR code.';
    for (const id of ['applicant-name', 'applicant-session', 'applicant-batch', 'applicant-app-no']) byId(id).textContent = '';
}
function newPaper() {
    generation++;
    clearIdentity();
    pixels = null;
    context.clearRect(0, 0, canvas.width, canvas.height);
    reset();
    return generation;
}
async function identify(code, version) {
    try {
        const response = await fetch(root.dataset.lookup, {method:'POST', headers:{'Content-Type':'application/json', Accept:'application/json','X-CSRF-TOKEN':root.dataset.csrf}, body:JSON.stringify({code})});
        if (version !== generation) return;
        if (!response.ok) throw new Error(response.status === 409 ? 'This paper cannot be recorded: the applicant already submitted an exam or there is no active cycle.' : 'QR not verified. No matching applicant in the active admission cycle.');
        const matchedApplicant = await response.json();
        if (version !== generation) return;
        applicant = matchedApplicant;
        byId('applicant-name').textContent = applicant.name;
        byId('applicant-session').textContent = applicant.session || 'Not assigned';
        byId('applicant-batch').textContent = applicant.batch || 'Not assigned';
        byId('applicant-app-no').textContent = applicant.application_number;
        byId('identity-placeholder').classList.add('d-none');
        byId('identity-panel').classList.remove('d-none');
        form.action = applicant.url;
        byId('record').disabled = form.hidden;
        message.textContent = 'QR verified. Select the four corner markers and review the detected answers before saving.';
    } catch (error) {
        if (version !== generation) return;
        clearIdentity();
        message.textContent = error.message;
    }
}
byId('start-camera').onclick = async () => {
    stop();
    try {
        stream = await navigator.mediaDevices.getUserMedia({video:{facingMode:'environment',width:{ideal:1920},height:{ideal:1080}},audio:false});
        video.srcObject = stream; await video.play(); byId('capture').disabled = false;
        videoOverlay?.classList.add('d-none');
        message.textContent = 'Place the complete paper in view, including its QR code, then capture the sheet.';
    } catch { message.textContent = 'Camera unavailable. Use HTTPS or localhost, allow camera access, or upload a photograph.'; }
};
async function capture(source, width, height, version) {
    if (version !== generation) return;
    if (width < 800 || height < 450) throw new Error('Use an image at least 800 x 450 pixels.');
    const scale = Math.min(1, 2400/Math.max(width,height));
    canvas.width = Math.round(width*scale); canvas.height = Math.round(height*scale);
    context.drawImage(source,0,0,canvas.width,canvas.height);
    pixels = context.getImageData(0,0,canvas.width,canvas.height); reset();
    const qr = window.jsQR(pixels.data, pixels.width, pixels.height, {inversionAttempts:'attemptBoth'});
    if (!qr?.data?.trim()) throw new Error('QR not detected. Capture or upload a clearer image with the complete QR visible. Saving is disabled.');
    message.textContent = 'Verifying the QR on this paper...';
    await identify(qr.data.trim(), version);
}
function reset() {
    points = []; byId('confirm-match').checked = false; form.hidden = true; byId('record').disabled = true; byId('answer-review').replaceChildren();
    if (pixels) context.putImageData(pixels,0,0);
    message.textContent = 'Select the top-left marker, then top-right, bottom-right, bottom-left.';
}
byId('reset-points').onclick = reset;
byId('capture').onclick = async () => {
    const version = newPaper();
    try { await capture(video,video.videoWidth,video.videoHeight,version); }
    catch(error) { if (version === generation) message.textContent = error.message; }
};
byId('photo').onchange = async event => {
    const file = event.target.files[0]; if (!file) return;
    const version = newPaper();
    try {
        if (!['image/png','image/jpeg','image/webp'].includes(file.type) || file.size > 20*1024*1024) throw new Error('Choose a JPG, PNG, or WebP image under 20 MB.');
        const bitmap = await createImageBitmap(file);
        try { await capture(bitmap,bitmap.width,bitmap.height,version); } finally { bitmap.close(); }
    } catch(error) { if (version === generation) message.textContent = error.message; }
};
canvas.onclick = event => {
    if (!pixels || points.length === 4) return;
    const rect = canvas.getBoundingClientRect();
    const point = [(event.clientX-rect.left)*canvas.width/rect.width,(event.clientY-rect.top)*canvas.height/rect.height];
    points.push(point); context.fillStyle = '#e11'; context.beginPath(); context.arc(...point,5,0,Math.PI*2); context.fill();
    if (points.length < 4) { message.textContent = `Marker ${points.length} selected. Select marker ${points.length+1}.`; return; }
    try {
        const answers = detectAnswers(pixels, points, TOTAL_ITEMS);
        for (const result of answers) {
            const label = document.createElement('label'); label.className = 'col-6 col-md-3';
            label.textContent = `Item ${result.item}: ${result.reason}`;
            const select = document.createElement('select'); select.name = `answers[${result.item}]`; select.required = true;
            select.className = `form-select ${result.answer ? '' : 'border-warning'}`;
            for (const [value,text] of [['','Review required'],['blank','Confirmed blank'],...Array.from('ABCD',letter=>[letter,letter])]) select.add(new Option(text,value));
            select.value = result.answer ?? ''; label.append(select); byId('answer-review').append(label);
        }
        form.hidden = false; byId('record').disabled = !applicant;
        message.textContent = `${answers.filter(row=>!row.answer).length} of ${TOTAL_ITEMS} rows require review. Review all answers before saving. A verified paper QR is required.`;
    } catch(error) { message.textContent = error.message; points = []; }
};
form.addEventListener('submit', event => {
    if (!applicant) { event.preventDefault(); return; }
    // Use empty values for explicitly reviewed blank answers; Laravel converts them to null.
    for (const select of form.querySelectorAll('select')) if (select.value === 'blank') { select.selectedOptions[0].value = ''; select.required = false; }
    byId('record').disabled = true; stop();
});
