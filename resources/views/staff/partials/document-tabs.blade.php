@php($base = auth()->user()->role.'.'.$moduleKey)
<div class="module-tabs-wrapper mb-4">
<nav class="nav nav-tabs module-tabs" aria-label="{{ \App\Http\Controllers\DocumentRequestController::MODULES[$moduleKey] }} navigation">
    <a class="nav-link flex-shrink-0 {{ $mode === 'queue' ? 'active fw-bold' : '' }}" href="{{ route($base) }}">
        <i class="bi bi-person me-1"></i><span class="tab-label">Individual Request Queue</span>
    </a>
    <a class="nav-link flex-shrink-0 {{ $mode === 'batches' ? 'active fw-bold' : '' }}" href="{{ route($base.'.batches') }}">
        <i class="bi bi-people me-1"></i><span class="tab-label">Bundled / Batch Queue</span>
    </a>
    <a class="nav-link flex-shrink-0 {{ $mode === 'archive' ? 'active fw-bold' : '' }}" href="{{ route($base.'.archive') }}">
        <i class="bi bi-archive me-1"></i><span class="tab-label">Archives</span>
    </a>
    <a class="nav-link flex-shrink-0 {{ $mode === 'analytics' ? 'active fw-bold' : '' }}" href="{{ route($base.'.analytics') }}">
        <i class="bi bi-graph-up me-1"></i><span class="tab-label">Analytics</span>
    </a>
</nav>
</div>
