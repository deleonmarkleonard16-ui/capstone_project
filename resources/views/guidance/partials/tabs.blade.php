@php
    $currentModule = $moduleKey ?? (
        request()->routeIs(auth()->user()->role.'.personality.*', auth()->user()->role.'.personality') ? 'personality' : (
            request()->routeIs(auth()->user()->role.'.career.*', auth()->user()->role.'.career') ? 'career' : 'psychological'
        )
    );
    $individualRoute = match($currentModule) {
        'personality' => route(auth()->user()->role.'.personality.index'),
        'career'      => route(auth()->user()->role.'.career.index'),
        default       => route(auth()->user()->role.'.psychological.index'),
    };
    $archiveRoute = match($currentModule) {
        'personality' => route(auth()->user()->role.'.personality.archive'),
        'career'      => route(auth()->user()->role.'.career.archive'),
        default       => route(auth()->user()->role.'.psychological.archive'),
    };
    $analyticsRoute = match($currentModule) {
        'personality' => route(auth()->user()->role.'.personality.analytics'),
        'career'      => route(auth()->user()->role.'.career.analytics'),
        default       => route(auth()->user()->role.'.psychological.analytics'),
    };

    $isQueue    = request()->routeIs(auth()->user()->role.'.guidance.index', auth()->user()->role.'.guidance-appointments.index', auth()->user()->role.'.psychological.index', auth()->user()->role.'.personality.index', auth()->user()->role.'.career.index')
                  && !request()->routeIs(auth()->user()->role.'.psychological.archive', auth()->user()->role.'.personality.archive', auth()->user()->role.'.career.archive');
    $isBatch    = (request()->routeIs(auth()->user()->role.'.guidance-batches.*', auth()->user()->role.'.psychological.batches', auth()->user()->role.'.personality.batches', auth()->user()->role.'.career.batches')) && !request()->boolean('archived');
    $isArchive  = request()->routeIs(auth()->user()->role.'.psychological.archive', auth()->user()->role.'.personality.archive', auth()->user()->role.'.career.archive', auth()->user()->role.'.guidance-appointments.archive')
                  || (request()->routeIs(auth()->user()->role.'.guidance-batches.*', auth()->user()->role.'.psychological.batches', auth()->user()->role.'.personality.batches', auth()->user()->role.'.career.batches') && request()->boolean('archived'));
    $isAnalytics = request()->routeIs(auth()->user()->role.'.guidance-appointments.analytics', auth()->user()->role.'.psychological.analytics', auth()->user()->role.'.personality.analytics', auth()->user()->role.'.career.analytics');
@endphp
<ul class="nav nav-tabs mb-4" role="tablist">
    <li class="nav-item" role="presentation">
        <a class="nav-link {{ $isQueue && !$isBatch && !$isArchive && !$isAnalytics ? 'active fw-bold' : '' }}" href="{{ $individualRoute }}">
            Individual Request Queue
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link {{ $isBatch ? 'active fw-bold' : '' }}" href="{{ route(auth()->user()->role.'.'.$currentModule.'.batches') }}">
            Bundled / Batch Queue
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link {{ $isArchive ? 'active fw-bold' : '' }}" href="{{ $archiveRoute }}">
            Archives
        </a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link {{ $isAnalytics ? 'active fw-bold' : '' }}" href="{{ $analyticsRoute }}">
            Analytics
        </a>
    </li>
</ul>
