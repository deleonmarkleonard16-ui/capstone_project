<div class="modal fade" id="createBatchModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow">
            <div class="modal-header bg-light">
                <div><h5 class="modal-title fw-bold mb-0"><i class="bi bi-collection me-2 text-primary"></i>Add Batch</h5><small class="text-muted">Create a group, then add its sessions.</small></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('admin.admission.batches.store') }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-3"><label class="form-label fw-semibold">Batch Name <span class="text-danger">*</span></label><input class="form-control" name="batch_name" placeholder="e.g., Batch 1" required></div>
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label fw-semibold">Batch Date <span class="text-danger">*</span></label><input class="form-control" type="date" name="batch_date" required></div>
                        <div class="col-md-6"><label class="form-label fw-semibold">Venue <span class="text-muted fw-normal">(Optional)</span></label><input class="form-control" name="room" placeholder="e.g., Covered Court"></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary btn-sm"><i class="bi bi-plus-circle me-1"></i>Create Batch</button></div>
            </form>
        </div>
    </div>
</div>
