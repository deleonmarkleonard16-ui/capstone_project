<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <style>
        @page { size: A4 portrait; margin: 50mm 13mm 30mm; }
        body { margin: 0; color: #182338; font: 10px/1.4 'DejaVu Sans', Arial, sans-serif; }
        .assessment-letterhead { position: fixed; top: -50mm; left: -13mm; width: 210mm; height: 297mm; z-index: -1000; }
        .document-header { text-align: center; border-bottom: 1px solid #17305f; padding-bottom: 3mm; margin-bottom: 3mm; }
        .document-header p { margin: 0; font-size: 9px; }
        h1 { font-size: 15px; margin: 1mm 0 0; color: #17305f; }
        h2 { color: #17305f; font-size: 11px; margin: 4mm 0 2mm; page-break-after: avoid; }
        .metadata { margin-bottom: 4mm; }
        .metadata p { margin: 1mm 0; overflow-wrap: break-word; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; margin-bottom: 3mm; }
        th, td { border: 1px solid #b7c1ce; padding: 5px 7px; text-align: left; vertical-align: top; overflow-wrap: break-word; }
        th { background: #edf1f6; color: #17305f; font-weight: bold; }
        .metric-label { width: 68%; }
        .record-label { width: 28%; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        .signatures { margin-top: 8mm; page-break-inside: avoid; }
        .signatures td { border: 0; text-align: center; width: 50%; }
        .no-print { margin-bottom: 4mm; text-align: center; }
        @if($format === 'html')
        @media screen {
            body { box-sizing: border-box; position: relative; z-index: 0; width: 210mm; min-height: 297mm; margin: 0 auto; padding: 50mm 13mm 30mm; }
            .assessment-letterhead { position: absolute; top: 0; left: 0; }
        }
        @endif
        @media print { .no-print { display: none; } th { print-color-adjust: exact; -webkit-print-color-adjust: exact; } }
    </style>
</head>
<body>
    @include('guidance.partials.assessment-letterhead')
    @if($format === 'html')<nav class="no-print"><button type="button" onclick="window.print()">Print report</button></nav>@endif
    <header class="document-header">
        <p>{{ $office }}</p>
        <h1>{{ $title }}</h1>
    </header>
    <div class="metadata">
        @foreach($metadata as $label => $value)<p><strong>{{ $label }}:</strong> {{ $value }}</p>@endforeach
    </div>
    @if($reportType === 'summary')
        @foreach($reportSections as $sectionTitle => $sectionRows)
            <h2>{{ $sectionTitle }}</h2>
            <table aria-label="{{ $sectionTitle }}">
                <thead><tr><th class="metric-label">Metric</th><th>Value</th></tr></thead>
                <tbody>
                    @foreach($sectionRows as $row)
                        <tr><td>{{ $sectionTitle === 'Overview' ? $row[0] : \Illuminate\Support\Str::after($row[0], ' / ') }}</td><td>{{ $row[1] ?? '-' }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        @endforeach
    @else
        @foreach($rows as $row)
            <h2>Record {{ $loop->iteration }} — {{ $row[0] }}</h2>
            <table aria-label="Record {{ $loop->iteration }}">
                <thead><tr><th class="record-label">Field</th><th>Details</th></tr></thead>
                <tbody>@foreach($columns as $index => $column)<tr><th scope="row">{{ $column }}</th><td>{{ $row[$index] ?? '-' }}</td></tr>@endforeach</tbody>
            </table>
        @endforeach
    @endif
    @include('exports.signatures')
    @if($autoPrint && $format === 'html')<script>window.onload = function () { window.print(); };</script>@endif
</body>
</html>
