@php $isGuestView = true; @endphp
@extends('layouts.app')

@push('styles')
<style>
    html, body, main, .content-wrapper { background-color: #ffffff !important; }
</style>
@endpush

@section('content')
<div class="d-flex justify-content-center py-3 py-md-5">
    <div style="max-width: 580px; width: 100%;">
        <div class="card border-0 shadow-sm rounded-4 bg-white">
            <div class="card-body p-4 p-md-5 text-center">
                <div class="mb-3">
                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-danger bg-opacity-10" style="width:72px;height:72px;">
                        <i class="bi bi-slash-circle-fill text-danger" style="font-size:2.5rem;"></i>
                    </span>
                </div>

                <div class="badge bg-danger mb-2 px-3 py-1 text-uppercase">Exam Session Locked</div>

                <h1 class="h4 fw-bold text-danger mb-2">
                    Exam Auto-Terminated
                </h1>

                <p class="text-muted small mb-4">
                    Examinee reached 3 strikes for violating test lockdown rules (such as exiting fullscreen, switching applications/tabs, or screenshot attempts).
                </p>

                <div class="alert alert-danger text-start small mb-4">
                    <strong>Lockout Status:</strong>
                    <ul class="mb-0 ps-3 mt-1">
                        <li>Your active answer sheet choices have been automatically saved.</li>
                        <li>This screen is locked and being monitored in real-time.</li>
                        <li>If the Guidance Admin / Proctor grants an administrative override (e.g. for a false alarm or technical issue), this page will automatically unlock and resume your exam session.</li>
                    </ul>
                </div>

                <div class="rounded-3 p-3 text-center small mb-0" role="status" aria-live="polite" style="background-color: #fef2f2; color: #991b1b; border: 1px solid #fecaca; font-size: 13px;">
                    <span class="spinner-border spinner-border-sm me-2 text-danger" aria-hidden="true"></span>
                    <span id="heartbeat-status">Listening for Proctor Override / Resume Authorization...</span>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    const examToken = @json($token);
    const stateUrl  = @json($token ? route('admission.state', $token) : '');

    let resumed = false;

    async function checkResumeHeartbeat() {
        if (resumed || !stateUrl) return;
        try {
            const res = await fetch(stateUrl, {
                headers: { 'Accept': 'application/json' },
                cache: 'no-store'
            });
            if (res.ok) {
                const data = await res.json();
                if (!data.terminated && data.strike_count === 0 && data.take_url) {
                    resumed = true;
                    document.getElementById('heartbeat-status').textContent = 'Re-entry granted! Restoring exam session...';
                    setTimeout(() => {
                        window.location.replace(data.take_url);
                    }, 500);
                }
            }
        } catch (_) {
            // Retry on next cycle
        }
    }

    if (stateUrl) {
        checkResumeHeartbeat();
        setInterval(checkResumeHeartbeat, 3000);
    }
</script>
@endpush
@endsection
