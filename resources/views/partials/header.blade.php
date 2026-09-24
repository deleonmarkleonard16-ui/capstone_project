@php
    $headerRole = auth()->user()?->role;
@endphp

@if (in_array($headerRole, ['admin', 'staff'], true))
    <div class="d-flex align-items-center gap-2">
        <small>Signed in as</small>
        <span class="badge role-pill {{ $headerRole }} text-uppercase">{{ strtoupper($headerRole) }}</span>
    </div>
@endif
