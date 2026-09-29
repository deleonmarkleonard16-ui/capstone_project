<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { size: A4 {{ $orientation }}; margin: 16mm 12mm; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 10px; color: #111; }
        .document-header { text-align: center; border-bottom: 2px solid #17305f; padding-bottom: 10px; }
        h1 { font-size: 16px; color: #17305f; } h2 { font-size: 14px; }
        .metadata { margin: 12px 0; } .metadata p { margin: 3px 0; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #999; padding: 6px; overflow-wrap: anywhere; vertical-align: top; }
        th { background: #e8edf5; } thead { display: table-header-group; }
        tr { page-break-inside: avoid; } .signatures { margin-top: 45px; page-break-inside: avoid; }
        .signatures td { border: 0; width: 50%; text-align: center; }
        .certificate { font-size: 13px; line-height: 1.9; } .certificate + .certificate { page-break-before: always; }
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
    <header class="document-header">
        <h1>{{ \App\Services\DocumentExportService::UNIVERSITY }}</h1>
        <div>{{ $office }}</div>
        <h2>{{ $title }}</h2>
    </header>
    <div class="metadata">
        @foreach($metadata as $label => $value)<p><strong>{{ $label }}:</strong> {{ $value }}</p>@endforeach
        @foreach($summary as $label => $value)<p><strong>{{ $label }}:</strong> {{ $value }}</p>@endforeach
    </div>
    @foreach($certificates as $certificate)
        <article class="certificate">
            @if(!$loop->first)
                <header class="document-header">
                    <h1>{{ \App\Services\DocumentExportService::UNIVERSITY }}</h1>
                    <div>{{ $office }}</div><h2>{{ $title }}</h2>
                </header>
                <div class="metadata">@foreach($metadata as $label => $value)<p><strong>{{ $label }}:</strong> {{ $value }}</p>@endforeach</div>
            @endif
            @foreach($certificate as $paragraph)<p>{{ $paragraph }}</p>@endforeach
            @include('exports.signatures')
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
