<style>
[data-review-preview] { min-height:180px; display:grid; place-items:center; }
[data-review-preview] > * { grid-area:1 / 1; }
[data-review-receipt-loading] { padding:2rem; color:#64748b; }
.review-receipt-spinner { display:inline-block; width:2rem; height:2rem; border:3px solid #dbe3ed; border-top-color:#2563eb; border-radius:50%; animation:review-receipt-spin .8s linear infinite; }
@keyframes review-receipt-spin { to { transform:rotate(360deg); } }
@media (prefers-reduced-motion:reduce) { .review-receipt-spinner { animation:none; } }
#guidance-review-body [hidden] { display:none !important; }
#guidance-review-body[aria-busy="true"]:empty { min-height:180px; }
</style>
<div class="modal fade" id="guidance-review-modal" data-guidance-review-modal tabindex="-1" aria-labelledby="guidance-review-title" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
        <div class="modal-header"><h2 class="modal-title fs-5" id="guidance-review-title">Review Details</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body"><p id="guidance-review-message" role="status" aria-live="polite" class="small"></p><div id="guidance-review-body" aria-busy="false"></div></div>
        <div class="modal-footer"><button class="btn btn-outline-secondary" type="button" id="guidance-review-refresh">Refresh details</button><button class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>
    </div></div>
</div>
@push('scripts')<script src="{{ asset('js/guidance-review.js') }}" defer></script>@endpush
