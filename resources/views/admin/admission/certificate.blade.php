<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>PSU-CAT Certificate of Admission Test Result - {{ $applicant->application_number }}</title>
    <style>
        @page {
            size: letter portrait;
            margin: 15mm 20mm 15mm;
        }
        * {
            box-sizing: border-box;
        }
        body {
            margin: 0;
            color: #111827;
            background: #ffffff;
            font-family: DejaVu Sans, "Times New Roman", Georgia, serif;
            font-size: 12px;
            line-height: 1.6;
        }
        .cert-container {
            width: 100%;
            min-height: 900px;
            padding: 10px;
            position: relative;
        }
        /* Top Institutional Header */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
        }
        .header-logo-cell {
            width: 90px;
            vertical-align: middle;
            text-align: center;
        }
        .header-logo {
            width: 80px;
            height: auto;
        }
        .header-text-cell {
            vertical-align: middle;
            text-align: center;
        }
        .republic {
            font-size: 11px;
            color: #374151;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }
        .university {
            font-size: 17px;
            font-weight: bold;
            color: #183861;
            letter-spacing: 0.5px;
            margin: 2px 0;
        }
        .campus {
            font-size: 11.5px;
            font-weight: bold;
            color: #1f2937;
            margin-bottom: 4px;
        }
        .office {
            font-size: 13px;
            font-weight: bold;
            color: #183861;
            text-transform: uppercase;
            letter-spacing: 0.75px;
            margin-top: 4px;
            border-top: 1px solid #d1d5db;
            padding-top: 4px;
            display: inline-block;
        }
        /* Certificate Title */
        .title-container {
            text-align: center;
            margin: 30px 0 28px;
        }
        .cert-title {
            font-size: 17px;
            font-weight: bold;
            letter-spacing: 1.5px;
            color: #183861;
            text-transform: uppercase;
            display: inline-block;
            border-bottom: 2px solid #183861;
            padding-bottom: 4px;
        }
        /* Body prose */
        .cert-body-p {
            font-size: 13px;
            text-align: justify;
            line-height: 1.8;
            margin-bottom: 24px;
            text-indent: 36px;
        }
        .cert-body-p strong {
            color: #000000;
        }
        /* Official 2-Column Table */
        .table-wrap {
            margin: 25px 0 30px;
            width: 100%;
        }
        .results-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            border: 2px solid #183861;
        }
        .results-table th {
            background-color: #f1f5f9;
            color: #183861;
            font-weight: bold;
            text-transform: uppercase;
            padding: 10px 14px;
            border: 1px solid #183861;
            letter-spacing: 0.75px;
        }
        .results-table td {
            padding: 14px 16px;
            border: 1px solid #183861;
            vertical-align: middle;
        }
        .results-table .stanine-cell {
            width: 32%;
            text-align: center;
            font-size: 16px;
            font-weight: bold;
            color: #183861;
        }
        .results-table .remarks-cell {
            width: 68%;
            text-align: center;
            font-size: 13.5px;
            font-weight: bold;
            color: #0f172a;
            letter-spacing: 0.5px;
        }
        /* Issued Date and Purpose */
        .cert-purpose-p {
            font-size: 13px;
            text-align: justify;
            line-height: 1.8;
            margin-bottom: 26px;
            text-indent: 36px;
        }
        .cert-date-p {
            font-size: 13px;
            text-align: justify;
            line-height: 1.8;
            margin-bottom: 40px;
            text-indent: 36px;
        }
        /* Signatory Area */
        .signatory-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 40px;
            margin-bottom: 30px;
        }
        .signatory-space {
            width: 48%;
        }
        .signatory-cell {
            width: 52%;
            text-align: center;
            vertical-align: top;
        }
        .signatory-line {
            height: 40px;
        }
        .signatory-name {
            font-size: 13.5px;
            font-weight: bold;
            text-transform: uppercase;
            color: #000000;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #000000;
            display: inline-block;
            padding-bottom: 2px;
            min-width: 250px;
        }
        .signatory-title {
            font-size: 11.5px;
            color: #374151;
            margin-top: 4px;
        }
        /* Footer / OR Section */
        .footer-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 25px;
            font-size: 11px;
            color: #374151;
        }
        .footer-table td {
            vertical-align: top;
        }
        .seal-note {
            font-style: italic;
            font-weight: bold;
            color: #4b5563;
            margin-bottom: 8px;
        }
        .or-item {
            margin-bottom: 3px;
        }
        .stamp-note {
            margin-top: 6px;
            font-weight: 600;
        }
        /* Preview bar (screen only) */
        .no-print {
            background: #1e293b;
            color: #fff;
            padding: 10px 16px;
            text-align: center;
            font-family: sans-serif;
            font-size: 13px;
        }
        .no-print button {
            margin-left: 14px;
            padding: 6px 16px;
            background: #2563eb;
            color: #fff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                background: #fff;
            }
            .cert-container {
                padding: 0;
            }
        }
    </style>
</head>
<body>
    @if(request('format') === 'print')
        <div class="no-print">
            <span>Official Certificate Preview Mode. Click below to print or save via browser:</span>
            <button onclick="window.print()">
                Print Certificate
            </button>
        </div>
    @endif

    <div class="cert-container">
        {{-- Institutional Header --}}
        <table class="header-table">
            <tr>
                <td class="header-logo-cell">
                    @php
                        $logoPath = public_path('images/psu-logo.jpg');
                        if (!is_file($logoPath)) {
                            $logoPath = public_path('images/psu-logo.png');
                        }
                        $logoSrc = '';
                        if (is_file($logoPath)) {
                            $mime = str_ends_with($logoPath, '.png') ? 'image/png' : 'image/jpeg';
                            $logoSrc = 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($logoPath));
                        } else {
                            $logoSrc = asset('images/psu-logo.png');
                        }
                    @endphp
                    @if($logoSrc)
                        <img src="{{ $logoSrc }}" alt="PSU Logo" class="header-logo">
                    @endif
                </td>
                <td class="header-text-cell">
                    <div class="republic">Republic of the Philippines</div>
                    <div class="university">PANGASINAN STATE UNIVERSITY</div>
                    <div class="campus">San Carlos Campus · San Carlos City, Pangasinan</div>
                    <div class="office">OFFICE OF ADMISSION AND GUIDANCE SERVICES</div>
                </td>
                <td style="width: 90px;"></td>{{-- Spacer to ensure center alignment --}}
            </tr>
        </table>

        {{-- Document Title --}}
        <div class="title-container">
            <div class="cert-title">CERTIFICATE OF ADMISSION TEST RESULT</div>
        </div>

        {{-- Certification Prose --}}
        <div class="cert-body-p">
            This is to certify that <strong>{{ ($salutation ? $salutation . ' ' : '') . mb_strtoupper($applicant->full_name) }}</strong> has taken the
            Pangasinan State University - College Admission Test in this campus for the S.Y.
            <strong>{{ $cycle->academic_year ?? '2026-2027' }}</strong>, with the following remarks:
        </div>

        {{-- Official Stanine & Remarks Table --}}
        <div class="table-wrap">
            <table class="results-table">
                <thead>
                    <tr>
                        <th style="width: 32%;">STANINE</th>
                        <th style="width: 68%;">REMARKS</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="stanine-cell">
                            {{ $applicant->stanine_score ?? '–' }}
                        </td>
                        <td class="remarks-cell">
                            {{ $remarks }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- Purpose --}}
        <div class="cert-purpose-p">
            This certification is issued upon the request of <strong>{{ mb_strtoupper($requestorName) }}</strong> for
            <strong>{{ mb_strtoupper($purpose) }}</strong> purposes only.
        </div>

        {{-- Issued Date --}}
        <div class="cert-date-p">
            Issued this <strong>{{ $issuedDay }}</strong> day of <strong>{{ $issuedMonth }}</strong>, <strong>{{ $issuedYear }}</strong>.
        </div>

        {{-- Signatory --}}
        <table class="signatory-table">
            <tr>
                <td class="signatory-space"></td>
                <td class="signatory-cell">
                    <div class="signatory-line"></div>
                    <div class="signatory-name">NOEMI C. CARLOS, RGC</div>
                    <div class="signatory-title">Coordinator, Admission and Guidance Services</div>
                </td>
            </tr>
        </table>

        {{-- Footer / Official Receipt Information --}}
        <table class="footer-table">
            <tr>
                <td>
                    <div class="seal-note">*Not Valid Without University Seal</div>
                    <div class="or-item">O.R. #: <strong>{{ $orNumber ?: '___________________' }}</strong></div>
                    <div class="or-item">Date: <strong>{{ $orDate ?: '___________________' }}</strong></div>
                    <div class="stamp-note">Doc. Stamp Tax Paid</div>
                </td>
            </tr>
        </table>
    </div>

    @if(request('format') === 'print')
        <script>
            window.onload = function() {
                setTimeout(function() { window.print(); }, 400);
            };
        </script>
    @endif
</body>
</html>
