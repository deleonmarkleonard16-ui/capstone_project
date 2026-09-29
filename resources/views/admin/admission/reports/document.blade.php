<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 18mm 10mm 16mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 7px; color: #18253b; }
        header { text-align: center; border-bottom: 2px solid #183861; padding-bottom: 8px; margin-bottom: 10px; }
        h1 { font-size: 15px; margin: 6px 0 3px; }
        h2 { font-size: 11px; margin: 16px 0 5px; background: #e8edf5; padding: 6px; }
        .meta { font-size: 8px; line-height: 1.6; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #b7c2d4; padding: 4px 3px; overflow-wrap: break-word; }
        th { background: #183861; color: white; font-size: 6.5px; }
        tr { page-break-inside: avoid; }
    </style>
</head>
<body>
<header>
    <div>Republic of the Philippines</div>
    <strong>Pangasinan State University - San Carlos Campus</strong><br>
    Guidance &amp; Admission Office
    <h1>{{ \App\Services\AdmissionReportExportService::TITLES[$type] }}</h1>
    <div class="meta">Admission Cycle: {{ $cycle->displayName }} | Academic Year: {{ $cycle->academic_year }} | Batch: {{ $batch ?: 'All' }} | Records: {{ $roster['count'] }}<br>Generated: {{ now()->format('F j, Y g:i A') }}</div>
</header>
@forelse($roster['groups'] as $course => $items)
    <h2>{{ $course }} - {{ count($items) }} applicant(s)
        @if(str_starts_with($type, 'interview-')) | Top limit: {{ $roster['cutoffs'][$course] ?? 'All' }}
        @elseif(str_starts_with($type, 'final-')) | Seats: {{ $roster['quotas'][$course] ?? 0 }} @endif
    </h2>
    <table>
        <thead><tr>@foreach(app(\App\Services\AdmissionReportExportService::class)->columns($type) as $column)<th>{{ $column }}</th>@endforeach</tr></thead>
        <tbody>
        @foreach($items as $item)
            <tr>@foreach(app(\App\Services\AdmissionReportExportService::class)->values($item, $type) as $value)<td>{{ $value }}</td>@endforeach</tr>
        @endforeach
        </tbody>
    </table>
@empty
    <p>No applicants matched this report.</p>
@endforelse
</body>
</html>
