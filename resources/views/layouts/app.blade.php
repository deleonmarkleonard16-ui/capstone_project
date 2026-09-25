<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @if (auth()->check() || request()->routeIs('login'))
        <script>
            // History snapshots must revalidate the session with the server.
            window.addEventListener('pageshow', function (event) {
                if (event.persisted) window.location.reload();
            });
        </script>
    @endif
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
        .brand-logo { width: 42px; height: 42px; object-fit: contain; background: transparent; }
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
        .sidebar-account small { display: block; color: #a8bfeb; font-size: 11px; margin-bottom: 4px; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; }
        .sidebar-account .role-pill {
            display: inline-block; font-size: 11px; font-weight: 800; padding: 3px 14px;
            border-radius: 9999px; letter-spacing: .05em; text-transform: uppercase;
        }
        .role-pill.admin { background: #ffc107; color: #000; border: 1px solid #e0a800; }
        .role-pill.staff { background: #fff3cd; color: #664d03; border: 1px solid #ffe69c; }
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
        .module-tabs {
            display: flex; flex-wrap: nowrap; overflow-x: auto; -webkit-overflow-scrolling: touch;
            gap: .35rem; border-bottom: 1px solid #dee2e6; padding-bottom: 0; margin-bottom: 1rem;
            scrollbar-width: none; /* Firefox */
        }
        .module-tabs::-webkit-scrollbar { display: none; /* Chrome/Safari */ }
        .module-tabs .nav-item { flex-shrink: 0; }
        .module-tabs .nav-link {
            white-space: nowrap; color: var(--psu-blue); font-size: .75rem; font-weight: 500;
            border-radius: 8px 8px 0 0; padding: .4rem .75rem;
            transition: background .15s, color .15s;
        }
        .module-tabs .nav-link:not(.active) { color: #5f6f8b; }
        .module-tabs .nav-link:not(.active):hover {
            color: #17305f; background: rgba(15,63,151,.06); border-bottom-color: transparent;
        }
        .module-tabs .nav-link.active {
            background: #fff; border-color: #dee2e6 #dee2e6 #fff;
            color: var(--psu-blue-deep); font-weight: 700;
            border-bottom: 2px solid var(--psu-blue);
        }
        @media (min-width: 768px) {
            .module-tabs { flex-wrap: nowrap; gap: .5rem; }
            .module-tabs .nav-link { font-size: .85rem; padding: .55rem 1.1rem; font-weight: 600; }
        }

        /* ── Login page ── */
        .login-body {
            background: linear-gradient(rgba(8,25,61,.62),rgba(8,25,61,.68)),
                        url('{{ asset("images/psu-building.png") }}') center/cover no-repeat fixed;
        }
    </style>
    <link href="{{ asset('css/executive-responsive.css') }}" rel="stylesheet">
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
    <div class="{{ $user ? 'container-fluid px-3' : 'container' }} brand-bar flex items-center justify-between px-3 md:px-6 py-2 d-flex align-items-center justify-content-between gap-2 w-100">
        <div class="brand-identity d-flex align-items-center gap-2">
            <img src="{{ asset('images/psu-logo.png') }}" alt="PSU logo" class="brand-logo flex-shrink-0">
            <div class="brand-copy flex-grow-1">
                <div class="main truncate" title="Pangasinan State University - San Carlos Campus">Pangasinan State University – San Carlos Campus</div>
                <div class="sub hidden sm:block">Digital Management System for Guidance Testing and Admission</div>
            </div>
        </div>
        @if ($user)
            <div class="header-actions d-flex align-items-center gap-2 ms-auto">
                @include('partials.header')
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
                        data-bs-target="#appSidebar" aria-controls="appSidebar" aria-label="Open navigation">
                    <i class="bi bi-list"></i>
                </button>
            </div>
        @endif
    </div>
</header>

{{-- ═══ SIDEBAR NAVIGATION ═══ --}}
@include('layouts.navigation')

{{-- ═══ MAIN CONTENT ═══ --}}
<main class="{{ request()->routeIs('admin.analytics', 'staff.analytics') ? 'executive-main' : '' }} py-4 {{ $user ? 'has-sidebar' : '' }}">
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
