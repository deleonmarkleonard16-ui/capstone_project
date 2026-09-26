import {detectAnswers} from './admission-omr.js';

const root = document.getElementById('paper-scanner');
const byId = id => document.getElementById(id);
const canvas = byId('sheet'), context = canvas.getContext('2d', {willReadFrequently:true});
const message = byId('message'), video = byId('preview'), form = byId('answers'), videoOverlay = byId('video-overlay');
// Read the active cycle's total item count injected by scanner.blade.php
const TOTAL_ITEMS = parseInt(root.dataset.totalItems, 10) || 80;
let stream, pixels, points = [], applicant, timer, lookupBusy = false;
const stop = () => { clearTimeout(timer); stream?.getTracks().forEach(track=>track.stop()); };
window.addEventListener('pagehide', stop);
async function identify(code) {
    if (lookupBusy) return;
    lookupBusy = true; applicant = null; byId('record').disabled = true; byId('identity').textContent = '';
    try {
        const response = await fetch(root.dataset.lookup, {method:'POST', headers:{'Content-Type':'application/json', Accept:'application/json','X-CSRF-TOKEN':root.dataset.csrf}, body:JSON.stringify({code})});
        if (!response.ok) throw new Error(response.status === 409 ? 'This applicant already has a submission.' : 'Applicant lookup failed. Check the active cycle and application number.');
        applicant = await response.json();
        byId('identity').textContent = `${applicant.name} — ${applicant.application_number}`;
        byId('code').value = applicant.application_number;
        form.action = applicant.url;
        byId('record').disabled = form.hidden;
        message.textContent = 'Applicant identified. Capture and review their sheet.';
    } catch (error) { message.textContent = error.message; }
    finally { lookupBusy = false; }
}
byId('lookup').addEventListener('submit', event => { event.preventDefault(); identify(byId('code').value.trim()); });
byId('code').addEventListener('input', () => { applicant = null; byId('identity').textContent = ''; byId('record').disabled = true; });
byId('start-camera').onclick = async () => {
    videoOverlay?.classList.add('d-none');
    stop();
    try {
        stream = await navigator.mediaDevices.getUserMedia({video:{facingMode:'environment',width:{ideal:1920},height:{ideal:1080}},audio:false});
        video.srcObject = stream; await video.play(); byId('capture').disabled = false;
        if (!('BarcodeDetector' in window)) { message.textContent = 'Use the application number field to identify the applicant; camera capture is available.'; return; }
        const detector = new BarcodeDetector({formats:['qr_code']});
        const tick = async () => {
            try { const codes = await detector.detect(video); if (codes.length && !applicant) await identify(codes[0].rawValue); }
            catch { /* Keep capture available if the browser cannot decode this frame. */ }
            if (stream?.active) timer = setTimeout(tick, 600);
        };
        tick();
    } catch { message.textContent = 'Camera unavailable. Use HTTPS or localhost, allow camera access, or upload a photograph.'; }
};
function capture(source, width, height) {
    if (width < 800 || height < 450) throw new Error('Use an image at least 800 × 450 pixels.');
    const scale = Math.min(1, 2400/Math.max(width,height));
    canvas.width = Math.round(width*scale); canvas.height = Math.round(height*scale);
    context.drawImage(source,0,0,canvas.width,canvas.height);
    pixels = context.getImageData(0,0,canvas.width,canvas.height); reset();
}
function reset() {
    points = []; form.hidden = true; byId('record').disabled = true; byId('answer-review').replaceChildren();
    if (pixels) context.putImageData(pixels,0,0);
    message.textContent = 'Select the top-left marker, then top-right, bottom-right, bottom-left.';
}
byId('reset-points').onclick = reset;
byId('capture').onclick = () => { try { capture(video,video.videoWidth,video.videoHeight); } catch(error) { message.textContent = error.message; } };
byId('photo').onchange = async event => {
    const file = event.target.files[0]; if (!file) return;
    try {
        if (!['image/png','image/jpeg','image/webp'].includes(file.type) || file.size > 20*1024*1024) throw new Error('Choose a JPG, PNG, or WebP image under 20 MB.');
        const bitmap = await createImageBitmap(file);
        try { capture(bitmap,bitmap.width,bitmap.height); } finally { bitmap.close(); }
    } catch(error) { message.textContent = error.message; }
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
        message.textContent = `${answers.filter(row=>!row.answer).length} of ${TOTAL_ITEMS} rows require review. Confirm the applicant and all answers before saving.`;
    } catch(error) { message.textContent = error.message; points = []; }
};
form.addEventListener('submit', event => {
    if (!applicant) { event.preventDefault(); return; }
    // Use empty values for explicitly reviewed blank answers; Laravel converts them to null.
    for (const select of form.querySelectorAll('select')) if (select.value === 'blank') { select.selectedOptions[0].value = ''; select.required = false; }
    byId('record').disabled = true; stop();
});
