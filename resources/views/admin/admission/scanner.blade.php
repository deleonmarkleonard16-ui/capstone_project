@extends('layouts.app')
@section('content')
<h1>Scan paper answer sheet</h1><p>Point a webcam at the applicant QR or enter the application number from a hardware scanner.</p><div class="card page-card"><div class="card-body"><video id="preview" autoplay playsinline style="max-width:100%;width:500px;background:#111"></video><form id="lookup" class="d-flex gap-2 mt-3"><input id="code" class="form-control" autocomplete="off" placeholder="Application number" required><button class="btn btn-primary">Open score entry</button></form><p id="message" class="text-danger mt-2"></p></div></div>
<script>
const code=document.getElementById('code');const message=document.getElementById('message');
async function lookup(value){const response=await fetch(@json(route('admin.admission.scan-paper.lookup')),{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':@json(csrf_token())},body:JSON.stringify({code:value})});if(!response.ok){message.textContent='Applicant was not found in the active cycle.';return}location.href=(await response.json()).url}
document.getElementById('lookup').addEventListener('submit',e=>{e.preventDefault();lookup(code.value.trim())});
if('BarcodeDetector' in window){navigator.mediaDevices.getUserMedia({video:{facingMode:'environment'}}).then(stream=>{const video=document.getElementById('preview');video.srcObject=stream;const detector=new BarcodeDetector({formats:['qr_code']});let active=true;async function tick(){if(!active)return;try{const found=await detector.detect(video);if(found.length){active=false;await lookup(found[0].rawValue);return}}catch{}setTimeout(tick,400)}tick()}).catch(()=>message.textContent='Camera unavailable. Use the number field instead.')}else{message.textContent='This browser does not support QR scanning. Use a hardware scanner or enter the number.'}
</script>
@endsection
