<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Digital Management System for Guidance Testing and Admission' }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --psu-blue: #0f3f97;
            --psu-blue-deep: #082b6d;
            --psu-gold: #f3c332;
            --page-bg: #f3f7ff;
            --ink-soft: #5f6f8b;
            --sb-width: 242px;
        }
        body {
            background: radial-gradient(circle at top left, rgba(243,195,50,.12),transparent 30%),
                        linear-gradient(135deg,#f8fbff 0%,#eef4ff 48%,#fef6eb 100%);
            min-height: 100vh; color: #17305f;
        }
        /* ── Top brand bar ── */
        .top-brand {
            background: rgba(255,255,255,.92); backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(15,63,151,.08); height: 64px;
            display: flex; align-items: center;
        }
        .top-brand.guest { background: rgba(255,255,255,.22); border-bottom-color: rgba(255,255,255,.15); }
        .brand-logo { width: 42px; height: 42px; object-fit: contain; border-radius: 50%;
                      background: #fff; box-shadow: 0 .5rem 1rem rgba(8,43,109,.14); }
        .brand-copy { line-height: 1.15; }
        .brand-copy .main { font-weight: 700; font-size: .95rem; color: var(--psu-blue-deep); }
        .brand-copy .sub  { font-size: .72rem; color: var(--ink-soft); }
        .top-brand.guest .brand-copy .main,
        .top-brand.guest .brand-copy .sub { color: #fff; }

        /* ── Sidebar ── */
        .app-sidebar {
            --bs-offcanvas-width: var(--sb-width);
            width: var(--sb-width);
            background: linear-gradient(170deg,#0d2d6e,#153f99) !important;
            color: #fff;
        }
        @media (min-width: 992px) {
            .app-sidebar { position:fixed; inset:64px auto 0 0; height:calc(100vh - 64px); z-index:1030; overflow-y:auto; }
            main.has-sidebar { margin-left: var(--sb-width); padding-top: 1.5rem !important; }
            .top-brand.has-sidebar { margin-left: 0; }
        }
        .sidebar-body   { padding: 18px 14px 24px; display: flex; flex-direction: column; min-height: 100%; }
        .sidebar-account{
            padding: 12px 13px; border: 1px solid #ffffff30; border-radius: 14px;
            background: #ffffff18; margin-bottom: 18px;
        }
        .sidebar-account small { display: block; color: #a8bfeb; font-size: 11px; margin-bottom: 2px; }
        .sidebar-account .role-pill {
            display: inline-block; font-size: 11px; font-weight: 700; padding: 2px 10px;
            border-radius: 999px; letter-spacing: .03em;
        }
        .role-pill.admin { background: var(--psu-gold); color: #1a1a00; }
        .role-pill.staff { background: #3b72e8; color: #fff; }
        .sidebar-caption{
            color: #c2d0f0; font-size: 10px; font-weight: 700; text-transform: uppercase;
            letter-spacing: .08em; margin-bottom: 8px; margin-top: 6px;
        }
        /* links */
        .sidebar-link, .sb-group summary {
            display: flex; align-items: center; gap: 8px;
            font-size: 13px; font-weight: 600; padding: 11px 12px; margin-bottom: 2px;
            border-radius: 10px; color: #d9e6ff; text-decoration: none; cursor: pointer;
            transition: background .15s;
        }
        .sidebar-link:hover, .sb-group summary:hover { background: rgba(255,255,255,.14); color: #fff; }
        .sidebar-link.active { background: rgba(255,255,255,.2); color: #fff; border-left: 3px solid var(--psu-gold); }
        /* badge label (e.g. "Dashboard", "Records", "Admin") */
        .sb-badge {
            margin-left: auto; font-size: 10px; font-weight: 700; padding: 2px 8px;
            border-radius: 999px; background: rgba(255,255,255,.18); color: #c9deff;
            white-space: nowrap;
        }
        /* accordion group */
        .sb-group { margin-bottom: 2px; }
        .sb-group summary {
            list-style: none; display: flex; justify-content: space-between; align-items: center;
        }
        .sb-group summary::-webkit-details-marker { display: none; }
        .sb-group summary .sb-chevron { color: #8ba8d4; font-size: 11px; transition: transform .2s; }
        .sb-group[open] summary .sb-chevron { transform: rotate(90deg); }
        .sb-group[open] summary { background: rgba(255,255,255,.2); color: #fff; }
        /* sub-items */
        .sb-sub { padding: 4px 0 8px 12px; border-left: 1px solid rgba(255,255,255,.18); margin-left: 14px; }
        .sb-sub .sidebar-link { font-size: 12.5px; font-weight: 500; padding: 9px 12px; }
        .sb-sub .sidebar-link.active { border-left: 3px solid var(--psu-gold); background: rgba(255,255,255,.16); }
        /* logout */
        .sidebar-logout { margin-top: auto; padding-top: 18px; }
        .sidebar-logout button {
            background: rgba(255,255,255,.12); color: #d9e6ff; font-weight: 700;
            padding: 12px; border-radius: 12px; font-size: 13px; border: 1px solid rgba(255,255,255,.2);
            width: 100%;
        }
        .sidebar-logout button:hover { background: rgba(255,255,255,.22); color: #fff; }

        /* ── Content cards ── */
        .page-card  { border:0; border-radius:1.25rem; box-shadow:0 1.25rem 3rem rgba(15,23,42,.09); overflow:hidden; }
        .stat-card  { border:0; border-radius:1.15rem; box-shadow:0 .85rem 2rem rgba(15,23,42,.08); background:rgba(255,255,255,.94); }
        .module-card{ border:0; border-radius:1.25rem; background:rgba(255,255,255,.95); box-shadow:0 .85rem 2rem rgba(15,23,42,.08); height:100%; position:relative; }
        .module-card::before { content:""; position:absolute; inset:0 auto 0 0; width:6px; background:linear-gradient(180deg,#0f3f97,#f3c332); border-radius:1.25rem 0 0 1.25rem; }
        .friendly-shell{ background:rgba(255,255,255,.78); border:1px solid rgba(255,255,255,.6); border-radius:1.5rem; padding:20px 18px 32px; box-shadow:0 1rem 2.5rem rgba(15,23,42,.06); backdrop-filter:blur(8px); }
        .section-title{ font-weight:700; color:var(--psu-blue-deep); }
        .btn-primary  { background:linear-gradient(135deg,var(--psu-blue),#1858d3); border-color:var(--psu-blue); }
        .btn-outline-primary { border-color:rgba(15,63,151,.3); color:var(--psu-blue); }
        .table thead th{ color:var(--ink-soft); font-size:.82rem; text-transform:uppercase; letter-spacing:.04em; }
        .alert{ border:0; border-radius:1rem; }
        .guidance-hero{ background:linear-gradient(135deg,rgba(15,63,151,.98),rgba(8,43,109,.98)); color:#fff; border-radius:1.25rem; }
        .question-block{ border:1px solid #dbe4ff; border-radius:1rem; background:linear-gradient(180deg,#fff 0%,#f8fbff 100%); padding:1rem; }
        .timer-box{ position:sticky; top:1rem; z-index:1000; }

        /* ── Module nav tabs ── */
        .module-tabs .nav-link {
            color: var(--psu-blue); font-size: .85rem; font-weight: 600;
            border-radius: 8px 8px 0 0; padding: .55rem 1.1rem;
        }
        .module-tabs .nav-link.active { background: #fff; border-color: #dee2e6 #dee2e6 #fff; color: var(--psu-blue-deep); }

        /* ── Login page ── */
        .login-body {
            background: linear-gradient(rgba(8,25,61,.62),rgba(8,25,61,.68)),
                        url('{{ asset("images/psu-building.png") }}') center/cover no-repeat fixed;
        }
    </style>
</head>
<body class="@yield('body_class')">
@php
    $user        = auth()->user();
    $role        = $user?->role ?? 'guest';
    $isAdmin     = $role === 'admin';
    $isStaff     = $role === 'staff';
    $currentPath = request()->path();

    // Detect active admission sub-page
    $admissionActive = request()->routeIs('admin.admission.*', 'admin.sessions.*')
        || (request()->routeIs('admin.archive') && request()->query('section') === 'admission');

    // Detect active guidance module for the request-testing accordion
    $activeGuidanceModule = collect(['psychological','personality','career','good-moral','exit-form'])
        ->first(fn ($m) => request()->routeIs("{$role}.{$m}", "{$role}.{$m}.*"));
@endphp

{{-- ═══ TOP BRAND BAR ═══ --}}
<header class="top-brand {{ $user ? 'has-sidebar' : 'guest' }} sticky-top">
    <div class="{{ $user ? 'container-fluid px-3' : 'container' }} d-flex align-items-center gap-3 w-100">
        <img src="{{ asset('images/psu-logo.png') }}" alt="PSU logo" class="brand-logo flex-shrink-0">
        <div class="brand-copy flex-grow-1">
            <div class="main">Pangasinan State University – San Carlos Campus</div>
            <div class="sub">Digital Management System for Guidance Testing and Admission</div>
        </div>
        @if ($user)
            <div class="d-flex align-items-center gap-2 ms-auto">
                {{-- Notification bell (staff + admin) --}}
                @if (in_array($role, ['admin','staff'], true))
                <div class="dropdown"
                     data-request-notifications
                     data-feed-url="{{ route("{$role}.notifications.index") }}"
                     data-clear-url="{{ route('api.notifications.clear-module') }}"
                     data-current-module="{{ $activeGuidanceModule }}"
                     data-csrf="{{ csrf_token() }}">
                    <button class="btn btn-outline-primary position-relative"
                            type="button" data-bs-toggle="dropdown"
                            aria-label="Notifications" title="Request notifications">
                        <i class="bi bi-bell"></i>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"
                              data-notification-count hidden>0</span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end p-0 shadow"
                         style="width:min(22rem,90vw);max-height:25rem;overflow:auto">
                        <div class="p-3 border-bottom fw-semibold small">Individual requests</div>
                        <div data-notification-list class="list-group list-group-flush">
                            <p class="p-3 mb-0 text-muted small">Loading…</p>
                        </div>
                    </div>
                </div>
                @endif
                {{-- Mobile menu toggle --}}
                <button class="btn btn-outline-primary d-lg-none"
                        type="button" data-bs-toggle="offcanvas"
                        data-bs-target="#appSidebar" aria-controls="appSidebar">
                    <i class="bi bi-list"></i>
                </button>
            </div>
        @endif
    </div>
</header>

{{-- ═══ SIDEBAR ═══ --}}
@if ($user)
<aside class="offcanvas-lg offcanvas-start app-sidebar"
       tabindex="-1" id="appSidebar" aria-labelledby="sidebarTitle">
    <div class="offcanvas-header border-bottom border-white border-opacity-25">
        <h2 class="offcanvas-title h6 text-white" id="sidebarTitle">Navigation</h2>
        <button type="button" class="btn-close btn-close-white"
                data-bs-dismiss="offcanvas" data-bs-target="#appSidebar"
                aria-label="Close"></button>
    </div>
    <div class="sidebar-body">

        {{-- Account pill --}}
        <div class="sidebar-account">
            <small>Signed in as</small>
            <span class="role-pill {{ $isAdmin ? 'admin' : 'staff' }}">
                {{ $isStaff ? 'Guidance Staff' : ucfirst($role) }}
            </span>
            @if($user->name ?? null)
            <div class="text-white fw-semibold mt-1" style="font-size:13px">{{ $user->name }}</div>
            @endif
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
                <summary>
                    <span><i class="bi bi-clipboard2-pulse me-1"></i> Testing Request</span>
                    <span class="sb-badge">Modules&nbsp;<i class="bi bi-chevron-right sb-chevron"></i></span>
                </summary>
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
            <a class="sidebar-link {{ request()->routeIs("{$role}.analytics", 'admin.analytics', 'staff.analytics') ? 'active' : '' }}"
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

        <div class="sidebar-logout">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit"><i class="bi bi-box-arrow-left me-2"></i>Logout</button>
            </form>
        </div>
    </div>
</aside>
@endif

{{-- ═══ MAIN CONTENT ═══ --}}
<main class="py-4 {{ $user ? 'has-sidebar' : '' }}">
    <div class="{{ $user ? 'container-fluid px-3 px-lg-4' : 'container' }}">

        {{-- Flash messages --}}
        @if (session('success'))
            <div class="alert alert-success d-flex align-items-center gap-2">
                <i class="bi bi-check-circle-fill"></i> {{ session('success') }}
            </div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger d-flex align-items-center gap-2">
                <i class="bi bi-exclamation-triangle-fill"></i> {{ session('error') }}
            </div>
        @endif
        @if (session('warning'))
            <div class="alert alert-warning">{{ session('warning') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">
                <div class="fw-semibold mb-2"><i class="bi bi-exclamation-circle me-1"></i>Please review the following:</div>
                <ul class="mb-0 ps-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="{{ $user ? 'friendly-shell' : '' }}">
            @yield('content')
        </div>
    </div>
</main>

{{-- Toast container --}}
@if ($user && in_array($role, ['admin','staff'], true))
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index:1100"
     data-request-toasts aria-live="polite"></div>
<div class="modal fade" id="notification-review-modal" tabindex="-1"
     aria-labelledby="notification-review-title" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="notification-review-title">Individual request</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" data-notification-review-body></div>
        </div>
    </div>
</div>
@endif

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@if ($user && in_array($role, ['admin','staff'], true))
    <script src="{{ asset('js/guidance-notifications.js') }}" defer></script>
    <script src="{{ asset('js/module-live-refresh.js') }}" defer></script>
@endif
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Move all modals to body root to avoid z-index nesting issues
    document.querySelectorAll('.modal').forEach(function (m) {
        if (m.parentElement !== document.body) document.body.appendChild(m);
    });
});
</script>
@stack('scripts')
</body>
</html>
