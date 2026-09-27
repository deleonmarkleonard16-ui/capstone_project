@php
    $headerUser = auth()->user();
    $headerRole = strtolower((string) ($headerUser?->role ?? ($headerUser?->role_id === 1 ? 'admin' : 'staff')));
    $displayRole = $headerRole === 'staff' ? 'STAFF' : ($headerRole === 'admin' ? 'ADMIN' : strtoupper($headerRole));
@endphp

@if ($headerUser && in_array($headerRole, ['admin', 'staff'], true))
    <div class="header-account d-flex flex-column align-items-center gap-1">
        <small class="text-muted fw-semibold">Signed in as</small>
        <span class="badge role-pill {{ $headerRole }} text-uppercase">{{ $displayRole }}</span>
    </div>
@endif
