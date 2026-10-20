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
        <nav class="no-print" aria-label="Assessment actions">
            <button type="button" onclick="window.print()">Print assessment</button>
            @foreach(['pdf'] as $exportFormat)
                <a href="{{ route(auth()->user()->role.'.guidance.report.download', ['appointment' => $appointment->getKey(), 'format' => $exportFormat]) }}">Download {{ strtoupper($exportFormat) }}</a>
            @endforeach
        </nav>
    @endif
    <article class="assessment-report career-report">
        <header class="letterhead">
            <div class="letterhead-copy">
                <span class="republic">Republic of the Philippines</span>
                @if($brand['universityTitle'])<img class="university-title" src="{{ $brand['universityTitle'] }}" alt="Pangasinan State University">
                @else<h1 class="university-name">Pangasinan State University</h1>@endif
                <strong>{{ $brand['campus'] }}</strong>
                <span>Website: {{ $brand['website'] }} &nbsp; Telephone: {{ $brand['telephone'] }} &nbsp; Telefax: {{ $brand['telefax'] }}</span>
            </div>
            @if($brand['seal'])<img class="university-seal" src="{{ $brand['seal'] }}" alt="Pangasinan State University seal">@endif
        </header>
        <div class="office-heading"><h2>OFFICE OF ADMISSION AND GUIDANCE SERVICES</h2><h3>PERSONALITY ASSESSMENT</h3></div>
        <table class="profile" aria-label="Student profile">
            @foreach(array_chunk($fields, 2, true) as $pair)<tr>@foreach($pair as $label => $value)<td><strong>{{ $label }}:</strong> <span class="field">{{ $value }}</span></td>@endforeach</tr>@endforeach
        </table>
        <section>
            <h3>I. Summary of Personality Profile</h3>
            <table class="matrix career-matrix" aria-label="Big Five personality profile">
                <thead><tr><th class="scale">Big Five Dimension</th><th>Description</th>@foreach([1, 2, 3, 4, 5] as $rating)<th>{{ $rating }}</th>@endforeach<th>Interpretation</th></tr></thead>
                <tbody>@foreach($rows as $row)<tr>
                    <th scope="row" class="scale">{{ strtoupper($row['label']) }}</th><td class="career-description">{{ $row['description'] }}</td>
                    @foreach([1, 2, 3, 4, 5] as $rating)<td class="mark">{{ $row['selected'] === $rating ? '☒' : '□' }}</td>@endforeach
                    <td class="career-interpretation">{{ $row['interpretation'] }}</td>
                </tr>@endforeach</tbody>
            </table>
            <p class="legend"><strong>Legend:</strong> @foreach($legend as $rating => $label){{ $rating }} – {{ $label }}@if(!$loop->last) &nbsp; · &nbsp; @endif @endforeach</p>
        </section>
        <section class="remarks"><h3>II. Remarks:</h3><p>{{ $remarks }}</p>@unless($complete)<p class="incomplete">Some ratings are unavailable; counselor review is required.</p>@endunless</section>
        <p class="note">Should you wish to further understand these results, please consult the Guidance Counselor/Psychologist.</p>
        <table class="signatures"><tr><td>Administered and interpreted by:<div class="signature-line">&nbsp;</div><div class="signature-line">(Position)</div></td><td>Reviewed and certified by:<div class="signature-line">{{ $counselor }}</div><div>Guidance Counselor</div></td></tr></table>
        <footer><strong>*NOT VALID WITHOUT UNIVERSITY SEAL</strong><p>O.R. #: {{ $orNumber }} &nbsp; Date: {{ $orDate }} &nbsp; Doc. Stamp Tax Paid</p><div class="footer-accent">Pangasinan State University · San Carlos Campus</div></footer>
    </article>
</body>
</html>
