<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PSU-CAT Masterlist — {{ $batch->batch_name }}</title>
    <style>
        @page { size: A4 portrait; margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #e9edf3; font-family: Arial, Helvetica, sans-serif; color: #111; }
        .toolbar { width: 210mm; margin: 10px auto; text-align: right; }
        .toolbar button { padding: 9px 15px; color: #fff; background: #123c85; border: 0; border-radius: 4px; font-weight: 700; cursor: pointer; }
        .paper { position: relative; width: 210mm; min-height: 297mm; margin: 0 auto; padding: 0 13mm 22mm; background: #fff; overflow: hidden; }
        .header-band { margin: 0 -13mm; padding: 8mm 12mm 5mm; background: #fff900; border-top: 3px solid #101010; border-bottom: 3px solid #fff900; }
        .header-table { width: 100%; border-collapse: collapse; }
        .header-table td { border: 0; vertical-align: middle; }
        .seal { width: 29mm; height: 29mm; object-fit: contain; }
        .bagong { width: 31mm; max-height: 25mm; object-fit: contain; }
        .republic { font-family: Georgia, serif; font-size: 10pt; margin-bottom: 1mm; }
        .university-image { width: 86mm; max-height: 13mm; object-fit: contain; }
        .university-fallback { display: block; font: bold 22pt Georgia, serif; }
        .campus { font-family: Georgia, serif; font-size: 9pt; margin: 1mm 0; }
        .contacts { display: flex; justify-content: center; align-items: center; gap: 3mm; font-size: 6.5pt; white-space: nowrap; }
        .yellow-rule { height: 3mm; margin: 0 -13mm; background: #fff900; border-bottom: 1mm solid #d8d8d8; }
        .title { margin: 6mm 0 4mm; text-align: center; font-size: 10.5pt; font-weight: 700; text-transform: uppercase; }
        .schedule { display: grid; grid-template-columns: 1.3fr 1.3fr .8fr; gap: 4mm; margin: 0 8mm 4mm; font-size: 8.5pt; font-weight: 700; text-transform: uppercase; }
        .schedule .value { color: #1174ba; text-decoration: underline; }
        .content { display: grid; grid-template-columns: 1fr 33mm; gap: 8mm; align-items: start; }
        table.masterlist { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 7pt; }
        .masterlist th, .masterlist td { border: .25mm solid #575757; padding: 1px 2px; height: 4.1mm; vertical-align: middle; }
        .masterlist th { text-align: center; font-size: 6.7pt; font-weight: 700; text-transform: uppercase; }
        .masterlist td:first-child { text-align: center; }
        .name { text-transform: uppercase; }
        .note { margin-top: 52mm; border: .35mm solid #666; padding: 4mm 2.5mm; text-align: center; font-size: 8.4pt; line-height: 1.12; text-transform: uppercase; }
        .note strong { display: block; margin-bottom: 3mm; text-align: left; }
        .footer-band { position: absolute; right: 0; bottom: 0; left: 0; padding: 2mm 4mm 1.5mm; background: #fff900; border-top: 1px solid #e6d900; }
        .footer-band img { display: block; width: 100%; height: 9mm; object-fit: contain; }
        .footer-text { margin-top: 1mm; text-align: center; font-size: 5.3pt; line-height: 1.2; }
        .empty { padding: 8mm; text-align: center; }
        @media print { body { background: #fff; } .toolbar { display: none; } .paper { width: 210mm; min-height: 297mm; margin: 0; } .header-band, .yellow-rule, .footer-band { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
    </style>
</head>
<body>
@php
    $sealPath = public_path('images/psu-template/image1.png');
    if (!is_file($sealPath)) $sealPath = public_path('images/psu-logo.png');
    $seal = is_file($sealPath) ? 'data:image/png;base64,'.base64_encode(file_get_contents($sealPath)) : '';
    $titlePath = public_path('images/psu-template/psu_title_gothic.png');
    $titleImage = is_file($titlePath) ? 'data:image/png;base64,'.base64_encode(file_get_contents($titlePath)) : '';
    $bagongPath = public_path('images/psu-template/bagong_pilipinas.png');
    $bagong = is_file($bagongPath) ? 'data:image/png;base64,'.base64_encode(file_get_contents($bagongPath)) : '';
    $footerPath = public_path('images/psu-template/image8.png');
    if (!is_file($footerPath)) $footerPath = public_path('images/psu-template/image8.jpg');
    $footer = is_file($footerPath) ? 'data:image/'.(str_ends_with($footerPath, '.png') ? 'png' : 'jpeg').';base64,'.base64_encode(file_get_contents($footerPath)) : '';
    $applicants = $sessions->flatMap(fn ($session) => $session->applicants)->values();
    $times = $sessions->pluck('start_time')->filter();
    $timeLabel = $times->count() === 1 ? $times->first()->format('h:i A') : 'MULTIPLE SESSIONS';
@endphp
<div class="toolbar"><button type="button" onclick="window.print()">Print Masterlist</button></div>
<main class="paper">
    <header class="header-band">
        <table class="header-table"><tr>
            <td style="width:32mm"><img class="seal" src="{{ $seal }}" alt="Pangasinan State University seal"></td>
            <td style="text-align:center">
                <div class="republic">Republic of the Philippines</div>
                @if($titleImage)<img class="university-image" src="{{ $titleImage }}" alt="Pangasinan State University">@else<div class="university-fallback">Pangasinan State University</div>@endif
                <div class="campus">Lingayen, Pangasinan</div>
                <div class="contacts">www.psu.edu.ph &nbsp; • &nbsp; president@psu.edu.ph &nbsp; • &nbsp; www.facebook.com/PSUroars</div>
            </td>
            <td style="width:34mm;text-align:right">@if($bagong)<img class="bagong" src="{{ $bagong }}" alt="Bagong Pilipinas">@endif</td>
        </tr></table>
    </header>
    <div class="yellow-rule"></div>
    <h1 class="title">List of Examinees for PSU-SC CAT ({{ $batch->batch_name }})</h1>
    <div class="schedule">
        <div>Date of Test: <span class="value">{{ optional($batch->batch_date)->format('F d, Y') ?: 'Not set' }}</span></div>
        <div>Venue: <span class="value">{{ $batch->room ?: 'Not set' }}</span></div>
        <div>Time: <span class="value">{{ $timeLabel }}</span></div>
    </div>
    <section class="content">
        <table class="masterlist">
            <colgroup><col style="width:7%"><col style="width:23%"><col style="width:33%"><col style="width:24%"><col style="width:13%"></colgroup>
            <thead><tr><th>No.</th><th>Last Name</th><th>Given Name</th><th>Middle Name</th><th>Course</th></tr></thead>
            <tbody>
                @forelse($applicants as $applicant)
                    <tr><td>{{ $loop->iteration }}</td><td class="name">{{ $applicant->last_name }}</td><td class="name">{{ $applicant->first_name }}</td><td class="name">{{ $applicant->middle_name }}</td><td class="name">{{ $applicant->course_choice }}</td></tr>
                @empty
                    <tr><td colspan="5" class="empty">No examinees are assigned to this batch.</td></tr>
                @endforelse
            </tbody>
        </table>
        <aside class="note"><strong>Note:</strong>Those applicants whose names are not included in the list must check their application status and follow the given instruction to qualify for the last batch of examination.</aside>
    </section>
    <footer class="footer-band">
        @if($footer)<img src="{{ $footer }}" alt="Pangasinan State University footer">@endif
        <div class="footer-text">Alaminos City · Asingan · Bayambang · Binmaley · Infanta · San Carlos City · Sta. Maria · Urdaneta City &nbsp;|&nbsp; School of Advanced Studies · Open University Systems</div>
    </footer>
</main>
</body>
</html>
