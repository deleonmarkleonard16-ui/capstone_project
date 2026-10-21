@push('styles')
<style>
    #admission-kiosk-gate {
        position: fixed;
        inset: 0;
        z-index: 5000;
        display: grid;
        place-items: center;
        padding: 1.25rem;
        background: rgba(7, 24, 58, .96);
        color: #fff;
    }
    #admission-kiosk-gate[hidden] { display: none !important; }
    #admission-kiosk-gate .kiosk-card {
        width: min(100%, 32rem);
        padding: clamp(1.25rem, 5vw, 2rem);
        border: 1px solid rgba(255,255,255,.2);
        border-radius: 1rem;
        background: #fff;
        color: #17305f;
        box-shadow: 0 1.5rem 4rem rgba(0,0,0,.3);
    }
</style>
@endpush

<div id="admission-kiosk-gate" role="dialog" aria-modal="true" aria-labelledby="admission-kiosk-title">
    <section class="kiosk-card">
        <div class="text-primary text-uppercase fw-bold small mb-2">Secure exam entry</div>
        <h1 id="admission-kiosk-title" class="h4 fw-bold">Prepare your phone</h1>
        <p class="mb-2">Turn on Do Not Disturb and remove chat heads or floating windows before continuing. Then tap “Enable fullscreen and continue.”</p>
        <p class="small text-muted mb-3">Fullscreen and focus checks are required. Keep this page open and focused throughout the exam.</p>
        <p id="admission-kiosk-status" class="small fw-semibold text-danger" role="status" aria-live="assertive">Tap below to enable fullscreen. You cannot continue until it succeeds.</p>
        <button id="admission-kiosk-enter" type="button" class="btn btn-primary w-100 fw-semibold py-2">Enable fullscreen and continue</button>
    </section>
</div>
<script src="{{ asset('js/admission-kiosk-gate.js') }}" defer></script>
