@php
    $routeParts = explode('.', request()->route()?->getName() ?? '');
    $sectionKey = $routeParts[1] ?? 'dashboard';
    $labels = [
        'dashboard' => 'Dashboard', 'admission' => 'Admission Test', 'sessions' => 'Sessions',
        'guidance' => 'Request Testing', 'guidance-appointments' => 'Request Testing', 'psychological' => 'Psychological Assessment',
        'personality' => 'Personality Test', 'career' => 'Career Test', 'good-moral' => 'Good Moral',
        'exit-form' => 'Exit Form', 'guidance-batches' => 'Testing Batches', 'exports' => 'Document Exports',
        'index' => 'Overview', 'show' => 'Details', 'create' => 'Create', 'edit' => 'Edit',
        'scan-paper' => 'OMR Scanner', 'encoding-sheet' => 'Encoding Sheet', 'answer-key' => 'Answer Key',
        'masterlist' => 'Masterlist', 'settings' => 'Settings', 'archive' => 'Archive', 'analytics' => 'Analytics',
    ];
    $sectionLabel = $labels[$sectionKey] ?? \Illuminate\Support\Str::headline($sectionKey);
    $lastKey = end($routeParts);
    $pageLabel = $labels[$lastKey] ?? \Illuminate\Support\Str::headline($lastKey);
@endphp
<nav aria-label="Breadcrumb" class="workspace-breadcrumb">
    <ol class="breadcrumb">
        @if(request()->routeIs($role.'.dashboard'))
            <li class="breadcrumb-item active" aria-current="page">Dashboard</li>
        @else
            <li class="breadcrumb-item"><a href="{{ route($role.'.dashboard') }}"><i class="bi bi-house-door me-1" aria-hidden="true"></i>Dashboard</a></li>
            @if($sectionKey === 'admission' && !request()->routeIs('admin.admission.index'))
                <li class="breadcrumb-item"><a href="{{ route('admin.admission.index') }}">Admission Test</a></li>
            @elseif(count($routeParts) > 2 && $lastKey !== 'index')
                <li class="breadcrumb-item">{{ $sectionLabel }}</li>
            @endif
            <li class="breadcrumb-item active" aria-current="page">{{ $lastKey === 'index' || count($routeParts) === 2 ? $sectionLabel : $pageLabel }}</li>
        @endif
    </ol>
</nav>
