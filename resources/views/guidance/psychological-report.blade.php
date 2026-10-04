<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <style>@include('guidance.partials.psychological-report-css')</style>
</head>
<body>
    @if($format === 'html')
        <nav class="no-print" aria-label="Report actions">
            <button type="button" onclick="window.print()">Print report</button>
            @foreach(['pdf', 'docx'] as $exportFormat)
                <a href="{{ route(auth()->user()->role.'.guidance.report.download', ['appointment' => $appointment->getKey(), 'format' => $exportFormat]) }}">Download {{ strtoupper($exportFormat) }}</a>
            @endforeach
        </nav>
    @endif
    <article class="assessment-report">
        <header><h1>OFFICE OF ADMISSION AND GUIDANCE SERVICES</h1><h2>PSYCHOLOGICAL ASSESSMENT</h2></header>
        <table class="profile" aria-label="Student profile">
            @foreach(array_chunk($fields, 2, true) as $pair)
                <tr>@foreach($pair as $label => $value)<td><strong>{{ $label }}:</strong> <span class="field">{{ $value }}</span></td>@endforeach</tr>
            @endforeach
        </table>
        @foreach($matrices as $matrix)
            <section>
                <h3>{{ $matrix['title'] }}</h3>
                <table class="matrix">
                    <thead><tr><th scope="col" class="scale">SCALE</th>@foreach($matrix['columns'] as $column)<th scope="col">{{ $column }}</th>@endforeach</tr></thead>
                    <tbody>
                        @foreach($matrix['rows'] as $row)
                            <tr><th scope="row" class="scale">{{ $row['label'] }}@if($row['description'])<span class="description">({{ $row['description'] }})</span>@endif</th>
                                @foreach($matrix['columns'] as $column)<td class="mark" aria-label="{{ $row['label'].': '.$column.($row['selected'] === $column ? ' selected' : '') }}">{{ $row['selected'] === $column ? '[X]' : '' }}</td>@endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
        @endforeach

        {{-- IV. Remarks --}}
        <section class="remarks">
            <h3>IV. Remarks:</h3>
            @if($remarks)
                <p>{{ $remarks }}</p>
            @else
                <p class="field-blank">____________________________________________________________________________________</p>
                <p class="field-blank">____________________________________________________________________________________</p>
            @endif
        </section>

        {{-- V. Recommendation --}}
        <section class="recommendations">
            <h3>V. Recommendation:</h3>
            @foreach(\App\Services\PsychologicalReportService::RECOMMENDATIONS as $option)
                <p>{{ $recommendation === $option ? '[X]' : '[ ]' }} {{ $option }}</p>
            @endforeach
            @unless($complete)<p class="incomplete">Incomplete assessment: blank cells indicate unavailable results. Counselor review required.</p>@endunless
        </section>

        <p class="note">{{ \App\Services\PsychologicalReportService::NOTE }}</p>
        <table class="signatures"><tr>
            <td>Administered and interpreted by:<div class="signature-line">&nbsp;</div><div class="signature-line">(Position)</div></td>
            <td>Reviewed and certified by:<div class="signature-line">{{ $counselor }}</div><div>Guidance Counselor</div></td>
        </tr></table>
        <footer><strong>*NOT VALID WITHOUT UNIVERSITY SEAL</strong><p>O.R. #: {{ $orNumber }} &nbsp; Date: {{ $orDate }} &nbsp; Doc. Stamp Tax Paid</p></footer>
    </article>
    @if($autoPrint && $format === 'html')<script>window.onload = function() { window.print(); };</script>@endif
</body>
</html>
