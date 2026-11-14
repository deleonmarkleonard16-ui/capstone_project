<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Assessment Answer Sheet</title>
    <style>
        @page { margin: 24px 40px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #111; }
        .sheet { width: 440px; margin: 0 auto; }
        .sheet + .sheet { page-break-before: always; }
        h1 { font-size: 13px; margin: 0; text-align: center; }
        h2 { font-size: 11px; margin: 0 0 10px; text-align: center; font-weight: normal; }
        .fields { width: 100%; margin-bottom: 8px; border-collapse: collapse; }
        .fields td { padding: 2px 0; vertical-align: bottom; }
        .value { border-bottom: 1px solid #777; }
        .grid { border-collapse: collapse; width: 100%; table-layout: fixed; }
        .grid th, .grid td { border: 1px solid #333; text-align: center; padding: 0; height: 16px; font-size: 9px; }
        .grid th { font-weight: normal; }
        .grid .office { background: #e4e7e4; }
        .box { display: inline-block; width: 10px; height: 11px; border: 1px solid #555; line-height: 11px; font-size: 12px; }
        .legend { margin-top: 8px; font-size: 8px; }
        .legend div { margin: 1px 0; }
    </style>
</head>
<body>
@foreach($sheets as $sheet)
    <section class="sheet">
        <h1>{{ $sheet['title'] }}</h1>
        <h2>ANSWER SHEET</h2>
        <table class="fields">
            @foreach($fields as $label => $value)
                <tr><td style="width: 125px">{{ $label }}:</td><td class="value">{{ $value ?: ' ' }}</td></tr>
            @endforeach
        </table>
        <table class="grid">
            <thead>
                <tr><th colspan="{{ count($sheet['choices']) + 1 }}">ANSWERS</th><th colspan="{{ count($sheet['columns']) }}">DO NOT WRITE IN THIS SECTION</th></tr>
                <tr><th>No.</th>@foreach($sheet['headings'] as $heading)<th>{{ $heading }}</th>@endforeach @foreach($sheet['columns'] as $column)<th>{{ $column }}</th>@endforeach</tr>
            </thead>
            <tbody>
            @foreach($sheet['rows'] as $row)
                <tr>
                    <td>{{ $row['number'] }}</td>
                    @foreach($sheet['choices'] as $value => $label)
                        <td><span class="box">@if($row['selected'] !== null && (string) $row['selected'] === (string) $value)&#10003;@else&nbsp;@endif</span></td>
                    @endforeach
                    @foreach($sheet['columns'] as $column)<td class="office">&nbsp;</td>@endforeach
                </tr>
            @endforeach
            </tbody>
        </table>
        <div class="legend">
            @foreach($sheet['choices'] as $value => $label)<div>{{ $sheet['headings'][$value] }} = {{ $label }}</div>@endforeach
            @if($sheet['difficulty'] !== null)<div>Difficulty rating: {{ $sheet['difficulty'] }}</div>@endif
            <div>Checks show recorded answers. Unanswered items are left blank.</div>
        </div>
    </section>
@endforeach
</body>
</html>
