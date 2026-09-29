<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { size: A4 {{ $orientation }}; margin: {{ count($certificates) ? '15mm 18mm' : '16mm 12mm' }}; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 10px; color: #111; }
        .document-header { text-align: center; border-bottom: 2px solid #17305f; padding-bottom: 10px; }
        h1 { font-size: 16px; color: #17305f; } h2 { font-size: 14px; }
        .metadata { margin: 12px 0; } .metadata p { margin: 3px 0; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #999; padding: 6px; overflow-wrap: anywhere; vertical-align: top; }
        th { background: #e8edf5; } thead { display: table-header-group; }
        tr { page-break-inside: avoid; } .signatures { margin-top: 45px; page-break-inside: avoid; }
        .signatures td { border: 0; width: 50%; text-align: center; }
        .certificate { box-sizing: border-box; width: 100%; min-height: 138mm; border: 4px double #222; padding: 11mm 14mm 10mm; font-family: Georgia, 'Times New Roman', serif; font-size: 13px; line-height: 1.85; page-break-inside: avoid; }
        .certificate + .certificate { page-break-before: always; }
        .certificate .document-header { border: 0; padding: 0; margin-bottom: 8mm; }
        .certificate .document-header h1 { color: #111; font-family: Georgia, 'Times New Roman', serif; font-size: 16px; text-transform: uppercase; margin: 0; }
        .certificate .document-header .campus, .certificate .document-header .office { font-size: 13px; line-height: 1.25; }
        .certificate h2 { font-family: Georgia, 'Times New Roman', serif; font-size: 19px; margin: 8mm 0 9mm; }
        .certificate-body { text-align: left; }
        .certificate-body p { margin: 0 0 5px; }
        .certificate-statement { line-height: 2; margin-bottom: 4px !important; }
        .certificate-signature { width: 43%; margin: 17mm 0 0 auto; text-align: center; line-height: 1.25; }
        .certificate-signature .line { border-top: 1px solid #222; padding-top: 3px; }
        .no-print { margin: 15px 0; }
        @media print {
            .no-print, nav, .sidebar, .app-sidebar, .app-header, header:not(.document-header) { display: none !important; }
            body { margin: 0; } th { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
        }
    </style>
</head>
<body>
    @if($format === 'html')
        <div class="no-print"><button type="button" onclick="window.print()">Print document</button></div>
    @endif
    @if(count($certificates) === 0)
        <header class="document-header">
            <h1>{{ \App\Services\DocumentExportService::UNIVERSITY }}</h1>
            <div>{{ $office }}</div>
            <h2>{{ $title }}</h2>
        </header>
        <div class="metadata">
            @foreach($metadata as $label => $value)<p><strong>{{ $label }}:</strong> {{ $value }}</p>@endforeach
            @foreach($summary as $label => $value)<p><strong>{{ $label }}:</strong> {{ $value }}</p>@endforeach
        </div>
    @endif
    @foreach($certificates as $certificate)
        <article class="certificate">
            <header class="document-header">
                <h1>Pangasinan State University</h1>
                <div class="campus">San Carlos Campus</div>
                <div class="office">Guidance and Counseling Office</div>
                <h2>{{ $title }}</h2>
            </header>
            <div class="certificate-body">
                <p class="certificate-statement">This certifies that <strong>{{ $certificate['name'] }}</strong>, student number <strong>{{ $certificate['student_number'] }}</strong>, enrolled in <strong>{{ $certificate['course'] }}</strong>, {{ $certificate['statement'] }}</p>
                <p><strong>Purpose:</strong> {{ $certificate['purpose'] }}</p>
                <p><strong>Issued:</strong> {{ $certificate['issued_at'] }}</p>
            </div>
            <div class="certificate-signature"><div class="line">Guidance Counselor</div></div>
        </article>
    @endforeach
    @if(count($columns))
        <table>
            <thead><tr>@foreach($columns as $column)<th>{{ $column }}</th>@endforeach</tr></thead>
            <tbody>@foreach($rows as $row)<tr>@foreach($row as $value)<td>{{ $value ?? '-' }}</td>@endforeach</tr>@endforeach</tbody>
        </table>
        @include('exports.signatures')
    @endif
    @if($autoPrint && $format === 'html')
        <script>window.onload = function() { window.print(); };</script>
    @endif
</body>
</html>
