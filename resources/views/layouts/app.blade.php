<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Digital Management System for Guidance Testing and Admission' }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --psu-blue: #0f3f97;
            --psu-blue-deep: #082b6d;
            --psu-gold: #f3c332;
            --page-bg: #f3f7ff;
            --ink-soft: #5f6f8b;
        }
        body {
            background:
                radial-gradient(circle at top left, rgba(243, 195, 50, 0.12), transparent 30%),
                linear-gradient(135deg, #f8fbff 0%, #eef4ff 48%, #fef6eb 100%);
            min-height: 100vh;
            color: #17305f;
        }
        .login-body {
            background:
                linear-gradient(rgba(8, 25, 61, 0.62), rgba(8, 25, 61, 0.68)),
                url('{{ asset('images/psu-building.png') }}') center/cover no-repeat fixed;
        }
        .brand-panel {
            background: linear-gradient(145deg, rgba(15, 63, 151, 0.96), rgba(8, 43, 109, 0.98));
            color: #fff;
        }
        .top-brand {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(15, 63, 151, 0.08);
        }
        .top-brand.guest {
            background: rgba(255, 255, 255, 0.22);
            border-bottom-color: rgba(255, 255, 255, 0.15);
        }
        .top-brand .brand-copy {
            line-height: 1.1;
        }
        .top-brand .brand-copy small {
            color: var(--ink-soft);
        }
        .top-brand.guest .brand-copy,
        .top-brand.guest .brand-copy small {
            color: #fff;
        }
        .brand-logo {
            width: 54px;
            height: 54px;
            object-fit: contain;
            border-radius: 50%;
            background: #fff;
            box-shadow: 0 0.5rem 1rem rgba(8, 43, 109, 0.14);
        }
        .page-card {
            border: 0;
            border-radius: 1.25rem;
            box-shadow: 0 1.25rem 3rem rgba(15, 23, 42, 0.09);
            overflow: hidden;
        }
        .stat-card {
            border: 0;
            border-radius: 1.15rem;
            box-shadow: 0 0.85rem 2rem rgba(15, 23, 42, 0.08);
            background: rgba(255, 255, 255, 0.94);
        }
        .question-block {
            border: 1px solid #dbe4ff;
            border-radius: 1rem;
            background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
            padding: 1rem;
            height: 100%;
        }
        .timer-box { position: sticky; top: 1rem; z-index: 1000; }
        .friendly-shell {
            background: rgba(255, 255, 255, 0.74);
            border: 1px solid rgba(255, 255, 255, 0.6);
            border-radius: 1.5rem;
            padding: 1rem;
            box-shadow: 0 1rem 2.5rem rgba(15, 23, 42, 0.06);
            backdrop-filter: blur(8px);
        }
        .section-title {
            font-weight: 700;
            color: var(--psu-blue-deep);
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--psu-blue), #1858d3);
            border-color: var(--psu-blue);
        }
        .btn-outline-primary {
            border-color: rgba(15, 63, 151, 0.28);
            color: var(--psu-blue);
        }
        .table thead th {
            color: var(--ink-soft);
            font-size: 0.84rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .module-card {
            border: 0;
            border-radius: 1.25rem;
            background: rgba(255, 255, 255, 0.95);
            box-shadow: 0 0.85rem 2rem rgba(15, 23, 42, 0.08);
            height: 100%;
            position: relative;
        }
        .module-card::before {
            content: "";
            position: absolute;
            inset: 0 auto 0 0;
            width: 6px;
            background: linear-gradient(180deg, #0f3f97, #f3c332);
            border-radius: 1.25rem 0 0 1.25rem;
        }
        .module-chip {
            display: inline-flex;
            align-items: center;
            padding: 0.4rem 0.85rem;
            border-radius: 999px;
            font-size: 0.8rem;
            font-weight: 700;
            letter-spacing: 0.03em;
        }
        .guidance-hero {
            background: linear-gradient(135deg, rgba(15, 63, 151, 0.98), rgba(8, 43, 109, 0.98));
            color: #fff;
            border-radius: 1.25rem;
        }
        .guidance-soft {
            background: linear-gradient(135deg, rgba(243, 195, 50, 0.12), rgba(15, 63, 151, 0.06));
            border: 1px solid rgba(15, 63, 151, 0.08);
            border-radius: 1rem;
        }
        .alert {
            border: 0;
            border-radius: 1rem;
        }
        .app-sidebar {
            --bs-offcanvas-width: 260px;
            width: 260px;
            background: linear-gradient(180deg, #0f3f97, #082b6d) !important;
            color: #fff;
        }
        .sidebar-body { display: flex; flex-direction: column; padding: 1.25rem; }
        .sidebar-brand { color: #fff; text-decoration: none; font-size: 1.1rem; }
        .sidebar-link, .sidebar-group summary {
            display: block; padding: .8rem 1rem; border-radius: .65rem;
            color: #e5edff; text-decoration: none; cursor: pointer;
        }
        .sidebar-link:hover, .sidebar-group summary:hover, .sidebar-link.active {
            color: #fff; background: rgba(255, 255, 255, .13);
        }
        .sidebar-link.active { box-shadow: inset 3px 0 var(--psu-gold); }
        .sidebar-group summary { display: list-item; list-style-position: inside; }
        .sidebar-submenu { margin: .25rem 0 .75rem .75rem; border-left: 1px solid #ffffff40; padding-left: .4rem; }
        .sidebar-submenu .sidebar-link { font-size: .9rem; }
        .sidebar-logout { margin-top: auto; padding-top: 2rem; }
        @media (min-width: 992px) {
            .app-sidebar { position: fixed; inset: 0 auto 0 0; height: 100vh; z-index: 1030; }
            .app-sidebar .sidebar-body { height: 100%; overflow-y: auto; }
            .has-sidebar { margin-left: 260px; }
        }
        .app-sidebar { --bs-offcanvas-width:242px; width:242px; background:linear-gradient(160deg,#102f6c,#174597)!important; }
        .sidebar-body { padding:20px 15px; }
        .sidebar-account { padding:14px 13px; border:1px solid #ffffff30; border-radius:16px; background:#ffffff18; margin-bottom:22px; }
        .sidebar-account small { display:block; color:#a8bfeb; font-size:12px; margin-bottom:4px; }
        .sidebar-account strong { font-size:14px; }
        .sidebar-caption { color:#e2eaff; font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.08em; margin-bottom:14px; }
        .sidebar-link,.sidebar-group summary { font-size:13px; font-weight:600; padding:13px; margin-bottom:3px; }
        .sidebar-group summary { list-style:none; display:flex; justify-content:space-between; align-items:center; }
        .sidebar-group summary::-webkit-details-marker { display:none; }
        .sidebar-group summary::after { content:'▸'; color:#b8c8e7; }
        .sidebar-group[open] summary { background:#ffffff25; border:1px solid #ffffff25; }
        .sidebar-group[open] summary::after { transform:rotate(90deg); }
        .sidebar-submenu { margin:3px 0 20px 10px; padding:0; border:0; }
        .sidebar-submenu .sidebar-link { font-size:12px; padding:10px 12px; }
        .sidebar-link.active { background:#ffffff29; box-shadow:none; }
        .sidebar-logout { margin-top:20px; padding-top:0; }
        .sidebar-logout button { background:#f8faff; color:#10234a; font-weight:700; padding:13px; border-radius:14px; font-size:13px; }
        .top-brand .brand-logo { width:46px; height:46px; }
        .top-brand .brand-copy small { font-size:12px; }
        .top-brand-row { display:flex; align-items:center; justify-content:space-between; gap:1rem; width:100%; }
        .top-brand-identity { display:flex; align-items:center; gap:.75rem; min-width:0; }
        .top-brand-identity .brand-logo { flex-shrink:0; }
        .top-brand-identity .brand-copy { min-width:0; }
        .top-brand-actions { display:flex; align-items:center; gap:1rem; flex-shrink:0; margin-left:auto; }
        .friendly-shell { padding:16px 14px 32px; border-radius:22px; }
        .friendly-shell h1 { font-size:24px; }
        .friendly-shell > .d-flex p { font-size:13px; }
        .page-card { border-radius:18px; }
        .table thead th { font-size:11px; letter-spacing:.02em; }
        @media(min-width:992px) {
            .top-brand.has-sidebar { margin-left:0; height:75px; }
            .top-brand.has-sidebar > div { max-width:none; width:100%; margin:0; padding-top:14px!important; padding-bottom:14px!important; }
            .app-sidebar { top:75px; height:calc(100vh - 75px); }
            main.has-sidebar { margin-left:242px; padding-top:20px!important; }
        }
    </style>
</head>
<body class="@yield('body_class')">
    @php($user = auth()->user())
    @php($rolePrefix = $user?->role ?? 'staff')
    @php($activeGuidanceModule = collect(['psychological', 'personality', 'career', 'good-moral', 'exit-form'])->first(fn ($module) => request()->routeIs($rolePrefix.'.'.$module, $rolePrefix.'.'.$module.'.*')))

    <div class="top-brand {{ $user ? 'has-sidebar' : 'guest' }}">
        <div class="{{ $user ? 'container-fluid px-4' : 'container' }} py-3">
            <div class="top-brand-row">
                <div class="top-brand-identity">
                    <img src="{{ asset('images/psu-logo.png') }}" alt="PSU logo" class="brand-logo">
                    <div class="brand-copy">
                        <div class="fw-bold">Pangasinan State University - San Carlos Campus</div>
                        <small>Digital Management System for Guidance Testing and Admission</small>
                    </div>
                </div>
                @if ($user)
                    <div class="top-brand-actions">
                        @if(in_array($user->role, ['admin', 'staff'], true))
                        <div class="dropdown" data-request-notifications data-feed-url="{{ route(auth()->user()->role.'.notifications.index') }}" data-clear-url="{{ route('api.notifications.clear-module') }}" data-current-module="{{ $activeGuidanceModule }}" data-csrf="{{ csrf_token() }}">
                            <button class="btn btn-outline-primary position-relative" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Open request notifications" title="Request notifications">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/></svg>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" data-notification-count hidden>0</span>
                            </button>
                            <div class="dropdown-menu dropdown-menu-end p-0 shadow" style="width:min(22rem,90vw);max-height:25rem;overflow:auto"><div class="p-3 border-bottom fw-semibold">Individual requests</div><div data-notification-list class="list-group list-group-flush"><p class="p-3 mb-0 text-muted">Loading notifications...</p></div></div>
                        </div>
                        @endif
                        <button class="btn btn-outline-primary d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#appSidebar" aria-controls="appSidebar" aria-label="Open navigation">Menu</button>

                    </div>
                @endif
            </div>
        </div>
    </div>

    @if ($user)
        <aside class="offcanvas-lg offcanvas-start app-sidebar" tabindex="-1" id="appSidebar" aria-labelledby="sidebarTitle">
            <div class="offcanvas-header">
                <h2 class="offcanvas-title h5" id="sidebarTitle">Navigation</h2>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" data-bs-target="#appSidebar" aria-label="Close navigation"></button>
            </div>
            <div class="sidebar-body">
                <div class="sidebar-account"><small>Signed in as</small><strong>{{ ucfirst($user->role) }}</strong></div><div class="sidebar-caption">Main Navigation</div>
                <nav aria-label="Main navigation">
                    <details class="sidebar-group" @if(request()->routeIs($rolePrefix.'.psychological.*', $rolePrefix.'.personality.*', $rolePrefix.'.career.*', $rolePrefix.'.personality', $rolePrefix.'.career')) open @endif>
                        <summary>Request Testing</summary>
                        <div class="sidebar-submenu">
                            <a class="sidebar-link {{ request()->routeIs($rolePrefix.'.psychological.*') ? 'active' : '' }}" data-module-navigation="psychological" href="{{ route($rolePrefix.'.psychological.index') }}">Psychological Assessment</a>
                            <a class="sidebar-link {{ request()->routeIs($rolePrefix.'.personality.*', $rolePrefix.'.personality') ? 'active' : '' }}" data-module-navigation="personality" href="{{ route($rolePrefix.'.personality.index') }}">Personality Test</a>
                            <a class="sidebar-link {{ request()->routeIs($rolePrefix.'.career.*', $rolePrefix.'.career') ? 'active' : '' }}" data-module-navigation="career" href="{{ route($rolePrefix.'.career.index') }}">Career Test</a>
                        </div>
                    </details>
                    <a class="sidebar-link {{ request()->routeIs($rolePrefix.'.good-moral', $rolePrefix.'.good-moral.*') ? 'active' : '' }}" data-module-navigation="good-moral" href="{{ route($rolePrefix.'.good-moral') }}">Good Moral</a>
                    <a class="sidebar-link {{ request()->routeIs($rolePrefix.'.exit-form', $rolePrefix.'.exit-form.*') ? 'active' : '' }}" data-module-navigation="exit-form" href="{{ route($rolePrefix.'.exit-form') }}">Exit Form</a>
                    @if($user->role === 'admin')<a class="sidebar-link {{ request()->routeIs('admin.admission.*') ? 'active' : '' }}" href="{{ route('admin.admission.index') }}">PSU-CAT Admission</a><a class="sidebar-link {{ request()->routeIs($rolePrefix.'.settings.*') ? 'active' : '' }}" href="{{ route($rolePrefix.'.settings.index') }}">System Settings</a>@endif
                </nav>
                <form class="sidebar-logout" method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn btn-outline-light w-100">Logout</button>
                </form>
            </div>
        </aside>
    @endif

    <main class="py-4 {{ $user ? 'has-sidebar' : '' }}">
        <div class="{{ $user ? 'container-fluid px-3 px-lg-4' : 'container' }}">
            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="alert alert-danger">{{ session('error') }}</div>
            @endif
            @if (session('warning'))
                <div class="alert alert-warning">{{ session('warning') }}</div>
            @endif
            @if ($errors->any())
                <div class="alert alert-danger">
                    <div class="fw-semibold mb-2">Please review the following:</div>
                    <ul class="mb-0">
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

    @if($user && in_array($user->role, ['admin', 'staff'], true))
    <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index:1100" data-request-toasts aria-live="polite"></div>
    <div class="modal fade" id="notification-review-modal" tabindex="-1" aria-labelledby="notification-review-title" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><div class="modal-content"><div class="modal-header"><h2 class="modal-title h5" id="notification-review-title">Individual request</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body" data-notification-review-body></div></div></div></div>
    @endif

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    @if($user && in_array($user->role, ['admin', 'staff'], true))<script src="{{ asset('js/guidance-notifications.js') }}" defer></script><script src="{{ asset('js/module-live-refresh.js') }}" defer></script>@endif
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.modal').forEach(function (m) {
                if (m.parentElement !== document.body) document.body.appendChild(m);
            });
        });
    </script>
    @stack('scripts')
</body>
</html>
