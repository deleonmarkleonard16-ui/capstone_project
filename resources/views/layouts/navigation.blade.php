@php
    $user        = auth()->user();
    $rawRole     = $user?->role ?? ($user?->role_id === 1 ? 'admin' : ($user?->role_id === 2 ? 'staff' : 'guest'));
    $role        = strtolower((string) $rawRole);
    $isAdmin     = $role === 'admin' || (int) ($user?->role_id ?? 0) === 1;
    $isStaff     = $role === 'staff' || (int) ($user?->role_id ?? 0) === 2;
    $displayRole = $isStaff ? 'STAFF' : ($isAdmin ? 'ADMIN' : strtoupper($role));

    // Detect active admission sub-page
    $admissionActive = request()->routeIs('admin.admission.*', 'admin.sessions.*')
        || (request()->routeIs('admin.archive') && request()->query('section') === 'admission');

    // Detect active guidance module for the request-testing accordion
    $activeGuidanceModule = collect(['psychological','personality','career','good-moral','exit-form'])
        ->first(fn ($m) => request()->routeIs("{$role}.{$m}", "{$role}.{$m}.*"));

    // Detect active analytics page
    $analyticsActive = request()->routeIs("{$role}.analytics", 'admin.analytics', 'staff.analytics');
@endphp

@if ($user)
<aside class="offcanvas-lg offcanvas-start app-sidebar overflow-y-auto"
       tabindex="-1" id="appSidebar" aria-labelledby="sidebarTitle">
    <div class="offcanvas-header border-bottom border-white border-opacity-25">
        <h2 class="offcanvas-title h6 text-white" id="sidebarTitle">Navigation</h2>
        <button type="button" class="btn-close btn-close-white"
                data-bs-dismiss="offcanvas" data-bs-target="#appSidebar"
                aria-label="Close"></button>
    </div>
    <div class="sidebar-body d-flex flex-column justify-content-between h-100 min-vh-100 overflow-y-auto">
        <div class="sidebar-main-nav">
            {{-- Account pill --}}
            <div class="sidebar-account">
                <small>Signed in as</small>
                <span class="role-pill {{ $isStaff ? 'staff' : ($isAdmin ? 'admin' : 'secondary') }} text-uppercase">
                    {{ $displayRole }}
                </span>
            </div>

            <div class="sidebar-caption">Main Navigation</div>

            <nav aria-label="Main navigation">

                {{-- ── 1. ADMISSION TEST (Admin Only) ── --}}
                @if ($isAdmin)
                <details class="sb-group"
                         @if($admissionActive) open @endif>
                    <summary>
                        <span><i class="bi bi-mortarboard me-1"></i> Admission Test</span>
                        <span class="d-flex align-items-center gap-2">
                            <span class="sb-badge">Admin&nbsp;<i class="bi bi-chevron-right sb-chevron"></i></span>
                        </span>
                    </summary>
                    <div class="sb-sub">
                        <a class="sidebar-link {{ request()->routeIs('admin.admission.index') && !request()->has('section') ? 'active' : '' }}"
                           href="{{ route('admin.admission.index') }}">
                            <i class="bi bi-arrow-repeat"></i> Admission Cycle
                        </a>
                        <a class="sidebar-link {{ request()->routeIs('admin.sessions.*') ? 'active' : '' }}"
                           href="{{ route('admin.sessions.index') }}">
                            <i class="bi bi-calendar3"></i> Sessions
                        </a>
                        <a class="sidebar-link {{ request()->routeIs('admin.admission.masterlist') ? 'active' : '' }}"
                           href="{{ route('admin.admission.masterlist') }}">
                            <i class="bi bi-table"></i> Masterlist
                        </a>
                        <a class="sidebar-link {{ request()->routeIs('admin.admission.encoding-sheet') ? 'active' : '' }}"
                           href="{{ route('admin.admission.encoding-sheet') }}">
                            <i class="bi bi-grid-3x3"></i> Encoding Sheet
                        </a>
                        <a class="sidebar-link {{ request()->routeIs('admin.admission.answer-key.*') ? 'active' : '' }}"
                           href="{{ route('admin.admission.index') }}#answer-key">
                            <i class="bi bi-key-fill"></i> Answer Key
                        </a>
                        <a class="sidebar-link {{ request()->routeIs('admin.admission.scan-paper*') ? 'active' : '' }}"
                           href="{{ route('admin.admission.scan-paper') }}">
                            <i class="bi bi-qr-code-scan"></i> OMR Scanner
                        </a>
                        <a class="sidebar-link {{ request()->routeIs('admin.admission.analytics', 'admin.admission.report') ? 'active' : '' }}"
                           href="{{ route('admin.admission.analytics') }}">
                            <i class="bi bi-bar-chart-line-fill"></i> Analytics
                        </a>
                        <a class="sidebar-link {{ request()->routeIs('admin.admission.archive') ? 'active' : '' }}"
                           href="{{ route('admin.admission.archive') }}">
                            <i class="bi bi-archive"></i> Archive
                        </a>
                    </div>
                </details>
                @endif

                {{-- ── 2. TESTING REQUEST (Parent Accordion / Sub-menu) ── --}}
                <details class="sb-group"
                         @if($activeGuidanceModule && in_array($activeGuidanceModule,['psychological','personality','career'])) open @endif>
                    <summary>Request Testing</summary>
                    <div class="sb-sub">
                        <a class="sidebar-link {{ request()->routeIs("{$role}.psychological.*") ? 'active' : '' }}"
                           href="{{ route("{$role}.psychological.index") }}"
                           data-module-navigation="psychological">
                            <i class="bi bi-brain"></i> Psychological Assessment
                        </a>
                        <a class="sidebar-link {{ request()->routeIs("{$role}.personality.*","{$role}.personality") ? 'active' : '' }}"
                           href="{{ route("{$role}.personality.index") }}"
                           data-module-navigation="personality">
                            <i class="bi bi-person-badge"></i> Personality Test
                        </a>
                        <a class="sidebar-link {{ request()->routeIs("{$role}.career.*","{$role}.career") ? 'active' : '' }}"
                           href="{{ route("{$role}.career.index") }}"
                           data-module-navigation="career">
                            <i class="bi bi-compass"></i> Career Test
                        </a>
                    </div>
                </details>

                {{-- ── 3. GOOD MORAL ── --}}
                <a class="sidebar-link {{ request()->routeIs("{$role}.good-moral","{$role}.good-moral.*") ? 'active' : '' }}"
                   href="{{ route("{$role}.good-moral") }}"
                   data-module-navigation="good-moral">
                    <i class="bi bi-patch-check"></i> Good Moral
                    <span class="sb-badge ms-auto">Service</span>
                </a>

                {{-- ── 4. EXIT FORM ── --}}
                <a class="sidebar-link {{ request()->routeIs("{$role}.exit-form","{$role}.exit-form.*") ? 'active' : '' }}"
                   href="{{ route("{$role}.exit-form") }}"
                   data-module-navigation="exit-form">
                    <i class="bi bi-door-open"></i> Exit Form
                    <span class="sb-badge ms-auto">Service</span>
                </a>

                {{-- ── 5. ANALYTICS (Dedicated Main Navigation Button) ── --}}
                <a class="sidebar-link {{ $analyticsActive ? 'active' : '' }}"
                   href="{{ route($isAdmin ? 'admin.analytics' : 'staff.analytics') }}">
                    <i class="bi bi-bar-chart-line-fill"></i> Analytics
                    <span class="sb-badge ms-auto">Guidance</span>
                </a>

                {{-- 6. ARCHIVE (Dedicated Main Navigation Link) --}}
                <a class="sidebar-link {{ (request()->routeIs('admin.archive') && request()->query('section') !== 'admission') || request()->routeIs('staff.guidance-appointments.archive') ? 'active' : '' }}"
                   href="{{ route($isAdmin ? 'admin.archive' : 'staff.guidance-appointments.archive') }}">
                    <i class="bi bi-archive"></i> Archive
                </a>

                {{-- ── 7. SETTINGS (Admin Only) ── --}}
                @if ($isAdmin || $isStaff)
                <a class="sidebar-link {{ request()->routeIs($role.'.settings.*') ? 'active' : '' }}"
                   href="{{ route($role.'.settings.index') }}">
                    <i class="bi bi-sliders"></i> Settings
                    <span class="sb-badge ms-auto">Config</span>
                </a>
                @endif

            </nav>
        </div>

        {{-- Mobile & Desktop Sticky Logout Button --}}
        <div class="sidebar-logout mt-auto pt-3">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn btn-outline-light w-100 fw-bold d-flex align-items-center justify-content-center py-2 rounded-3 shadow-xs">
                    <i class="bi bi-box-arrow-left me-2"></i>Logout
                </button>
            </form>
        </div>
    </div>
</aside>
@endif
