@php
    $checkinUrl = $session->checkinUrl();
    $qrDataUri = app(\App\Services\GuidanceQrService::class)->dataUri($checkinUrl);
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Print Venue QR — {{ $session->session_name }}</title>
    <style>
        @page { size: A4 portrait; margin: 16mm; } body { margin: 0; font-family: Arial, Helvetica, sans-serif; color: #111; text-align: center; } .toolbar { margin: 16px; } button { cursor: pointer; color: #fff; background: #0f3f97; border: 0; border-radius: 6px; padding: 10px 16px; font-weight: 700; } .sheet { max-width: 700px; margin: 0 auto; border: 2px solid #0f3f97; padding: 28px; } .logo { width: 64px; height: 64px; object-fit: contain; } h1 { color: #0f3f97; font-size: 22px; margin: 8px 0 4px; } h2 { font-size: 20px; margin: 6px 0; } .qr { width: min(100%, 390px); margin: 24px auto 14px; padding: 14px; border: 2px solid #222; } .qr img { display: block; width: 100%; height: auto; } .url { overflow-wrap: anywhere; font: 12px monospace; } .details { margin: 12px 0; line-height: 1.6; } @media print { .toolbar { display: none; } .sheet { border-width: 1px; } }
    </style>
</head>
<body>
    <div class="toolbar"><button type="button" onclick="window.print()">Print QR Code</button></div>
    <main class="sheet"><img class="logo" src="{{ asset('images/psu-logo.png') }}" alt="PSU Logo"><h1>Pangasinan State University — San Carlos Campus</h1><p><strong>PSU-CAT Venue Check-In QR Code</strong></p><h2>{{ $session->session_name }}</h2><div class="details">{{ $session->room ?: 'Main Testing Hall' }}<br>{{ $session->start_time?->format('F d, Y · h:i A') ?: 'TBA' }}</div><div class="qr"><img src="{{ $qrDataUri }}" alt="Venue check-in QR code"></div><p>Scan this QR code with a phone to verify attendance.</p><p class="url">{{ $checkinUrl }}</p></main>
</body>
</html>
