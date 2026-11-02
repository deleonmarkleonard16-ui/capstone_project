<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $batch->batch_name }} — Assessment Reports</title>
    <style>
        @include('guidance.partials.psychological-report-css')
        .batch-report + .batch-report { page-break-before: always; break-before: page; }
        @media screen {
            body { padding: 20px; background: #eee; }
            .batch-report { background: white; max-width: 184mm; padding: 15mm; margin: 20px auto; }
            .assessment-letterhead { display: none; }
        }
    </style>
</head>
<body>
    @include('guidance.partials.assessment-letterhead')
    <nav class="no-print" aria-label="Batch print actions">
        <h1>{{ $batch->batch_name }} — {{ $batch->test_type }}</h1>
        <p>{{ count($reports) }} assessment report(s). {{ $skipped }} roster record(s) without printable scored results.</p>
        <button type="button" onclick="window.print()">Print this batch</button>
    </nav>
    @foreach($reports as $report)
        <section class="batch-report">
            @include($report['view'], $report)
        </section>
    @endforeach
    <script>window.addEventListener('load', function () { window.print(); });</script>
</body>
</html>
