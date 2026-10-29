<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 37mm 10mm 24mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 7px; color: #18253b; }
        .official-header { position: fixed; top: -37mm; left: -10mm; right: -10mm; height: 31mm; padding: 3mm 10mm 2mm; background: #fff900; border-top: 1.2mm solid #101010; border-bottom: 1mm solid #d8d8d8; }
        .official-header table { border: 0; }
        .official-header td { padding: 0; border: 0; vertical-align: middle; }
        .seal { width: 24mm; height: 24mm; object-fit: contain; }
        .bagong { width: 27mm; max-height: 21mm; object-fit: contain; }
        .republic { font-family: DejaVu Serif, serif; font-size: 8px; }
        .university-title { width: 76mm; max-height: 10mm; object-fit: contain; }
        .university-fallback { font: bold 18px DejaVu Serif, serif; }
        .campus { margin-top: 1px; font-family: DejaVu Serif, serif; font-size: 7px; }
        .contacts { margin-top: 2px; font-size: 5.5px; }
        .official-footer { position: fixed; right: -10mm; bottom: -24mm; left: -10mm; height: 17mm; padding: 2mm 5mm 1mm; background: #fff900; text-align: center; }
        .official-footer img { width: 100%; height: 8mm; object-fit: contain; }
        .footer-text { margin-top: 1mm; font-size: 5px; }
        .report-heading { text-align: center; border-bottom: 2px solid #183861; padding-bottom: 8px; margin-bottom: 10px; }
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
@php
    $assetData = static function (array $paths): string {
        foreach ($paths as $path) {
            $absolute = public_path($path);
            if (is_file($absolute)) {
                $extension = strtolower(pathinfo($absolute, PATHINFO_EXTENSION));
                $mime = $extension === 'jpg' || $extension === 'jpeg' ? 'jpeg' : 'png';
                return 'data:image/'.$mime.';base64,'.base64_encode(file_get_contents($absolute));
            }
        }
        return '';
    };
    $seal = $assetData(['images/psu-template/image1.png', 'images/psu-logo.png']);
    $titleImage = $assetData(['images/psu-template/psu_title_gothic.png']);
    $bagong = $assetData(['images/psu-template/bagong_pilipinas.png']);
    $footer = $assetData(['images/psu-template/image8.png', 'images/psu-template/image8.jpg']);
@endphp
<header class="official-header">
    <table><tr>
        <td style="width:26mm">@if($seal)<img class="seal" src="{{ $seal }}" alt="Pangasinan State University seal">@endif</td>
        <td style="text-align:center">
            <div class="republic">Republic of the Philippines</div>
            @if($titleImage)<img class="university-title" src="{{ $titleImage }}" alt="Pangasinan State University">@else<div class="university-fallback">Pangasinan State University</div>@endif
            <div class="campus">Lingayen, Pangasinan</div>
            <div class="contacts">www.psu.edu.ph &nbsp; · &nbsp; president@psu.edu.ph &nbsp; · &nbsp; www.facebook.com/PSUroars</div>
        </td>
        <td style="width:29mm;text-align:right">@if($bagong)<img class="bagong" src="{{ $bagong }}" alt="Bagong Pilipinas">@endif</td>
    </tr></table>
</header>
<footer class="official-footer">
    @if($footer)<img src="{{ $footer }}" alt="Pangasinan State University footer">@endif
    <div class="footer-text">Alaminos City · Asingan · Bayambang · Binmaley · Infanta · San Carlos City · Sta. Maria · Urdaneta City &nbsp;|&nbsp; School of Advanced Studies · Open University Systems</div>
</footer>
<section class="report-heading">
    <strong>Guidance &amp; Admission Office</strong>
    <h1>{{ \App\Services\AdmissionReportExportService::TITLES[$type] }}</h1>
    <div class="meta">Admission Cycle: {{ $cycle->displayName }} | Academic Year: {{ $cycle->academic_year }} | Batch: {{ $batch ?: 'All' }} | Records: {{ $roster['count'] }}<br>Generated: {{ now()->format('F j, Y g:i A') }}</div>
</section>
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
