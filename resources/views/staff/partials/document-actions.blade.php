@if($entry->status === 'proof_review')
    <div class="btn-group btn-group-sm">
        <button type="button" class="btn btn-outline-primary"
                data-receipt="{{ route(auth()->user()->role.'.documents.proof', $entry) }}">
            <i class="bi bi-receipt me-1"></i> Review Receipt
        </button>
        <form method="post"
              action="{{ route(auth()->user()->role.'.'.$entry->service.'.update', $entry) }}"
              class="d-inline">
            @csrf
            @method('PATCH')
            <input type="hidden" name="action" value="verify">
            <button class="btn btn-primary" title="Approve and mark ready for pickup">
                <i class="bi bi-check-lg me-1"></i> Approve / Ready for Pickup
            </button>
        </form>
    </div>
@elseif($entry->status === 'ready')
    <button type="button" class="btn btn-sm btn-success"
            data-bs-toggle="modal"
            data-bs-target="#claim-modal-{{ $entry->id }}">
        <i class="bi bi-box-arrow-down me-1"></i> Mark as Claimed / Completed
    </button>

    {{-- Claim modal --}}
    <div class="modal fade" id="claim-modal-{{ $entry->id }}" tabindex="-1" aria-labelledby="claimLabel{{ $entry->id }}" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title h5" id="claimLabel{{ $entry->id }}">
                        Document Claim Verification — {{ $entry->reference }}
                    </h3>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="post" action="{{ route(auth()->user()->role.'.'.$entry->service.'.update', $entry) }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="action" value="claim">
                    <div class="modal-body">
                        <p class="small text-muted mb-3">
                            Confirm that the student has paid and physically claimed this document at the Guidance Office.
                        </p>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Official Receipt (O.R.) Number <span class="text-danger">*</span></label>
                            <input class="form-control" name="or_number" maxlength="100" placeholder="e.g. 1234567" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">O.R. Date <span class="text-danger">*</span></label>
                            <input class="form-control" type="date" name="or_date" max="{{ now()->toDateString() }}" value="{{ now()->toDateString() }}" required>
                        </div>
                        <div class="mb-3">
                            <button type="button" class="btn btn-outline-primary btn-sm w-100"
                                    data-receipt="{{ route(auth()->user()->role.'.documents.proof', $entry) }}">
                                <i class="bi bi-image me-1"></i> Preview Attached Receipt
                            </button>
                        </div>
                        <div class="mb-2">
                            <label class="form-label fw-semibold">Staff Message / Remarks</label>
                            <input class="form-control form-control-sm" name="staff_message" placeholder="Optional notes (e.g. Claimed in person)">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button class="btn btn-success btn-sm">
                            <i class="bi bi-check2-circle me-1"></i> Mark as Claimed / Completed
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif
