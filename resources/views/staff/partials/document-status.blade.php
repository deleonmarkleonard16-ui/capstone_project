@php($documentStatus = match($entry->status) {
    'ready' => ['Approved / Ready for Pickup', 'success'],
    'completed' => ['Claimed / Completed', 'success'],
    'proof_review' => ['Receipt Review', 'warning'],
    'void' => ['Void', 'danger'],
    'declined' => ['Declined', 'secondary'],
    default => [ucfirst(str_replace('_', ' ', $entry->status)), 'primary'],
})
<span class="badge text-bg-{{ $documentStatus[1] }}">{{ $documentStatus[0] }}</span>
