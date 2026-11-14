<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Assessment Answer Sheet</title>
    <style>
        @page { margin: 5mm 6mm; }
        body { margin: 0; font-family: 'DejaVu Sans', sans-serif; font-size: 7px; color: #111; }
        .sheet { width: 100%; margin: 0 auto; }
        .sheet + .sheet { page-break-before: always; }
        h1 { font-size: 9px; margin: 0; text-align: center; }
        h2 { font-size: 8px; margin: 0 0 3px; text-align: center; font-weight: normal; }
        .fields { width: 100%; margin-bottom: 4mm; border-collapse: collapse; table-layout: fixed; }
        .fields td { padding: 0; vertical-align: bottom; line-height: 10px; }
        .value { border-bottom: 0.5px solid #777; }
        .grid { border-collapse: collapse; width: 100%; table-layout: fixed; }
        .grid th, .grid td { border: 0.5px solid #333; text-align: center; padding: 0; height: 4.8mm; font-size: 7px; }
        .grid th { font-weight: normal; height: 3mm; font-size: 6px; }
        .grid .instruction { height: 6mm; }
        .grid .office { background: #aeb8af; }
        .grid .key { background: #fff; }
        .box { display: inline-block; width: 2mm; height: 2mm; border: 0.5px solid #333; line-height: 2mm; font-size: 9px; }
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
            <tr><td style="width: 62%">Name: <span class="value">{{ $fields['Name'] }}</span></td><td>Date: <span class="value">{{ $fields['Date'] }}</span></td></tr>
            <tr><td>Course,yr&amp;Section: <span class="value">{{ $fields['Course, Yr. & Section'] }}</span></td><td>Sex: <span class="value">{{ $fields['Sex'] }}</span></td></tr>
            <tr><td colspan="2">Purpose: <span class="value">{{ $fields['Purpose'] ?: '________________________________________' }}</span></td></tr>
            <tr><td colspan="2">OR NO.: <span class="value">{{ $fields['OR No.'] ?: '________________________________________' }}</span></td></tr>
        </table>
        <table class="grid">
            <thead>
                <tr><th colspan="{{ count($sheet['choices']) + 1 }}" style="width: 51%"></th><th class="instruction" colspan="{{ count($sheet['columns']) }}">DO NOT WRITE IN THIS SECTION</th></tr>
                <tr><th>No.</th>@foreach($sheet['headings'] as $heading)<th>{{ $heading }}</th>@endforeach @foreach($sheet['columns'] as $column)<th>{{ $column }}</th>@endforeach</tr>
            </thead>
            <tbody>
            @foreach($sheet['rows'] as $row)
                <tr>
                    <td>{{ $row['number'] }}</td>
                    @foreach($sheet['choices'] as $value => $label)
                        <td><span class="box">@if($row['selected'] !== null && (string) $row['selected'] === (string) $value)&#10003;@else&nbsp;@endif</span></td>
                    @endforeach
                    @foreach($sheet['columns'] as $column)
                        @php
                            $trait = ['O(10)' => 'Openness', 'C(9)' => 'Conscientiousness', 'E(8)' => 'Extraversion', 'A(9)' => 'Agreeableness', 'N(8)' => 'Neuroticism'][$column] ?? null;
                            $isKey = $sheet['test'] === 'bfpi' && in_array($row['number'], config('guidance.bfpi.subscales.'.$trait, []), true);
                            $isReverse = $isKey && in_array($row['number'], config('guidance.bfpi.reverse_items', []), true);
                        @endphp
                        <td class="office{{ $isKey ? ' key' : '' }}">{{ $isReverse ? '*' : '' }}</td>
                    @endforeach
                </tr>
            @endforeach
            @if($sheet['test'] === 'bfpi')
                @for($line = 0; $line < 2; $line++)
                    <tr><td colspan="6">&nbsp;</td>@foreach($sheet['columns'] as $column)<td>&nbsp;</td>@endforeach</tr>
                @endfor
            @endif
            </tbody>
        </table>
        @if($sheet['test'] !== 'bfpi')
        <div class="legend">
            @foreach($sheet['choices'] as $value => $label)<div>{{ $sheet['headings'][$value] }} = {{ $label }}</div>@endforeach
            @if($sheet['difficulty'] !== null)<div>Difficulty rating: {{ $sheet['difficulty'] }}</div>@endif
        </div>
        @endif
    </section>
@endforeach
</body>
</html>
