@php
    $role = auth()->user()?->role ?? 'staff';

    // Identify the active module
    $currentModule = $moduleKey ?? (
        request()->routeIs($role.'.personality.*', $role.'.personality') ? 'personality' : (
            request()->routeIs($role.'.career.*', $role.'.career') ? 'career' : (
                request()->routeIs($role.'.good-moral', $role.'.good-moral.*') ? 'good-moral' : (
                    request()->routeIs($role.'.exit-form', $role.'.exit-form.*') ? 'exit-form' : 'psychological'
                )
            )
        )
    );

    // Route mappings
    $isDocumentModule = in_array($currentModule, ['good-moral', 'exit-form'], true);

    $individualRoute = match($currentModule) {
        'good-moral', 'exit-form' => route("{$role}.{$currentModule}"),
        'personality', 'career'   => route("{$role}.{$currentModule}.index"),
        default                   => route("{$role}.psychological.index"),
    };

    $batchRoute = route("{$role}.{$currentModule}.batches");

    $archiveRoute = match($currentModule) {
        'good-moral', 'exit-form' => route("{$role}.{$currentModule}.archive"),
        'personality', 'career'   => route("{$role}.{$currentModule}.archive"),
        default                   => route("{$role}.psychological.archive"),
    };

    $analyticsRoute = match($currentModule) {
        'good-moral', 'exit-form' => route("{$role}.{$currentModule}.analytics"),
        'personality', 'career'   => route("{$role}.{$currentModule}.analytics"),
        default                   => route("{$role}.psychological.analytics"),
    };

    // Under Review route — filter the individual queue by status
    $underReviewRoute = match($currentModule) {
        'personality', 'career' => route("{$role}.{$currentModule}.index", ['status' => 'Under review']),
        default                 => route("{$role}.psychological.index", ['status' => 'Under review']),
    };

    // State detection
    $isAnalytics = request()->routeIs("{$role}.*.analytics")
                   || request()->routeIs("{$role}.guidance-appointments.analytics")
                   || ($mode ?? null) === 'analytics';

    $isArchive   = request()->routeIs("{$role}.*.archive")
                   || request()->routeIs("{$role}.guidance-appointments.archive")
                   || ($mode ?? null) === 'archive'
                   || request()->boolean('archived');

    $isBatch     = request()->routeIs("{$role}.*.batches", "{$role}.guidance-batches.*")
                   && !$isArchive;

    $isUnderReview = !$isAnalytics && !$isBatch && !$isArchive
                     && request()->input('status') === 'Under review';

    $isQueue     = !$isAnalytics && !$isBatch && !$isArchive && !$isUnderReview;
@endphp

{{-- ═══ 5-TAB BAR — responsive & mobile-scrollable ═══ --}}
<div class="module-tabs-wrapper mb-4">
<ul class="nav nav-tabs module-tabs" role="tablist">
    {{-- Tab 1: Individual Request Queue --}}
    <li class="nav-item flex-shrink-0" role="presentation">
        <a class="nav-link {{ $isQueue ? 'active fw-bold' : '' }}"
           href="{{ $individualRoute }}"
           role="tab"
           aria-selected="{{ $isQueue ? 'true' : 'false' }}">
            <i class="bi bi-person me-1"></i><span class="tab-label">Individual Request Queue</span>
        </a>
    </li>

    {{-- Tab 2: Under Review --}}
    @unless($isDocumentModule)
    <li class="nav-item flex-shrink-0" role="presentation">
        <a class="nav-link {{ $isUnderReview ? 'active fw-bold' : '' }}"
           href="{{ $underReviewRoute }}"
           role="tab"
           aria-selected="{{ $isUnderReview ? 'true' : 'false' }}">
            <i class="bi bi-hourglass-split me-1"></i><span class="tab-label">Under Review</span>
        </a>
    </li>
    @endunless

    {{-- Tab 3: Bundled / Batch Queue --}}
    <li class="nav-item flex-shrink-0" role="presentation">
        <a class="nav-link {{ $isBatch ? 'active fw-bold' : '' }}"
           href="{{ $batchRoute }}"
           role="tab"
           aria-selected="{{ $isBatch ? 'true' : 'false' }}">
            <i class="bi bi-people me-1"></i><span class="tab-label">Bundled / Batch Queue</span>
        </a>
    </li>

    {{-- Tab 4: Archives --}}
    <li class="nav-item flex-shrink-0" role="presentation">
        <a class="nav-link {{ $isArchive ? 'active fw-bold' : '' }}"
           href="{{ $archiveRoute }}"
           role="tab"
           aria-selected="{{ $isArchive ? 'true' : 'false' }}">
            <i class="bi bi-archive me-1"></i><span class="tab-label">Archives</span>
        </a>
    </li>

    {{-- Tab 5: Analytics --}}
    <li class="nav-item flex-shrink-0" role="presentation">
        <a class="nav-link {{ $isAnalytics ? 'active fw-bold' : '' }}"
           href="{{ $analyticsRoute }}"
           role="tab"
           aria-selected="{{ $isAnalytics ? 'true' : 'false' }}">
            <i class="bi bi-graph-up me-1"></i><span class="tab-label">Analytics</span>
        </a>
    </li>
</ul>
</div>
