<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>PSU-CAT Certificate of Admission Test Result - {{ $applicant->application_number }}</title>
    <style>
        @page { size: letter portrait; margin: 12mm 15mm 12mm; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            color: #1a2538;
            background: #ffffff;
            font-family: DejaVu Sans, "Times New Roman", Georgia, serif;
            font-size: 11.5px;
            line-height: 1.5;
        }
        .cert-outer-border {
            border: 3px solid #183861;
            padding: 4px;
            min-height: 940px;
        }
        .cert-inner-border {
            border: 1px solid #183861;
            padding: 24px 30px 20px;
            min-height: 928px;
            position: relative;
        }
        .cert-header {
            text-align: center;
            border-bottom: 2px solid #183861;
            padding-bottom: 12px;
            margin-bottom: 18px;
        }
        .cert-header .republic {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #475569;
        }
        .cert-header .university {
            font-size: 17px;
            font-weight: bold;
            color: #183861;
            margin: 2px 0;
            letter-spacing: 0.5px;
        }
        .cert-header .campus {
            font-size: 11px;
            font-weight: 600;
            color: #334155;
        }
        .cert-header .office {
            font-size: 10px;
            font-style: italic;
            color: #64748b;
            margin-top: 2px;
        }
        .cert-title-container {
            text-align: center;
            margin: 16px 0 20px;
        }
        .cert-title {
            font-size: 17px;
            font-weight: bold;
            letter-spacing: 1.5px;
            color: #183861;
            text-transform: uppercase;
            display: inline-block;
            border-bottom: 2px solid #d97706;
            padding-bottom: 4px;
        }
        .cert-statement {
            font-size: 12px;
            text-align: justify;
            line-height: 1.65;
            margin-bottom: 16px;
        }
        .cert-statement strong {
            color: #0f172a;
        }
        .results-card {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            padding: 12px 16px;
            margin-bottom: 16px;
        }
        .results-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }
        .results-table th, .results-table td {
            padding: 5px 8px;
            border-bottom: 1px solid #e2e8f0;
        }
        .results-table th {
            text-align: left;
            color: #475569;
            font-weight: 600;
            width: 48%;
        }
        .results-table td {
            color: #0f172a;
            font-weight: bold;
        }
        .remarks-box {
            border: 2px dashed #183861;
            background: #eff6ff;
            padding: 12px 16px;
            text-align: center;
            margin: 16px 0;
            border-radius: 4px;
        }
        .remarks-label {
            font-size: 10px;
            font-weight: bold;
            letter-spacing: 1px;
            color: #1e40af;
            text-transform: uppercase;
            margin-bottom: 4px;
        }
        .remarks-value {
            font-size: 14px;
            font-weight: bold;
            color: #183861;
            letter-spacing: 0.5px;
        }
        .cert-issuance {
            font-size: 11.5px;
            line-height: 1.6;
            text-align: justify;
            margin-top: 14px;
        }
        .signatures-area {
            margin-top: 50px;
            width: 100%;
        }
        .signature-col {
            display: inline-block;
            width: 45%;
            text-align: center;
            vertical-align: top;
        }
        .signature-col.right {
            float: right;
        }
        .signature-line {
            width: 80%;
            border-top: 1px solid #0f172a;
            margin: 40px auto 4px;
        }
        .signatory-name {
            font-weight: bold;
            font-size: 11px;
            text-transform: uppercase;
            color: #0f172a;
        }
        .signatory-title {
            font-size: 10px;
            color: #64748b;
        }
        .cert-footer-meta {
            position: absolute;
            bottom: 12px;
            left: 30px;
            right: 30px;
            font-size: 8px;
            color: #94a3b8;
            text-align: center;
            border-top: 1px solid #e2e8f0;
            padding-top: 6px;
        }
        @media print {
            body { background: #fff; }
            .cert-outer-border { border: 3px solid #183861; min-height: 980px; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
    @if(request('format') === 'print')
        <div class="no-print" style="background:#1e293b; color:#fff; padding:10px; text-align:center; font-family:sans-serif; font-size:13px;">
            <span>Preview Mode. Click below to print or save as PDF.</span>
            <button onclick="window.print()" style="margin-left:15px; padding:4px 14px; background:#3b82f6; color:#fff; border:none; border-radius:3px; cursor:pointer; font-weight:bold;">
                Print Certificate
            </button>
        </div>
    @endif

    <div class="cert-outer-border">
        <div class="cert-inner-border">
            <div class="cert-header">
                <div class="republic">Republic of the Philippines</div>
                <div class="university">PANGASINAN STATE UNIVERSITY</div>
                <div class="campus">San Carlos Campus · San Carlos City, Pangasinan</div>
                <div class="office">Guidance and Testing Center / Campus Admission Office</div>
            </div>

            <div class="cert-title-container">
                <div class="cert-title">Certificate of Admission Test Result</div>
            </div>

            <div class="cert-statement">
                THIS IS TO CERTIFY that <strong>{{ mb_strtoupper($applicant->full_name) }}</strong>,
                with Application Number <strong>{{ $applicant->application_number }}</strong>,
                has taken the <strong>Pangasinan State University College Admission Test (PSU-CAT)</strong>
                for <strong>Academic Year {{ $cycle->academic_year ?? '2026-2027' }}</strong>
                ({{ $cycle->displayName }}) with the following official evaluation results:
            </div>

            <div class="results-card">
                <table class="results-table">
                    <tbody>
                        <tr>
                            <th>1st Course Choice:</th>
                            <td>
                                {{ $applicant->course_choice }} — {{ \App\Support\CourseCatalog::OPTIONS[$applicant->course_choice] ?? $applicant->course_choice }}
                            </td>
                        </tr>
                        <tr>
                            <th>2nd Course Choice:</th>
                            <td>
                                @if($applicant->second_course_choice)
                                    {{ $applicant->second_course_choice }} — {{ \App\Support\CourseCatalog::OPTIONS[$applicant->second_course_choice] ?? $applicant->second_course_choice }}
                                @else
                                    <span style="color:#64748b; font-weight:normal;">None Specified</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>CAT Raw Score / Items:</th>
                            <td>
                                {{ $applicant->exam_score !== null ? number_format($applicant->exam_score, 0) : '–' }} / {{ $cycle->total_items ?: 80 }}
                                @if($applicant->cat_score_percentage !== null)
                                    ({{ number_format($applicant->cat_score_percentage, 2) }}%)
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>Stanine Rating:</th>
                            <td>
                                @if($applicant->stanine_score)
                                    <span style="font-size:13px; color:#183861;">Stanine {{ $applicant->stanine_score }}</span>
                                @else
                                    –
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>High School General Weighted Average (GWA):</th>
                            <td>{{ $applicant->gwa !== null ? number_format($applicant->gwa, 2) . '%' : '–' }}</td>
                        </tr>
                        <tr>
                            <th>Interview Score:</th>
                            <td>{{ $applicant->interview_score !== null ? number_format($applicant->interview_score, 2) . '%' : '–' }}</td>
                        </tr>
                        <tr>
                            <th>Overall Composite Total Score:</th>
                            <td>
                                @php
                                    $computedTotal = $applicant->calculated_total;
                                @endphp
                                @if($computedTotal !== null)
                                    <span style="font-size:13px; color:#0f766e;">{{ number_format($computedTotal, 2) }}%</span>
                                @else
                                    –
                                @endif
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="remarks-box">
                <div class="remarks-label">Official Admission Eligibility Status</div>
                <div class="remarks-value">{{ $remarks }}</div>
            </div>

            <div class="cert-issuance">
                This certificate is issued upon the request of the above-named examinee for
                <strong>{{ $purpose }}</strong>.
                <br><br>
                Issued this <strong>{{ $dateIssued }}</strong> at Pangasinan State University, San Carlos Campus, San Carlos City, Pangasinan, Philippines.
            </div>

            <div class="signatures-area">
                <div class="signature-col">
                    <div class="signature-line"></div>
                    <div class="signatory-name">Guidance Counselor / Psychometrician</div>
                    <div class="signatory-title">Campus Testing Coordinator</div>
                </div>

                <div class="signature-col right">
                    <div class="signature-line"></div>
                    <div class="signatory-name">Campus Executive Director</div>
                    <div class="signatory-title">Pangasinan State University</div>
                </div>
            </div>

            <div class="cert-footer-meta">
                Document Code: PSU-CAT-CTR-{{ $applicant->id }}-{{ date('Ymd') }} &nbsp;·&nbsp;
                This is an official computer-generated document issued by the PSU Guidance &amp; Admission Office. Valid with university dry seal.
            </div>
        </div>
    </div>

    @if(request('format') === 'print')
        <script>
            window.onload = function() {
                // Auto trigger print dialog if requested
                setTimeout(function() { window.print(); }, 500);
            };
        </script>
    @endif
</body>
</html>
