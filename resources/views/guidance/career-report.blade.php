<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>CONFIDENTIAL PSYCHOLOGICAL ASSESSMENT REPORT / CAREER TEST EVALUATION</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 20mm;
        }
        body {
            font-family: "DejaVu Sans", Arial, sans-serif;
            color: #111;
            line-height: 1.6;
            margin: 0;
            padding: 20px 30px;
            font-size: 13.5px;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 14px;
            margin-bottom: 22px;
        }
        .header h1 {
            font-size: 16px;
            font-weight: bold;
            margin: 0 0 4px 0;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .header .sub-office {
            font-size: 13px;
            font-weight: 600;
            margin: 0 0 10px 0;
        }
        .header h2 {
            font-size: 14px;
            font-weight: bold;
            text-decoration: underline;
            margin: 6px 0 0 0;
            letter-spacing: 0.02em;
        }
        .info-block {
            margin-bottom: 20px;
            line-height: 1.8;
        }
        .narrative-block {
            margin: 24px 0;
            line-height: 1.85;
            text-align: justify;
        }
        .skills-list {
            margin: 12px 0 16px 20px;
            padding-left: 0;
            list-style-type: decimal;
        }
        .skills-list li {
            margin-bottom: 6px;
        }
        .footer-section {
            margin-top: 36px;
            padding-top: 15px;
            border-top: 1px dashed #999;
        }
        .or-line {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            font-weight: 500;
        }
        .signatory {
            margin-top: 25px;
        }
        .signatory-name {
            font-weight: bold;
            text-decoration: underline;
            margin-top: 5px;
        }
        .actions {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #ccc;
            display: flex;
            gap: 12px;
        }
        .btn {
            display: inline-block;
            padding: 8px 16px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            border-radius: 6px;
            cursor: pointer;
            border: 1px solid #0f3f97;
            background: #0f3f97;
            color: #fff;
        }
        .btn-outline {
            background: #fff;
            color: #0f3f97;
        }
        @media print {
            body { padding: 0; }
            .actions { display: none !important; }
        }
    </style>
</head>
<body>

<div class="header">
    <h1>PANGASINAN STATE UNIVERSITY – SAN CARLOS CAMPUS</h1>
    <div class="sub-office">Guidance and Counseling Services Office</div>
    <h2>CONFIDENTIAL PSYCHOLOGICAL ASSESSMENT REPORT / CAREER TEST EVALUATION</h2>
</div>

<div class="info-block">
    <div><strong>Name:</strong> {{ $appointment->applicant->full_name }}</div>
    <div><strong>ID No.:</strong> [{{ $appointment->serviceRequest?->student_number ?? $appointment->applicant->student_id ?? '23-SC-####' }}]</div>
    <div><strong>Purpose:</strong> {{ $appointment->serviceRequest?->purpose ?? 'For Counseling Reference' }}</div>
    <div><strong>Others:</strong> ____________________</div>
</div>

<div class="narrative-block">
    <p style="font-size: 14px; line-height: 1.8;">
        "Based on your RIASEC, your interest code is <strong>{{ implode(', ', $traits) }}</strong>.<br>
        This implies that you are <strong>{{ $interestLevel }}</strong> in the following skills:<br>
        <strong>Skills:</strong> These are the skills you can explore: 
        1. <u>{{ $skills[$traits[0] ?? ''] ?? 'Hands-on problem solving & field execution' }}</u> &nbsp; 
        2. <u>{{ $skills[$traits[1] ?? ''] ?? 'Analytical research & empirical investigation' }}</u> &nbsp; 
        3. <u>{{ $skills[$traits[2] ?? ''] ?? 'Creative design & innovative expression' }}</u>"
    </p>

    <ol class="skills-list" style="margin-top: 15px;">
        @foreach($traits as $trait)
            <li>
                <strong>{{ $trait }}:</strong> {{ $skills[$trait] ?? $trait }}
            </li>
        @endforeach
    </ol>
</div>

<div class="footer-section">
    <div class="or-line">
        <span><strong>O.R. Date:</strong> {{ $appointment->or_date ? $appointment->or_date->format('F d, Y') : ($appointment->serviceRequest?->or_date ? \Illuminate\Support\Carbon::parse($appointment->serviceRequest->or_date)->format('F d, Y') : '___________________') }}</span>
        <span><strong>O.R. Number:</strong> {{ $appointment->or_number ?: ($appointment->serviceRequest?->or_number ?: '___________________') }}</span>
    </div>

    <div class="signatory">
        <div>Administered &amp; Interpreted By:</div>
        <div class="signatory-name">{{ $counselorName ?? 'Ms. Noemi C. Carlos' }}</div>
        <div style="font-size: 12px; color: #444;">Guidance Counselor / Psychometrician</div>
    </div>
</div>

<div class="actions">
    <button class="btn" onclick="window.print()">Print Report</button>
    <a class="btn btn-outline" href="{{ request()->fullUrlWithQuery(['format' => 'pdf']) }}">Download Official PDF</a>
</div>

</body>
</html>
