@php
    $moduleLabel = \App\Http\Controllers\DocumentRequestController::MODULES[$moduleKey];
    $summaryCards = [
        ['Awaiting Receipt', (int) ($queueCounts['pending'] ?? 0), 'primary', 'bi-hourglass-split'],
        ['Receipt Review', (int) ($queueCounts['proof_review'] ?? 0), 'warning', 'bi-receipt'],
        ['Ready for Pickup', (int) ($queueCounts['ready'] ?? 0), 'success', 'bi-box-seam'],
        ['Completed', (int) ($queueCounts['completed'] ?? 0), 'secondary', 'bi-check2-circle'],
    ];
@endphp
<div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
    <div>
        <h1 class="h3 mb-1">{{ $moduleLabel }} {{ $mode === 'archive' ? 'Archives' : ($mode === 'batches' ? 'Bundled / Batch Queue' : 'Request Queue') }}</h1>
        <p class="text-muted mb-0">Verify receipts, prepare documents, and record release without an assessment.</p>
    </div>
    @if(in_array($mode, ['queue', 'batches'], true))
        <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#document-batch-modal">
            <i class="bi bi-people-fill me-1"></i> + Create Bundled Request
        </button>
    @endif
</div>

@if(in_array($mode, ['queue', 'batches'], true))
    <div class="row g-3 mb-4">
        @foreach($summaryCards as [$label, $total, $color, $icon])
            <div class="col-6 col-xl-3">
                <div class="card page-card h-100 border-start border-4 border-{{ $color }}">
                    <div class="card-body p-3 d-flex justify-content-between align-items-center">
                        <div>
                            <div class="small text-muted text-uppercase fw-semibold">{{ $label }}</div>
                            <div class="h3 mb-0 mt-1 text-{{ $color }}">{{ $total }}</div>
                        </div>
                        <span class="rounded-circle bg-{{ $color }} bg-opacity-10 text-{{ $color }} p-3"><i class="bi {{ $icon }} fs-5"></i></span>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif
