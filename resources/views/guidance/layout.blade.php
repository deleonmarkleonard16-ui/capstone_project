<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="referrer" content="no-referrer">
    <title>Guidance Testing | PSU San Carlos Campus</title>
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap.min.css') }}">
    <style>
        :root { --accent:#194896; --accent-deep:#102f6d; --tint:#eef4ff; --accent-border:#aabfe6; --focus:#3278df; --ink:#19345e; --muted:#65748a; }
        body.guidance-student { background:var(--tint); color:var(--ink); font:16px/1.5 system-ui,sans-serif; min-height:100vh; }
        .psu-header { background:#fff; border-bottom:4px solid var(--accent); }
        .psu-header-inner { max-width:1100px; margin:auto; padding:16px 20px; display:flex; align-items:center; gap:14px; }
        .psu-header img { width:50px; height:50px; object-fit:contain; flex:none; background:transparent; }
        .psu-header strong { display:block; line-height:1.25; }
        .psu-header small { display:block; color:var(--muted); line-height:1.35; }
        .guidance-main { max-width:1100px; padding:30px 20px; }
        .guidance-student .card { border:0; border-radius:18px; box-shadow:0 6px 24px #17305f0b; }
        .guidance-student .form-control { border:1px solid #b7c5d8; border-radius:7px; padding:11px; }
        .guidance-student .form-control:focus { border-color:var(--accent); box-shadow:0 0 0 .2rem #3278df33; }
        .guidance-student .btn { border-radius:7px; padding:10px 18px; font-weight:600; }
        .guidance-student .btn-primary { background:var(--accent); border-color:var(--accent); }
        .guidance-student .btn-primary:hover { background:var(--accent-deep); border-color:var(--accent-deep); }
        .guidance-student .btn-outline-primary { color:var(--accent); border-color:var(--accent-border); }
        .guidance-student .btn-outline-primary:hover { color:#fff; background:var(--accent); border-color:var(--accent); }
        .guidance-student :is(a,button,input):focus-visible { outline:3px solid var(--focus); outline-offset:2px; }
        .guidance-student .muted { color:var(--muted); }
        @media(max-width:650px) { .psu-header-inner { padding:15px; } .guidance-main { padding:20px 15px; } }
    </style>
    @stack('styles')
</head>
<body class="guidance-student">
<header class="psu-header"><div class="psu-header-inner"><img src="{{ asset('images/psu-logo.png') }}" alt="PSU logo"><div><strong>Pangasinan State University - San Carlos Campus</strong><small>Digital Management System for Guidance Testing and Admission</small></div></div></header>
<main class="container guidance-main">
    @if($errors->any())<div class="alert alert-danger" role="alert"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @yield('content')
</main>
@stack('scripts')
</body>
</html>
