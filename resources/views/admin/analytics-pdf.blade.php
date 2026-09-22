<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>PSU San Carlos - Institutional Analytics Executive Summary</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 15mm 15mm 18mm 15mm;
        }
        body {
            font-family: "DejaVu Sans", Arial, sans-serif;
            color: #111;
            font-size: 11px;
            line-height: 1.4;
            margin: 0;
            padding: 0;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #0f3f97;
            padding-bottom: 10px;
            margin-bottom: 14px;
        }
        .header .university {
            font-size: 14px;
            font-weight: bold;
            color: #0f3f97;
            text-transform: uppercase;
            margin: 0 0 2px 0;
        }
        .header .campus {
            font-size: 11px;
            font-weight: 600;
            color: #333;
            margin: 0 0 2px 0;
        }
        .header .office {
            font-size: 10px;
            color: #555;
            margin: 0 0 6px 0;
        }
        .header .report-title {
            font-size: 13px;
            font-weight: bold;
            color: #000;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin: 6px 0 0 0;
        }
        .meta-table {
            width: 100%;
            margin-bottom: 12px;
            border-collapse: collapse;
        }
        .meta-table td {
            font-size: 10px;
            padding: 2px 4px;
        }
        .section-title {
            font-size: 11px;
            font-weight: bold;
            color: #0f3f97;
            border-bottom: 1.5px solid #0f3f97;
            padding-bottom: 3px;
            margin-top: 14px;
            margin-bottom: 8px;
            text-transform: uppercase;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        table.data-table th, table.data-table td {
            border: 1px solid #ccc;
            padding: 5px 6px;
            font-size: 10px;
            text-align: left;
        }
        table.data-table th {
            background-color: #f2f5fa;
            font-weight: bold;
            color: #0f3f97;
        }
        .text-center { text-align: center !important; }
        .text-right  { text-align: right !important; }
        .badge-danger {
            background-color: #dc3545;
            color: #fff;
            padding: 2px 5px;
            border-radius: 3px;
            font-weight: bold;
            font-size: 9px;
        }
        .badge-warning {
            background-color: #ffc107;
            color: #000;
            padding: 2px 5px;
            border-radius: 3px;
            font-weight: bold;
            font-size: 9px;
        }
        .badge-success {
            background-color: #198754;
            color: #fff;
            padding: 2px 5px;
            border-radius: 3px;
            font-size: 9px;
        }
        .summary-box {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-left: 4px solid #0f3f97;
            padding: 8px 10px;
            margin-bottom: 10px;
        }
        .signoff {
            margin-top: 25px;
            width: 100%;
            border-collapse: collapse;
        }
        .signoff td {
            width: 50%;
            vertical-align: top;
            font-size: 10px;
        }
        .sign-line {
            display: inline-block;
            width: 200px;
            border-bottom: 1px solid #000;
            margin-top: 35px;
            margin-bottom: 4px;
        }
    </style>
</head>
<body>

    <div class="header">
        <div class="university">Pangasinan State University</div>
        <div class="campus">San Carlos City Campus &bull; Roxas Boulevard, San Carlos City, Pangasinan</div>
        <div class="office">Guidance, Counseling, Testing and Admission Center</div>
        <div class="report-title">Executive Institutional Analytics Summary</div>
    </div>

    <table class="meta-table">
        <tr>
            <td style="width: 50%;"><strong>Academic Year:</strong> {{ $academicYear }}</td>
            <td style="width: 50%; text-align: right;"><strong>Date Generated:</strong> {{ $generatedAt }}</td>
        </tr>
        <tr>
            <td><strong>Campus:</strong> San Carlos Campus</td>
            <td style="text-align: right;"><strong>Classification:</strong> Official Institutional Summary</td>
        </tr>
    </table>

    {{-- Executive Metrics --}}
    <div class="section-title">1. Executive Overview & Request Volumes</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Metric Category</th>
                <th class="text-center">Count</th>
                <th>Institutional Notes / Context</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>Total Requests & Applications</strong></td>
                <td class="text-center"><strong>{{ number_format($totalRequests) }}</strong></td>
                <td>{{ $guidanceOnly ? 'Aggregated across Guidance Testing, Good Moral, and Exit Forms' : '{{ $guidanceOnly ? 'Aggregated across Guidance Testing, Good Moral, and Exit Forms' : 'Aggregated across Testing, Good Moral, Exit Forms, and Admission applicants' }}' }}</td>
            </tr>
            <tr>
                <td><strong>Completed Assessments</strong></td>
                <td class="text-center"><strong>{{ number_format($completedTests) }}</strong></td>
                <td>Examinees with fully scored psychometric, personality, or career instruments</td>
            </tr>
            <tr>
                <td><strong>In-Queue / Active Requests</strong></td>
                <td class="text-center"><strong>{{ number_format($activeRequests) }}</strong></td>
                <td>Submissions currently pending payment verification, scheduling, or evaluation</td>
            </tr>
            <tr>
                <td><strong>Good Moral Certificate Requests</strong></td>
                <td class="text-center">{{ number_format($goodMoralCount) }}</td>
                <td>Official certificate applications processed through the student tracking portal</td>
            </tr>
            <tr>
                <td><strong>Exit Form Submissions</strong></td>
                <td class="text-center">{{ number_format($exitFormCount) }}</td>
                <td>Graduating and transferring student clearance interviews recorded</td>
            </tr>
            <tr>
                <td><strong>Counselor Red-Flag Cases</strong></td>
                <td class="text-center"><span class="badge-danger">{{ number_format($redFlagsCount) }}</span></td>
                <td>Examinees scoring in Severe or Extremely Severe mental health screening ranges</td>
            </tr>
        </tbody>
    </table>

    {{-- Psychometric Severity Distributions --}}
    <div class="section-title">2. Global Psychometric Severity Distribution</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Severity Level</th>
                <th class="text-center">Total Examinees</th>
                <th class="text-center">Percentage</th>
                <th>Standard Clinical Guideline</th>
            </tr>
        </thead>
        <tbody>
            @php $sevTotal = array_sum($severityDistribution) ?: 1; @endphp
            @foreach($severityDistribution as $level => $cnt)
            <tr>
                <td><strong>{{ $level }}</strong></td>
                <td class="text-center">{{ number_format($cnt) }}</td>
                <td class="text-center">{{ round(($cnt / $sevTotal) * 100, 1) }}%</td>
                <td>
                    @if(in_array($level, ['Severe', 'Extremely Severe']))
                        <span class="badge-danger">Priority Counselor Intervention Required</span>
                    @elseif($level === 'Moderate')
                        <span class="badge-warning">Monitor / Supportive Guidance Recommended</span>
                    @else
                        <span class="badge-success">Within Typical Screening Threshold</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Program Testing Volume --}}
    <div class="section-title">3. Testing & Service Volume Across 10 Campus Programs</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Program Code</th>
                <th>Official Degree Program Title</th>
                <th class="text-center">Volume</th>
            </tr>
        </thead>
        <tbody>
            @foreach($programTestingVolume as $code => $count)
            <tr>
                <td><strong>{{ $code }}</strong></td>
                <td>{{ $officialPrograms[$code] ?? $code }}</td>
                <td class="text-center"><strong>{{ number_format($count) }}</strong></td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Socio-Demographics --}}
    <div class="section-title">4. Socio-Demographic Profile</div>
    <table class="data-table">
        <thead>
            <tr>
                <th colspan="2">Gender Distribution</th>
                <th colspan="2">Special Priority Groups</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>Male:</strong> {{ number_format($maleCount) }}</td>
                <td><strong>Female:</strong> {{ number_format($femaleCount) }}</td>
                <td colspan="2">
                    @foreach($specialCategories as $grp => $cnt)
                        <strong>{{ $grp }}:</strong> {{ number_format($cnt) }}@if(!$loop->last) &bull; @endif
                    @endforeach
                </td>
            </tr>
        </tbody>
    </table>

    {{-- Counselor Priority List --}}
    @if(count($redFlags) > 0)
    <div class="section-title" style="color: #dc3545; border-bottom-color: #dc3545;">5. Counselor Red-Flag Priority Alerts (Recent Examinees)</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Examinee Name</th>
                <th>Student ID</th>
                <th>Program</th>
                <th>Flagged Scale</th>
                <th>Severity</th>
                <th>Assessment Date</th>
            </tr>
        </thead>
        <tbody>
            @foreach(array_slice($redFlags, 0, 15) as $flag)
            <tr>
                <td><strong>{{ $flag['name'] }}</strong></td>
                <td>{{ $flag['student_id'] }}</td>
                <td>{{ $flag['course'] }}</td>
                <td>{{ $flag['scale'] }}</td>
                <td><span class="badge-danger">{{ $flag['severity'] }}</span></td>
                <td>{{ $flag['date'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    {{-- Sign-off Section --}}
    <table class="signoff">
        <tr>
            <td>
                Prepared by:<br>
                <span class="sign-line"></span><br>
                <strong>DMSGTA Automated Analytics Engine</strong><br>
                Guidance & Testing Office
            </td>
            <td>
                Certified and Noted by:<br>
                <span class="sign-line"></span><br>
                <strong>{{ $counselorName }}</strong><br>
                Registered Guidance Counselor (RGC)
            </td>
        </tr>
    </table>

</body>
</html>
