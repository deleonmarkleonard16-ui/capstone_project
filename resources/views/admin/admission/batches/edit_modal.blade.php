<div class="modal fade" id="editBatchModal{{ $batch->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content shadow">
        <div class="modal-header bg-light"><h5 class="modal-title fw-bold"><i class="bi bi-pencil me-2"></i>Edit {{ $batch->batch_name }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <form method="POST" action="{{ route('admin.admission.batches.update', $batch) }}">@csrf @method('PUT')
            <div class="modal-body"><div class="mb-3"><label class="form-label fw-semibold">Batch Name</label><input class="form-control" name="batch_name" value="{{ $batch->batch_name }}" required></div><div class="row g-3"><div class="col-md-6"><label class="form-label fw-semibold">Batch Date <span class="text-danger">*</span></label><input class="form-control" type="date" name="batch_date" value="{{ optional($batch->batch_date)->format('Y-m-d') }}" required></div><div class="col-md-6"><label class="form-label fw-semibold">Venue</label><input class="form-control" name="room" value="{{ $batch->room }}"></div></div></div>
            <div class="modal-footer"><button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary btn-sm">Save Batch</button></div>
        </form>
    </div></div>
</div>
