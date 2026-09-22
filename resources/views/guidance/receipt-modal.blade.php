<div class="modal fade" id="receipt-preview" data-guidance-review-modal tabindex="-1" aria-labelledby="receipt-title" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
        <div class="modal-header"><h2 class="modal-title fs-5" id="receipt-title">Payment Receipt Verification</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body text-center">
            <div id="receipt-or-banner" class="alert alert-primary d-flex justify-content-between align-items-center mb-3 text-start px-3 py-2" hidden>
                <div><span class="text-muted small d-block">Official Receipt (O.R.) Number</span><strong id="receipt-or-number" class="fs-6 text-dark"></strong></div>
                <div><span class="text-muted small d-block">O.R. Date</span><strong id="receipt-or-date" class="fs-6 text-dark"></strong></div>
            </div>
            <img id="receipt-image" alt="Uploaded payment receipt" class="img-fluid" style="max-height:70vh">
            <p id="receipt-error" class="text-danger" hidden>Preview unavailable. Open the original receipt below.</p>
        </div>
        <div class="modal-footer"><a id="receipt-original" class="btn btn-outline-primary" target="_blank" rel="noopener">Open original receipt</a><button class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>
    </div></div>
</div>
@push('scripts')
<script>
document.addEventListener('click', event => {
    const button = event.target.closest('[data-receipt]');
    if (!button) return;
    event.preventDefault();
    const modalEl = document.getElementById('receipt-preview');
    if (!modalEl) return;
    if (modalEl.parentElement !== document.body) {
        document.body.appendChild(modalEl);
    }

    const orBanner = document.getElementById('receipt-or-banner');
    const orNum = document.getElementById('receipt-or-number');
    const orDate = document.getElementById('receipt-or-date');
    if (orBanner && orNum && orDate) {
        const numVal = button.dataset.orNumber || '';
        const dateVal = button.dataset.orDate || '';
        if (numVal || dateVal) {
            orNum.textContent = numVal || 'Not provided';
            orDate.textContent = dateVal || 'Not provided';
            orBanner.hidden = false;
        } else {
            orBanner.hidden = true;
        }
    }

    const image = document.getElementById('receipt-image');
    const error = document.getElementById('receipt-error');
    if (error) error.hidden = true;
    if (image) {
        image.hidden = false;
        image.onerror = () => { if (error) error.hidden = false; image.hidden = true; };
        image.src = button.dataset.receipt;
    }
    const original = document.getElementById('receipt-original');
    if (original) original.href = button.dataset.receipt;
    bootstrap.Modal.getOrCreateInstance(modalEl).show();
});
</script>
@endpush
