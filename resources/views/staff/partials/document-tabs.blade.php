@php($base = auth()->user()->role.'.'.$moduleKey)
<nav class="nav nav-tabs mb-4 module-tabs" aria-label="{{ \App\Http\Controllers\DocumentRequestController::MODULES[$moduleKey] }} navigation">
    <a class="nav-link {{ $mode === 'queue' ? 'active fw-bold' : '' }}" href="{{ route($base) }}">
        <i class="bi bi-person me-1"></i> Individual Request Queue
    </a>
    <a class="nav-link {{ $mode === 'batches' ? 'active fw-bold' : '' }}" href="{{ route($base.'.batches') }}">
        <i class="bi bi-people me-1"></i> Bundled / Batch Queue
    </a>
    <a class="nav-link {{ $mode === 'archive' ? 'active fw-bold' : '' }}" href="{{ route($base.'.archive') }}">
        <i class="bi bi-archive me-1"></i> Archives
    </a>
    <a class="nav-link {{ $mode === 'analytics' ? 'active fw-bold' : '' }}" href="{{ route($base.'.analytics') }}">
        <i class="bi bi-graph-up me-1"></i> Analytics
    </a>
</nav>
