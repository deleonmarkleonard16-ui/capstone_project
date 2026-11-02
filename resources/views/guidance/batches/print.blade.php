<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $batch->batch_name }} — Assessment Reports</title>
    <style>
        @include('guidance.partials.psychological-report-css')
        .batch-report + .batch-report { page-break-before: always; break-before: page; }
        .assessment-result-details { page-break-before: always; }
    </style>
</head>
<body>
    @include('guidance.partials.assessment-letterhead')
    @foreach($reports as $report)
        <section class="batch-report">
            @include($report['view'], $report)
        </section>
    @endforeach
</body>
</html>
