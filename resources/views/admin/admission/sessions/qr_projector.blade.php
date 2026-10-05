@php
    $checkinUrl = $session->checkinUrl();
    $qrDataUri = app(\App\Services\GuidanceQrService::class)->dataUri($checkinUrl);
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Project QR — {{ $session->session_name }}</title>
    <style>
        * { box-sizing: border-box; } body { min-height: 100vh; margin: 0; color: #fff; font-family: Arial, Helvetica, sans-serif; background: radial-gradient(circle at center, #0e2a6d, #061539 75%); }
        main { min-height: 100vh; padding: 3vw; display: flex; flex-direction: column; justify-content: space-between; gap: 2rem; }
        header, footer { display: flex; justify-content: space-between; align-items: center; gap: 1rem; } .brand { display: flex; gap: 14px; align-items: center; } .brand img { width: 58px; height: 58px; object-fit: contain; } h1, h2, p { margin: 0; } h1 { font-size: clamp(1.2rem, 2vw, 2rem); color: #ffc107; } .muted { color: #cbd8f5; }
        .content { display: flex; justify-content: center; align-items: center; gap: clamp(2rem, 7vw, 8rem); flex-wrap: wrap; } .qr { background: #fff; padding: 22px; border: 5px solid #ffc107; border-radius: 22px; } .qr img { display: block; width: min(42vw, 440px); height: min(42vw, 440px); min-width: 260px; min-height: 260px; }
        .details { max-width: 540px; } .details h2 { font-size: clamp(2rem, 4vw, 4rem); margin-bottom: .7rem; } .venue { color: #ffc107; font-size: clamp(1.2rem, 2vw, 1.8rem); margin-bottom: 1.5rem; } .notice { padding: 1rem 1.25rem; border: 1px solid #7994cf; background: #ffffff16; border-radius: 12px; line-height: 1.6; } button { cursor: pointer; border: 1px solid #fff; background: transparent; color: #fff; padding: .65rem 1rem; border-radius: 7px; font-weight: 700; } kbd { padding: 3px 7px; background: #45536d; border-radius: 4px; }
        @media print { button { display: none; } }
    </style>
</head>
<body>
<main id="projector">
    <header><div class="brand"><img src="{{ asset('images/psu-logo.png') }}" alt="PSU Logo"><div><h1>Pangasinan State University — San Carlos Campus</h1><p class="muted">PSU-CAT Venue Check-In</p></div></div><button id="fullscreen" type="button">Enter Fullscreen</button></header>
    <section class="content"><div class="qr"><img src="{{ $qrDataUri }}" alt="Venue check-in QR code"></div><div class="details"><p class="muted">SCAN TO CHECK IN</p><h2>{{ $session->session_name }}</h2><p class="venue">{{ $session->room ?: 'Main Testing Hall' }}</p><div class="notice"><strong>Examinees:</strong> Scan the QR code with your phone, verify your registered name, then wait for the proctor to launch the examination.<br><br><strong>Schedule:</strong> {{ $session->start_time?->format('F d, Y · h:i A') ?: 'TBA' }}</div></div></section>
    <footer><span class="muted">If QR scanning is unavailable: {{ $checkinUrl }}</span><span>Press <kbd>ESC</kbd> to leave fullscreen</span></footer>
</main>
<script>
    const projector = document.getElementById('projector');
    document.getElementById('fullscreen').addEventListener('click', () => (projector.requestFullscreen?.() || projector.webkitRequestFullscreen?.()));
</script>
</body>
</html>
