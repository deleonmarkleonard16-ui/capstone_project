@once
<div class="modal fade" id="receipt-preview" data-guidance-review-modal tabindex="-1" aria-labelledby="receipt-title" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-light">
                <h2 class="modal-title fs-5 fw-bold text-dark" id="receipt-title">
                    <i class="bi bi-receipt me-2 text-primary"></i>Payment Receipt Verification
                </h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center p-3">
                <div id="receipt-or-banner" class="alert alert-primary d-flex justify-content-between align-items-center mb-3 text-start px-3 py-2" hidden>
                    <div>
                        <span class="text-muted small d-block">Official Receipt (O.R.) Number</span>
                        <strong id="receipt-or-number" class="fs-6 text-dark"></strong>
                    </div>
                    <div>
                        <span class="text-muted small d-block">O.R. Date</span>
                        <strong id="receipt-or-date" class="fs-6 text-dark"></strong>
                    </div>
                </div>

                {{-- Loading indicator --}}
                <div id="receipt-loading" class="py-5 text-muted">
                    <div class="spinner-border text-primary mb-2" role="status" style="width: 2.5rem; height: 2.5rem;"></div>
                    <div class="small fw-semibold">Loading receipt preview...</div>
                </div>

                {{-- Image Preview --}}
                <div id="receipt-image-container" class="d-none text-center">
                    <img id="receipt-image" alt="Uploaded payment receipt" class="img-fluid rounded border shadow-sm" style="max-height: 70vh; object-fit: contain;">
                </div>

                {{-- PDF Preview --}}
                <div id="receipt-pdf-container" class="d-none">
                    <iframe id="receipt-pdf" style="width: 100%; height: 68vh; border: 1px solid #dee2e6; border-radius: 6px;" title="Receipt PDF"></iframe>
                </div>

                {{-- Error Message --}}
                <div id="receipt-error" class="alert alert-warning py-3 text-center my-3" hidden>
                    <i class="bi bi-exclamation-triangle-fill text-warning me-2 fs-5"></i>
                    <span>Preview unavailable. Please click <strong>"Open original receipt"</strong> below to view the file.</span>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <a id="receipt-original" class="btn btn-outline-primary" target="_blank" rel="noopener">
                    <i class="bi bi-box-arrow-up-right me-1"></i> Open original receipt
                </a>
                <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
@push('scripts')
<script>
(() => {
    let currentBlobUrl = null;

    document.addEventListener('click', async event => {
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
        const loading = document.getElementById('receipt-loading');
        const imgContainer = document.getElementById('receipt-image-container');
        const image = document.getElementById('receipt-image');
        const pdfContainer = document.getElementById('receipt-pdf-container');
        const pdfFrame = document.getElementById('receipt-pdf');
        const error = document.getElementById('receipt-error');
        const original = document.getElementById('receipt-original');

        // Reset state
        if (currentBlobUrl) {
            URL.revokeObjectURL(currentBlobUrl);
            currentBlobUrl = null;
        }
        if (loading) loading.hidden = false;
        if (imgContainer) imgContainer.classList.add('d-none');
        if (pdfContainer) pdfContainer.classList.add('d-none');
        if (error) error.hidden = true;
        if (image) { image.src = ''; }
        if (pdfFrame) { pdfFrame.src = ''; }

        // Set O.R. details if present
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

        const receiptUrl = button.dataset.receipt;
        if (original) original.href = receiptUrl;

        const modalInstance = bootstrap.Modal.getOrCreateInstance(modalEl);
        modalInstance.show();

        if (!receiptUrl) {
            if (loading) loading.hidden = true;
            if (error) error.hidden = false;
            return;
        }

        try {
            const response = await fetch(receiptUrl, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                cache: 'no-store'
            });

            if (!response.ok) {
                throw new Error('Receipt preview returned status ' + response.status);
            }

            const contentType = response.headers.get('Content-Type') || '';
            const blob = await response.blob();
            currentBlobUrl = URL.createObjectURL(blob);

            if (loading) loading.hidden = true;

            if (contentType.includes('pdf') || blob.type.includes('pdf')) {
                if (pdfFrame && pdfContainer) {
                    pdfFrame.src = currentBlobUrl;
                    pdfContainer.classList.remove('d-none');
                }
            } else {
                if (image && imgContainer) {
                    image.onload = () => {
                        if (loading) loading.hidden = true;
                    };
                    image.onerror = () => {
                        if (loading) loading.hidden = true;
                        if (imgContainer) imgContainer.classList.add('d-none');
                        if (error) error.hidden = false;
                    };
                    image.src = currentBlobUrl;
                    imgContainer.classList.remove('d-none');
                }
            }
        } catch (err) {
            if (loading) loading.hidden = true;
            // Fallback direct URL load on image
            if (image && imgContainer) {
                image.onerror = () => {
                    if (imgContainer) imgContainer.classList.add('d-none');
                    if (error) error.hidden = false;
                };
                image.onload = () => {
                    if (error) error.hidden = true;
                };
                image.src = receiptUrl;
                imgContainer.classList.remove('d-none');
            } else if (error) {
                error.hidden = false;
            }
        }
    });

    const modalEl = document.getElementById('receipt-preview');
    if (modalEl) {
        modalEl.addEventListener('hidden.bs.modal', () => {
            if (currentBlobUrl) {
                URL.revokeObjectURL(currentBlobUrl);
                currentBlobUrl = null;
            }
        });
    }
})();
</script>
@endpush
@endonce
