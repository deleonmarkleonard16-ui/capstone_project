<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>PSU Certificate of Admission Test Result - {{ $applicant->application_number }}</title>
    <style>
        @page {
            size: letter portrait;
            margin: 0;
        }
        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        html, body {
            margin: 0;
            padding: 0;
            background: #ffffff;
            color: #111827;
            font-family: Arial, "Helvetica Neue", Helvetica, "Times New Roman", sans-serif;
            font-size: 12px;
            line-height: 1.55;
        }

        /* Screen Preview Styling */
        @if(request('format') === 'print' || !request()->has('format'))
        body.screen-preview {
            background-color: #e2e8f0;
            padding: 20px 0 40px;
        }
        .cert-paper {
            width: 8.5in;
            min-height: 11in;
            margin: 0 auto;
            background: #ffffff;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
            position: relative;
        }
        @else
        .cert-paper {
            width: 100%;
            position: relative;
        }
        @endif

        /* Print Action Bar (Browser Only) */
        .no-print {
            max-width: 8.5in;
            margin: 0 auto 16px auto;
            background: #1e293b;
            color: #ffffff;
            padding: 10px 20px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-family: Arial, sans-serif;
            font-size: 13px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .no-print button {
            padding: 7px 18px;
            background: #2563eb;
            color: #ffffff;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            font-size: 13px;
        }
        .no-print button:hover {
            background: #1d4ed8;
        }

        /* HEADER BAND - Official PSU Bright Yellow */
        .header-band {
            width: 100%;
            background-color: #ffff00;
            padding: 14px 30px 10px 30px;
            border-bottom: 1.5px solid #000000;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
        }
        .header-seal-col {
            width: 95px;
            vertical-align: middle;
            text-align: left;
        }
        .header-seal-img {
            width: 86px;
            height: 86px;
            display: block;
        }
        .header-center-col {
            vertical-align: middle;
            text-align: center;
            padding: 0 8px;
        }
        .header-republic {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #000000;
            margin-bottom: 2px;
            font-weight: 500;
        }
        .header-university {
            margin: 1px 0 2px;
            line-height: 1;
        }
        .header-gothic-img {
            height: 32px;
            width: auto;
            max-width: 100%;
            display: inline-block;
        }
        .header-gothic-fallback {
            font-family: "Old English Text MT", "Cloister Black", Georgia, serif;
            font-size: 24px;
            color: #002664;
            font-weight: bold;
            letter-spacing: 0.5px;
        }
        .header-campus {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #000000;
            margin-bottom: 5px;
            font-weight: 500;
        }
        .header-contact-row {
            font-family: Arial, sans-serif;
            font-size: 8.5px;
            color: #000000;
            white-space: nowrap;
        }
        .contact-item {
            display: inline-block;
            vertical-align: middle;
        }
        .contact-icon {
            width: 10px;
            height: 10px;
            vertical-align: -1px;
            margin-right: 3px;
            display: inline-block;
        }
        .header-bp-col {
            width: 95px;
            vertical-align: middle;
            text-align: right;
        }
        .header-bp-img {
            width: 80px;
            height: auto;
            display: inline-block;
        }

        /* BODY CONTENT CONTAINER */
        .content-body {
            padding: 18px 52px 10px 52px;
            position: relative;
        }

        /* Subtle Center Watermark */
        .watermark-container {
            position: absolute;
            top: 110px;
            left: 50%;
            margin-left: -180px;
            width: 360px;
            height: 360px;
            text-align: center;
            opacity: 0.08;
            z-index: 0;
            pointer-events: none;
        }
        .watermark-img {
            width: 340px;
            height: auto;
        }

        /* Inner Content layered above watermark */
        .content-inner {
            position: relative;
            z-index: 1;
        }

        /* Office Title */
        .office-title-wrap {
            text-align: center;
            margin-bottom: 16px;
        }
        .office-title {
            font-family: Arial, sans-serif;
            font-size: 13px;
            font-weight: bold;
            color: #183861;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }
        .office-campus {
            font-family: Arial, sans-serif;
            font-size: 11px;
            font-weight: 600;
            color: #374151;
        }

        /* Certificate Main Title */
        .cert-title-wrap {
            text-align: center;
            margin: 16px 0 20px;
        }
        .cert-title {
            font-family: Arial, sans-serif;
            font-size: 15px;
            font-weight: bold;
            letter-spacing: 1.5px;
            color: #183861;
            text-transform: uppercase;
            display: inline-block;
            border-bottom: 2px solid #183861;
            padding-bottom: 3px;
        }

        /* Certification Body Paragraphs */
        .cert-prose {
            font-family: "Times New Roman", Times, Georgia, serif;
            font-size: 12.5px;
            text-align: justify;
            line-height: 1.75;
            margin-bottom: 16px;
            text-indent: 38px;
            color: #000000;
        }
        .cert-prose strong {
            color: #000000;
        }

        /* 2-Column Official Results Table */
        .table-wrap {
            margin: 18px 0 20px;
            width: 100%;
        }
        .results-table {
            width: 100%;
            border-collapse: collapse;
            font-family: Arial, sans-serif;
            font-size: 12px;
            border: 2px solid #183861;
        }
        .results-table th {
            background-color: #f8fafc;
            color: #183861;
            font-weight: bold;
            text-transform: uppercase;
            padding: 7px 12px;
            border: 1px solid #183861;
            letter-spacing: 0.75px;
        }
        .results-table td {
            padding: 10px 14px;
            border: 1px solid #183861;
            vertical-align: middle;
        }
        .results-table .stanine-cell {
            width: 32%;
            text-align: center;
            font-size: 14.5px;
            font-weight: bold;
            color: #183861;
        }
        .results-table .remarks-cell {
            width: 68%;
            text-align: center;
            font-size: 12px;
            font-weight: bold;
            color: #0f172a;
            letter-spacing: 0.5px;
        }

        /* Signatory Area */
        .signatory-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 18px;
            margin-bottom: 14px;
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
            height: 25px;
        }
        .signatory-name {
            font-family: Arial, sans-serif;
            font-size: 12.5px;
            font-weight: bold;
            text-transform: uppercase;
            color: #000000;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #000000;
            display: inline-block;
            padding-bottom: 2px;
            min-width: 240px;
        }
        .signatory-title {
            font-family: Arial, sans-serif;
            font-size: 10.5px;
            color: #374151;
            margin-top: 4px;
        }

        /* Verification & Receipt Information */
        .verification-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            margin-bottom: 8px;
            font-family: Arial, sans-serif;
            font-size: 10px;
            color: #374151;
        }
        .verification-table td {
            vertical-align: top;
        }
        .seal-note {
            font-style: italic;
            font-weight: bold;
            color: #4b5563;
            margin-bottom: 4px;
        }
        .or-item {
            margin-bottom: 2px;
        }
        .stamp-note {
            margin-top: 3px;
            font-weight: bold;
            color: #111827;
        }

        /* FOOTER BAND - Official PSU Yellow Banner */
        .footer-band {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            width: 100%;
            background-color: #ffff00;
            line-height: 0;
            z-index: 10;
        }
        .footer-banner-img {
            width: 100%;
            height: auto;
            display: block;
        }

        /* PRINT STYLES */
        @media print {
            .no-print {
                display: none !important;
            }
            body.screen-preview {
                padding: 0 !important;
                background: #ffffff !important;
            }
            .cert-paper {
                width: 100% !important;
                min-height: auto !important;
                box-shadow: none !important;
                margin: 0 !important;
            }
            .footer-band {
                position: fixed !important;
                bottom: 0 !important;
                left: 0 !important;
                width: 100% !important;
            }
        }
    </style>
</head>
<body class="{{ request('format') === 'print' || !request()->has('format') ? 'screen-preview' : '' }}">
    @php
        // Resolve images as base64 for 100% reliable Dompdf and browser rendering
        $sealPath = public_path('images/psu-template/image1.png');
        if (!is_file($sealPath)) $sealPath = public_path('images/psu-template/image2.png');
        if (!is_file($sealPath)) $sealPath = public_path('images/psu-logo.jpg');
        $psuSealSrc = is_file($sealPath) ? 'data:image/' . (str_ends_with($sealPath, '.png') ? 'png' : 'jpeg') . ';base64,' . base64_encode(file_get_contents($sealPath)) : '';

        $bpPath = public_path('images/psu-template/bagong_pilipinas.png');
        if (!is_file($bpPath)) $bpPath = public_path('images/psu-template/image6.png');
        $bagongPilipinasSrc = is_file($bpPath) ? 'data:image/' . (str_ends_with($bpPath, '.png') ? 'png' : 'jpeg') . ';base64,' . base64_encode(file_get_contents($bpPath)) : '';

        $gothicPath = public_path('images/psu-template/psu_title_gothic.png');
        $gothicTitleSrc = is_file($gothicPath) ? 'data:image/png;base64,' . base64_encode(file_get_contents($gothicPath)) : '';

        $globePath = public_path('images/psu-template/image3.png');
        $globeIconSrc = is_file($globePath) ? 'data:image/png;base64,' . base64_encode(file_get_contents($globePath)) : '';

        $mailPath = public_path('images/psu-template/image4.png');
        $mailIconSrc = is_file($mailPath) ? 'data:image/png;base64,' . base64_encode(file_get_contents($mailPath)) : '';

        $fbPath = public_path('images/psu-template/image5.png');
        $fbIconSrc = is_file($fbPath) ? 'data:image/png;base64,' . base64_encode(file_get_contents($fbPath)) : '';

        $footerPath = public_path('images/psu-template/image8.png');
        if (!is_file($footerPath)) $footerPath = public_path('images/psu-template/image8.jpg');
        $footerSrc = is_file($footerPath) ? 'data:image/' . (str_ends_with($footerPath, '.png') ? 'png' : 'jpeg') . ';base64,' . base64_encode(file_get_contents($footerPath)) : '';

        $watermarkPath = public_path('images/psu-template/watermark.jpg');
        if (!is_file($watermarkPath)) $watermarkPath = public_path('images/psu-template/image1.png');
        $watermarkSrc = is_file($watermarkPath) ? 'data:image/' . (str_ends_with($watermarkPath, '.png') ? 'png' : 'jpeg') . ';base64,' . base64_encode(file_get_contents($watermarkPath)) : '';
    @endphp

    @if(request('format') === 'print' || !request()->has('format'))
        <div class="no-print">
            <div>
                <strong>Official PSU Certificate of Admission Test Result</strong>
                <span style="opacity: 0.8; margin-left: 8px;">(Letter Portrait Format)</span>
            </div>
            <div>
                <a href="{{ request()->fullUrlWithQuery(['format' => 'pdf']) }}" style="color: #93c5fd; text-decoration: none; margin-right: 14px; font-weight: 600;">
                    Download Official PDF
                </a>
                <button onclick="window.print()">
                    Print Certificate
                </button>
            </div>
        </div>
    @endif

    <div class="cert-paper">
        {{-- Background Watermark --}}
        @if($watermarkSrc)
            <div class="watermark-container">
                <img src="{{ $watermarkSrc }}" alt="PSU Watermark" class="watermark-img">
            </div>
        @endif

        {{-- 1. OFFICIAL FULL-WIDTH YELLOW HEADER BAND --}}
        <div class="header-band">
            <table class="header-table">
                <tr>
                    {{-- Left Column: Official PSU Seal --}}
                    <td class="header-seal-col">
                        @if($psuSealSrc)
                            <img src="{{ $psuSealSrc }}" alt="PSU Seal" class="header-seal-img">
                        @endif
                    </td>

                    {{-- Center Column: Institutional Headings & Social/Web Links --}}
                    <td class="header-center-col">
                        <div class="header-republic">Republic of the Philippines</div>
                        <div class="header-university">
                            @if($gothicTitleSrc)
                                <img src="{{ $gothicTitleSrc }}" alt="Pangasinan State University" class="header-gothic-img">
                            @else
                                <span class="header-gothic-fallback">Pangasinan State University</span>
                            @endif
                        </div>
                        <div class="header-campus">Lingayen, Pangasinan</div>
                        <div class="header-contact-row">
                            <span class="contact-item">
                                @if($globeIconSrc)
                                    <img src="{{ $globeIconSrc }}" alt="" class="contact-icon">
                                @endif
                                <span>www.psu.edu.ph</span>
                            </span>
                            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                            <span class="contact-item">
                                @if($mailIconSrc)
                                    <img src="{{ $mailIconSrc }}" alt="" class="contact-icon">
                                @endif
                                <span>president@psu.edu.ph</span>
                            </span>
                            &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                            <span class="contact-item">
                                @if($fbIconSrc)
                                    <img src="{{ $fbIconSrc }}" alt="" class="contact-icon">
                                @endif
                                <span>www.facebook.com/PSUroars</span>
                            </span>
                        </div>
                    </td>

                    {{-- Right Column: Official Bagong Pilipinas Logo --}}
                    <td class="header-bp-col">
                        @if($bagongPilipinasSrc)
                            <img src="{{ $bagongPilipinasSrc }}" alt="Bagong Pilipinas" class="header-bp-img">
                        @endif
                    </td>
                </tr>
            </table>
        </div>

        {{-- 2. CERTIFICATE BODY CONTENT --}}
        <div class="content-body">
            <div class="content-inner">
                {{-- Office Title --}}
                <div class="office-title-wrap">
                    <div class="office-title">OFFICE OF ADMISSION AND GUIDANCE SERVICES</div>
                    <div class="office-campus">San Carlos Campus · San Carlos City, Pangasinan</div>
                </div>

                {{-- Certificate Main Title --}}
                <div class="cert-title-wrap">
                    <div class="cert-title">CERTIFICATE OF ADMISSION TEST RESULT</div>
                </div>

                {{-- Certification Prose --}}
                <div class="cert-prose">
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

                {{-- Purpose Paragraph --}}
                <div class="cert-prose">
                    This certification is issued upon the request of <strong>{{ mb_strtoupper($requestorName) }}</strong> for
                    <strong>{{ mb_strtoupper($purpose) }}</strong> purposes only.
                </div>

                {{-- Issued Date Paragraph --}}
                <div class="cert-prose">
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
            </div>
        </div>

        {{-- 3. OFFICIAL FULL-WIDTH YELLOW FOOTER BANNER --}}
        @if($footerSrc)
            <div class="footer-band">
                <img src="{{ $footerSrc }}" alt="Accreditation Banner" class="footer-banner-img">
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
