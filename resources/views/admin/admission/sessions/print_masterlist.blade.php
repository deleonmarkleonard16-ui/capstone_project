<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Session Masterlist — {{ $session->session_name }}</title>
    <style>
        @page { size: A4 landscape; margin: 0; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 10mm;
            color: #111;
            background: #fff;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10px;
        }
        .document { width: 100%; margin: 0 auto; }
        .toolbar { margin-bottom: 12px; text-align: right; }
        .toolbar button {
            padding: 8px 14px;
            border: 1px solid #143d80;
            border-radius: 4px;
            color: #fff;
            background: #143d80;
            cursor: pointer;
        }
        .official-header {
            position: relative;
            min-height: 24mm;
            padding: 1mm 24mm 4mm;
            text-align: center;
            border-bottom: 2px solid #143d80;
        }
        .official-header img {
            position: absolute;
            top: 0;
            left: 2mm;
            width: 21mm;
            height: 21mm;
            object-fit: contain;
        }
        .university { margin: 0; font-size: 18px; color: #143d80; text-transform: uppercase; }
        .office { margin: 3px 0 0; font-size: 12px; font-weight: 700; }
        .document-title { margin: 7px 0 0; font-size: 15px; text-transform: uppercase; letter-spacing: .5px; }
        .meta {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 5px 18px;
            margin: 5mm 0 4mm;
            padding: 3mm;
            border: 1px solid #888;
            background: #f7f8fa;
        }
        .meta div { min-width: 0; }
        .meta strong { display: inline-block; margin-right: 4px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #555; padding: 5px 4px; vertical-align: middle; }
        th { background: #e8edf5; font-size: 9px; text-align: center; text-transform: uppercase; }
        tbody tr { height: 11mm; break-inside: avoid; page-break-inside: avoid; }
        .center { text-align: center; }
        .mono { font-family: Consolas, "Courier New", monospace; }
        .name { font-weight: 700; text-transform: uppercase; }
        .remarks { height: 10mm; }
        .document-footer {
            display: flex;
            justify-content: space-between;
            margin-top: 5mm;
            font-size: 9px;
            color: #444;
        }
        @media print {
            html, body { width: 100%; min-height: 100%; }
            body { padding: 10mm; }
            .no-print { display: none !important; }
            .meta, th { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>
<div class="toolbar no-print">
    <button type="button" onclick="window.print()">Print Masterlist</button>
</div>

<main class="document">
    <header class="official-header">
        <img src="{{ asset('images/psu-logo.png') }}" alt="Pangasinan State University logo">
        <h1 class="university">Pangasinan State University — San Carlos Campus</h1>
        <p class="office">Guidance &amp; Counseling / Testing Office</p>
        <h2 class="document-title">Official Admission Test Session Masterlist</h2>
    </header>

    <section class="meta" aria-label="Session details">
        <div><strong>Admission Cycle:</strong> {{ $cycle->name ?? $cycle->cycle_name ?? '—' }}</div>
        <div><strong>Session Name:</strong> {{ $session->session_name }}</div>
        <div><strong>Venue:</strong> {{ $session->room ?: 'Main Testing Hall' }}</div>
        <div><strong>Date &amp; Time:</strong> {{ optional($session->start_time)->format('F d, Y · h:i A') ?: 'Not scheduled' }}</div>
        <div><strong>Masterlist Range:</strong> #{{ $session->start_number }}–#{{ $session->end_number }}</div>
        <div><strong>Total Assigned:</strong> {{ $applicants->count() }} examinee(s)</div>
    </section>

    <table>
        <colgroup>
            <col style="width:4%"><col style="width:12%"><col style="width:23%"><col style="width:6%">
            <col style="width:16%"><col style="width:7%"><col style="width:13%"><col style="width:19%">
        </colgroup>
        <thead>
            <tr>
                <th>#</th>
                <th>Application Number</th>
                <th>Full Name<br>(Last Name, First Name, Middle Name)</th>
                <th>Sex</th>
                <th>Course Choice</th>
                <th>GWA</th>
                <th>Exam Status / Score</th>
                <th>Proctor Signature / Remarks</th>
            </tr>
        </thead>
        <tbody>
            @forelse($applicants as $index => $applicant)
                <tr>
                    <td class="center">{{ $index + 1 }}</td>
                    <td class="center mono">{{ $applicant->application_number }}</td>
                    <td class="name">{{ $applicant->full_name }}</td>
                    <td class="center">{{ $applicant->sex ?: '—' }}</td>
                    <td>{{ \App\Support\CourseCatalog::label($applicant->course_choice) }}</td>
                    <td class="center mono">{{ $applicant->gwa !== null ? number_format((float) $applicant->gwa, 2) : '—' }}</td>
                    <td class="center">
                        @if($applicant->submitted_at)
                            <strong>Submitted</strong><br>
                            Score: {{ number_format((float) $applicant->exam_score, 2) }} / {{ (int) ($cycle->total_items ?: 80) }}<br>
                            Stanine: {{ $applicant->stanine_score ?? '—' }}
                        @else
                            Pending
                        @endif
                    </td>
                    <td class="remarks"></td>
                </tr>
            @empty
                <tr><td colspan="8" class="center">No examinees are assigned to this session.</td></tr>
            @endforelse
        </tbody>
    </table>

    <footer class="document-footer">
        <span>Prepared for: PSU-CAT Session Administration</span>
        <span>Generated: {{ now()->format('F d, Y · h:i A') }}</span>
    </footer>
</main>

<script>
    window.addEventListener('load', function () { window.print(); });
</script>
</body>
</html>
