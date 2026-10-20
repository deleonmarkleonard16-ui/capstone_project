@php($documentStatus = match($entry->status) {
    'pending' => ['Waiting for Receipt', 'primary'],
    'approved' => $entry->hasReceipt() ? ['Receipt Review', 'warning'] : ['Waiting for Receipt', 'primary'],
    'ready' => ['Approved / Ready for Pickup', 'success'],
    'completed' => [($mode ?? null) === 'archive' ? 'Claimed / Completed' : 'Completed', 'success'],
    'proof_review' => ['Receipt Review', 'warning'],
    'void' => ['Void', 'danger'],
    'declined' => ['Declined', 'secondary'],
    default => [ucfirst(str_replace('_', ' ', $entry->status)), 'primary'],
})
<span class="badge text-bg-{{ $documentStatus[1] }}">{{ $documentStatus[0] }}</span>
