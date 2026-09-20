@php($base = auth()->user()->role.'.'.$moduleKey)
<nav class="nav nav-tabs mb-4" aria-label="{{ \App\Http\Controllers\DocumentRequestController::MODULES[$moduleKey] }} navigation">
    <a class="nav-link {{ $mode === 'queue' ? 'active fw-bold' : '' }}" href="{{ route($base) }}">Individual Request Queue</a>
    <a class="nav-link {{ $mode === 'batches' ? 'active fw-bold' : '' }}" href="{{ route($base.'.batches') }}">Bundled / Batch Queue</a>
    <a class="nav-link {{ $mode === 'archive' ? 'active fw-bold' : '' }}" href="{{ route($base.'.archive') }}">Archives</a>
    <a class="nav-link {{ $mode === 'analytics' ? 'active fw-bold' : '' }}" href="{{ route($base.'.analytics') }}">Analytics</a>
</nav>
