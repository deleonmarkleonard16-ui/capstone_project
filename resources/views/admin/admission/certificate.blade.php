<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>PSU-CAT Certificate of Admission Test Result - {{ $applicant->application_number }}</title>
    <style>
        @page {
            size: letter portrait;
            margin: 12mm 18mm 12mm;
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
            position: relative;
        }
        .cert-container {
            width: 100%;
            min-height: 960px;
            position: relative;
        }
        /* Official Background Watermark */
        .watermark-container {
            position: absolute;
            top: 280px;
            left: 50%;
            margin-left: -200px;
            width: 400px;
            height: 400px;
            text-align: center;
            opacity: 0.12;
            z-index: -1;
        }
        .watermark-img {
            width: 380px;
            height: auto;
        }
        /* Official Institutional Letterhead Header */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
        }
        .header-logo-cell {
            width: 85px;
            vertical-align: middle;
            text-align: left;
        }
        .header-logo {
            width: 80px;
            height: auto;
        }
        .header-text-cell {
            vertical-align: middle;
            text-align: center;
            padding-right: 85px; /* balances the left logo */
        }
        .republic {
            font-size: 11px;
            color: #374151;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 1px;
        }
        .university {
            font-size: 17px;
            font-weight: bold;
            color: #183861;
            letter-spacing: 0.5px;
            margin: 1px 0;
        }
        .campus {
            font-size: 11px;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 3px;
        }
        .office {
            font-size: 12.5px;
            font-weight: bold;
            color: #183861;
            text-transform: uppercase;
            letter-spacing: 0.75px;
            margin-top: 3px;
        }
        /* Official Decorative Divider Bar */
        .header-bar-wrap {
            width: 100%;
            text-align: center;
            margin-top: 6px;
            margin-bottom: 24px;
        }
        .header-bar {
            width: 100%;
            height: 6px;
            display: block;
        }
        /* Certificate Title */
        .title-container {
            text-align: center;
            margin: 24px 0 26px;
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
            margin: 22px 0 26px;
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
            padding: 9px 14px;
            border: 1px solid #183861;
            letter-spacing: 0.75px;
        }
        .results-table td {
            padding: 13px 16px;
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
            font-size: 13px;
            font-weight: bold;
            color: #0f172a;
            letter-spacing: 0.5px;
        }
        /* Purpose and Date */
        .cert-purpose-p {
            font-size: 13px;
            text-align: justify;
            line-height: 1.8;
            margin-bottom: 24px;
            text-indent: 36px;
        }
        .cert-date-p {
            font-size: 13px;
            text-align: justify;
            line-height: 1.8;
            margin-bottom: 34px;
            text-indent: 36px;
        }
        /* Signatory Area */
        .signatory-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 30px;
            margin-bottom: 25px;
        }
        .signatory-space {
            width: 45%;
        }
        .signatory-cell {
            width: 55%;
            text-align: center;
            vertical-align: top;
        }
        .signatory-line {
            height: 35px;
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
            min-width: 260px;
        }
        .signatory-title {
            font-size: 11.5px;
            color: #374151;
            margin-top: 4px;
        }
        /* Verification & Receipt Information */
        .verification-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            margin-bottom: 15px;
            font-size: 11px;
            color: #374151;
        }
        .verification-table td {
            vertical-align: top;
        }
        .seal-note {
            font-style: italic;
            font-weight: bold;
            color: #4b5563;
            margin-bottom: 6px;
        }
        .or-item {
            margin-bottom: 3px;
        }
        .stamp-note {
            margin-top: 5px;
            font-weight: 600;
        }
        /* Official Footer Banner */
        .footer-banner-wrap {
            margin-top: 10px;
            width: 100%;
            text-align: center;
            border-top: 1px solid #e5e7eb;
            padding-top: 8px;
        }
        .footer-banner {
            width: 100%;
            max-height: 40px;
            height: auto;
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
            <span>Official PSU Certificate Template. Click below to print or save as PDF via browser:</span>
            <button onclick="window.print()">
                Print Certificate
            </button>
        </div>
    @endif

    @php
        // Resolve base64 encoded template graphics for 100% reliable Dompdf rendering
        $logoPath = public_path('images/psu-template/image1.jpg');
        if (!is_file($logoPath)) $logoPath = public_path('images/psu-template/image1.png');
        if (!is_file($logoPath)) $logoPath = public_path('images/psu-logo.jpg');
        $logoSrc = is_file($logoPath) ? 'data:image/' . (str_ends_with($logoPath, '.png') ? 'png' : 'jpeg') . ';base64,' . base64_encode(file_get_contents($logoPath)) : '';

        $barPath = public_path('images/psu-template/image7.jpg');
        if (!is_file($barPath)) $barPath = public_path('images/psu-template/image7.png');
        $barSrc = is_file($barPath) ? 'data:image/' . (str_ends_with($barPath, '.png') ? 'png' : 'jpeg') . ';base64,' . base64_encode(file_get_contents($barPath)) : '';

        $watermarkPath = public_path('images/psu-template/watermark.jpg');
        if (!is_file($watermarkPath)) $watermarkPath = public_path('images/psu-template/image6.png');
        $watermarkSrc = is_file($watermarkPath) ? 'data:image/' . (str_ends_with($watermarkPath, '.png') ? 'png' : 'jpeg') . ';base64,' . base64_encode(file_get_contents($watermarkPath)) : '';

        $footerPath = public_path('images/psu-template/image8.jpg');
        if (!is_file($footerPath)) $footerPath = public_path('images/psu-template/image8.png');
        $footerSrc = is_file($footerPath) ? 'data:image/' . (str_ends_with($footerPath, '.png') ? 'png' : 'jpeg') . ';base64,' . base64_encode(file_get_contents($footerPath)) : '';
    @endphp

    <div class="cert-container">
        {{-- Background Watermark --}}
        @if($watermarkSrc)
            <div class="watermark-container">
                <img src="{{ $watermarkSrc }}" alt="PSU Watermark" class="watermark-img">
            </div>
        @endif

        {{-- Official Institutional Letterhead Header --}}
        <table class="header-table">
            <tr>
                <td class="header-logo-cell">
                    @if($logoSrc)
                        <img src="{{ $logoSrc }}" alt="PSU Seal" class="header-logo">
                    @endif
                </td>
                <td class="header-text-cell">
                    <div class="republic">Republic of the Philippines</div>
                    <div class="university">PANGASINAN STATE UNIVERSITY</div>
                    <div class="campus">San Carlos Campus · San Carlos City, Pangasinan</div>
                    <div class="office">OFFICE OF ADMISSION AND GUIDANCE SERVICES</div>
                </td>
            </tr>
        </table>

        {{-- Official Gold/Blue Divider Bar --}}
        @if($barSrc)
            <div class="header-bar-wrap">
                <img src="{{ $barSrc }}" alt="Decorative Divider Bar" class="header-bar">
            </div>
        @endif

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

        {{-- Official 2-Column Stanine & Remarks Table --}}
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

        {{-- Signatory Area --}}
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

        {{-- Official Verification & Receipt Footer --}}
        <table class="verification-table">
            <tr>
                <td>
                    <div class="seal-note">*Not Valid Without University Seal</div>
                    <div class="or-item">O.R. #: <strong>{{ $orNumber ?: '___________________' }}</strong></div>
                    <div class="or-item">Date: <strong>{{ $orDate ?: '___________________' }}</strong></div>
                    <div class="stamp-note">Doc. Stamp Tax Paid</div>
                </td>
            </tr>
        </table>

        {{-- Official Accreditation Banner --}}
        @if($footerSrc)
            <div class="footer-banner-wrap">
                <img src="{{ $footerSrc }}" alt="Accreditation Banner" class="footer-banner">
            </div>
        @endif
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
