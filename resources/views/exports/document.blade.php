<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { size: A4 {{ $orientation }}; margin: {{ count($certificates) ? '9mm 13mm' : '16mm 12mm' }}; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 10px; color: #111; }
        .document-header { text-align: center; border-bottom: 2px solid #17305f; padding-bottom: 10px; }
        h1 { font-size: 16px; color: #17305f; } h2 { font-size: 14px; }
        .metadata { margin: 12px 0; } .metadata p { margin: 3px 0; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #999; padding: 6px; overflow-wrap: anywhere; vertical-align: top; }
        th { background: #e8edf5; } thead { display: table-header-group; }
        tr { page-break-inside: avoid; } .signatures { margin-top: 45px; page-break-inside: avoid; }
        .signatures td { border: 0; width: 50%; text-align: center; }
        .certificate { box-sizing: border-box; width: 100%; min-height: 272mm; padding: 0; font-family: Georgia, 'Times New Roman', serif; font-size: 12px; line-height: 1.65; page-break-inside: avoid; position: relative; }
        .certificate + .certificate { page-break-before: always; }
        .certificate-brand { width: 100%; height: 36mm; background: #f2d83d; border-bottom: 2px solid #c2a900; }
        .certificate-brand table { width: 100%; height: 100%; border-collapse: collapse; table-layout: fixed; }
        .certificate-brand td { border: 0; padding: 2mm; text-align: center; vertical-align: middle; }
        .certificate-brand .seal-cell, .certificate-brand .bagong-cell { width: 18%; }
        .certificate-brand .seal-cell img { width: 25mm; height: auto; max-height: 25mm; }
        .certificate-brand .bagong-cell img { width: 24mm; height: auto; max-height: 24mm; }
        .certificate-brand .university-title { display: block; width: 77mm; height: auto; max-height: 10mm; margin: 1mm auto; }
        .certificate-brand .university-name { color: #17305f; font-size: 19px; margin: 1mm 0; }
        .certificate-brand .republic, .certificate-brand .address, .certificate-brand .contacts { display: block; font-size: 7px; line-height: 1.2; }
        .certificate-brand .campus { display: block; font-size: 9px; font-weight: bold; }
        .certificate-title { text-align: center; letter-spacing: .15em; font-size: 17px; margin: 8mm 0; padding-bottom: 3mm; border-bottom: 1px dashed #777; color: #111; }
        .certificate-body { width: 88%; margin: 0 auto; text-align: justify; }
        .certificate-body p { margin: 0 0 5mm; }
        .certificate-statement { line-height: 1.6; text-indent: 10mm; }
        .certificate-issued { margin-top: 18mm !important; text-indent: 10mm; }
        .certificate-signature { width: 45%; margin: 18mm 0 0 auto; text-align: center; line-height: 1.25; }
        .certificate-signature .name { font-weight: bold; text-decoration: underline; }
        .certificate-bottom { width: 88%; margin: 16mm auto 0; font-size: 10px; line-height: 1.35; }
        .certificate-bottom p { margin: 0 0 2mm; }
        .certificate-footer { width: 100%; margin-top: 5mm; }
        .certificate-footer img { display: block; width: 100%; height: auto; }
        .no-print { margin: 15px 0; }
        @media print {
            .no-print, nav, .sidebar, .app-sidebar, .app-header, header:not(.document-header):not(.certificate-brand) { display: none !important; }
            body { margin: 0; } th { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
            .certificate-brand, .certificate-footer { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
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
            <header class="certificate-brand">
                <table><tr>
                    <td class="seal-cell">@if($certificateBrand['seal'])<img src="{{ $certificateBrand['seal'] }}" alt="PSU seal">@endif</td>
                    <td>
                        <span class="republic">Republic of the Philippines</span>
                        @if($certificateBrand['universityTitle'])<img class="university-title" src="{{ $certificateBrand['universityTitle'] }}" alt="Pangasinan State University">@else<h1 class="university-name">Pangasinan State University</h1>@endif
                        <span class="campus">{{ $certificateBrand['campus'] }}</span>
                        <span class="address">{{ $certificateBrand['address'] }}</span>
                        <span class="contacts">Website: {{ $certificateBrand['website'] }} · Telephone: {{ $certificateBrand['telephone'] }} · Telefax: {{ $certificateBrand['telefax'] }}</span>
                    </td>
                    <td class="bagong-cell">@if($certificateBrand['bagongPilipinas'])<img src="{{ $certificateBrand['bagongPilipinas'] }}" alt="Bagong Pilipinas">@endif</td>
                </tr></table>
            </header>
            <h2 class="certificate-title">{{ $title }}</h2>
            <div class="certificate-body">
                <p class="certificate-statement">This is to certify that <strong>{{ $certificate['name'] }}</strong> {{ $certificate['statement'] }}</p>
                @if($certificate['student_status'] === 'alumni' && $certificate['course'])<p>Records in this office show that the graduate completed the degree program in <strong>{{ $certificate['course'] }}</strong>.</p>@endif
                @if(str_contains(strtolower($title), 'good moral'))<p>Records in this office show that the student has not violated University rules and regulations and is known to possess good moral character.</p>@endif
                <p>This certification is issued upon the request of <strong>{{ $certificate['name'] }}</strong> for <strong>{{ strtoupper($certificate['purpose']) }}</strong> purposes only.</p>
                <p class="certificate-issued">Issued this <u>{{ $certificate['issued_day'] }}</u> day of <u>{{ $certificate['issued_month_year'] }}</u>.</p>
            </div>
            <div class="certificate-signature"><div class="name">{{ $certificateBrand['counselor'] }}</div><div>Guidance Counselor</div></div>
            <div class="certificate-bottom"><p><em>*Not valid without University Seal</em></p><p>O.R. #: <u>{{ $certificate['or_number'] }}</u><br>Date: <u>{{ $certificate['or_date'] }}</u></p><p>Doc. Stamp Tax Paid</p></div>
            @if($certificateBrand['footer'])<div class="certificate-footer"><img src="{{ $certificateBrand['footer'] }}" alt="Pangasinan State University campus information"></div>@endif
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
