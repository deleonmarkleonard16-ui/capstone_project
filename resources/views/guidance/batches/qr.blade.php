@extends('guidance.layout')
@section('content')
<style>
    .batch-qr-page { max-width:1000px; margin:0 auto; }
    .batch-qr-hero { color:#fff; border-radius:22px; padding:clamp(24px,5vw,48px); background:radial-gradient(circle at 85% 15%,#2f65b5 0,#173d80 38%,#102d67 100%); box-shadow:0 16px 44px #102d6726; }
    .batch-qr-layout { display:grid; grid-template-columns:minmax(250px, .9fr) minmax(280px, 1.1fr); gap:clamp(22px,4vw,42px); align-items:center; }
    .batch-qr-code { background:#fff; padding:18px; border:4px solid #ffc83d; border-radius:22px; display:inline-flex; box-shadow:0 12px 35px #06153940; }
    .batch-qr-code img { width:min(340px,65vw); height:min(340px,65vw); object-fit:contain; }
    .batch-qr-details { background:#fff; border:1px solid #dce5f3; border-radius:16px; padding:18px; color:#19345e; }
    .batch-qr-details dt { color:#65748a; font-size:.7rem; letter-spacing:.06em; text-transform:uppercase; }
    .batch-qr-url { word-break:break-all; }
    .batch-projector { position:fixed; inset:0; z-index:2000; padding:clamp(20px,4vw,54px); background:radial-gradient(circle at center,#173d80 0,#0b1e46 75%); color:#fff; display:none; flex-direction:column; justify-content:space-between; }
    .batch-projector.is-open { display:flex; }
    .batch-projector .batch-qr-code img { width:min(440px,72vw); height:min(440px,72vw); }
    @media(max-width:700px) { .batch-qr-layout { grid-template-columns:1fr; text-align:center; } .batch-qr-copy { text-align:left; } }
    @media print { body.guidance-student { background:#fff; } .psu-header,.batch-qr-page,.batch-projector { display:none!important; } .batch-print-card { display:block!important; text-align:center; padding:20mm; color:#142c58; } .batch-print-card img { width:90mm; height:90mm; } .batch-print-card h1 { font-size:24pt; } }
    .batch-print-card { display:none; }
</style>
<div class="batch-qr-page">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3 d-print-none">
        <a class="btn btn-outline-primary" href="{{ route(auth()->user()->role.'.guidance-batches.index') }}"><i class="bi bi-arrow-left me-1"></i>Back to batches</a>
        <div class="d-flex gap-2 flex-wrap"><button class="btn btn-warning fw-bold" type="button" id="project-batch-qr"><i class="bi bi-projector-fill me-1"></i>Display Fullscreen / Project</button><button class="btn btn-outline-secondary" type="button" onclick="window.print()"><i class="bi bi-printer me-1"></i>Print QR</button></div>
    </div>
    <section class="batch-qr-hero">
        <div class="d-flex align-items-center gap-3 mb-4"><img src="{{ asset('images/psu-logo.png') }}" alt="PSU logo" style="width:54px;height:54px;object-fit:contain"><div><div class="small text-uppercase fw-semibold text-warning">Guidance Testing · Bundled Request</div><h1 class="h3 mb-0">{{ $batch->batch_name }}</h1></div></div>
        <div class="batch-qr-layout">
            <div class="text-center"><div class="batch-qr-code"><img src="{{ $image }}" alt="Batch registration QR code"></div><div class="mt-3"><span class="badge bg-white text-primary font-monospace px-3 py-2">SCAN TO JOIN BATCH</span></div></div>
            <div class="batch-qr-copy">
                <h2 class="h4 fw-bold">Student Batch Registration</h2>
                <p class="text-white-50 mb-4">Students scan this code, verify their roster identity, then submit proof of payment to join the testing session.</p>
                <div class="batch-qr-details mb-3"><div class="row g-3"><div class="col-6"><dt>Course / Section</dt><dd class="fw-bold mb-0">{{ $batch->courseLabel() }}{{ $batch->year_section ? ' · '.$batch->year_section : '' }}</dd></div><div class="col-6"><dt>Test Category</dt><dd class="fw-bold mb-0">{{ $batch->test_type }}</dd></div><div class="col-6"><dt>Applicants</dt><dd class="fw-bold mb-0">{{ $batch->appointments()->count() }}</dd></div><div class="col-6"><dt>Batch Status</dt><dd class="fw-bold mb-0">{{ $batch->status }}</dd></div><div class="col-12"><dt>Reason</dt><dd class="fw-bold mb-0">{{ $batch->reason_for_request }}</dd></div></div></div>
                <label class="form-label small fw-semibold text-white-50 mb-1" for="batch-join-url">Direct Batch Registration Link</label>
                <div class="input-group"><input id="batch-join-url" class="form-control font-monospace batch-qr-url" value="{{ $url }}" readonly><button class="btn btn-light" id="copy-batch-link" type="button"><i class="bi bi-clipboard me-1"></i>Copy</button><a class="btn btn-outline-light" href="{{ $url }}" target="_blank" rel="noopener" aria-label="Open registration link"><i class="bi bi-box-arrow-up-right"></i></a></div>
            </div>
        </div>
    </section>
</div>
<div class="batch-print-card"><img src="{{ $image }}" alt="Batch registration QR code"><h1>{{ $batch->batch_name }}</h1><p>{{ $batch->courseLabel() }} · {{ $batch->test_type }}</p><p>Scan to verify roster identity and join the Guidance Testing batch.</p><p>{{ $url }}</p></div>
<section class="batch-projector" id="batch-projector" aria-label="Fullscreen batch QR projector">
    <div class="d-flex justify-content-between align-items-start gap-3"><div class="d-flex align-items-center gap-3"><img src="{{ asset('images/psu-logo.png') }}" alt="PSU logo" style="width:56px;height:56px;object-fit:contain"><div><h2 class="h4 fw-bold text-warning mb-0">Pangasinan State University – San Carlos Campus</h2><div class="small text-white-50">Guidance Testing · Student Batch Registration</div></div></div><button class="btn btn-outline-light" id="exit-batch-projector" type="button"><i class="bi bi-fullscreen-exit me-1"></i>Exit Fullscreen</button></div>
    <div class="text-center my-auto"><div class="batch-qr-code"><img src="{{ $image }}" alt="Batch registration QR code"></div><h1 class="display-5 fw-bold mt-4">{{ $batch->batch_name }}</h1><p class="fs-4 text-white-50">{{ $batch->courseLabel() }} · {{ $batch->test_type }}</p><p class="font-monospace text-white-50">{{ $url }}</p></div>
    <div class="text-center text-white-50">Scan the code, verify your roster identity, then submit your receipt.</div>
</section>
<script>
(() => {
    const projector = document.getElementById('batch-projector');
    const enter = document.getElementById('project-batch-qr');
    const close = document.getElementById('exit-batch-projector');
    const copy = document.getElementById('copy-batch-link');
    const leave = async () => { projector.classList.remove('is-open'); if (document.fullscreenElement) await document.exitFullscreen().catch(() => {}); };
    enter?.addEventListener('click', async () => { projector.classList.add('is-open'); try { await projector.requestFullscreen(); } catch (_) {} });
    close?.addEventListener('click', leave);
    document.addEventListener('fullscreenchange', () => { if (!document.fullscreenElement) projector.classList.remove('is-open'); });
    document.addEventListener('keydown', event => { if (event.key === 'Escape') projector.classList.remove('is-open'); });
    copy?.addEventListener('click', async () => { await navigator.clipboard.writeText(document.getElementById('batch-join-url').value); copy.innerHTML = '<i class="bi bi-check-lg me-1"></i>Copied'; setTimeout(() => copy.innerHTML = '<i class="bi bi-clipboard me-1"></i>Copy', 1800); });
})();
</script>
@endsection
