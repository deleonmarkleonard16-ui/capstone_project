<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>PSU Certificate of Admission Test Result - {{ $applicant->application_number }}</title>
    <style>
        @page { size: letter portrait; margin: 50mm 15mm 28mm; }
        /* Dompdf uses the html frame as the page: do not reset its margins. */
        body { margin: 0; padding: 0; font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 12px; line-height: 1.5; color: #111827; }
        .page-letterhead { position: fixed; top: -50mm; left: -15mm; width: 215.9mm; height: 279.4mm; z-index: -1000; }
        .office-title-wrap { text-align: center; margin-bottom: 14px; }
        .office-title { font-size: 11px; font-weight: bold; color: #183861; }
        .office-campus { font-size: 9px; }
        .cert-title-wrap { text-align: center; margin: 14px 0 22px; }
        .cert-title { display: inline-block; font-size: 14px; font-weight: bold; letter-spacing: 1px; color: #183861; border-bottom: 1px solid #183861; padding-bottom: 4px; }
        .cert-prose { font-family: 'DejaVu Serif', Georgia, serif; font-size: 12px; text-align: justify; line-height: 1.6; margin-bottom: 16px; text-indent: 28px; overflow-wrap: break-word; }
        .table-wrap { margin: 16px 0 20px; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .results-table th, .results-table td { border: 1px solid #183861; padding: 9px; text-align: center; overflow-wrap: break-word; }
        .results-table th { background: #e8edf5; color: #183861; font-size: 11px; }
        .stanine-cell { font-size: 16px; font-weight: bold; }
        .remarks-cell { font-size: 11px; font-weight: bold; }
        tr, .table-wrap, .signatory-table, .verification-table { page-break-inside: avoid; }
        .signatory-table { margin: 20px 0 12px; }
        .signatory-space { width: 48%; }
        .signatory-cell { width: 52%; text-align: center; }
        .signatory-line { height: 22px; }
        .signatory-name { font-size: 11px; font-weight: bold; border-bottom: 1px solid #111; padding-bottom: 2px; }
        .signatory-title { font-size: 9px; margin-top: 3px; }
        .verification-table { margin-top: 8px; font-size: 9px; }
        .seal-note { font-style: italic; margin-bottom: 4px; }
        .or-item { margin-bottom: 2px; }
        .stamp-note { margin-top: 3px; font-weight: bold; }
        .no-print { display: none; }
        @if(request('format') !== 'pdf')
        @media screen {
            body { background: #e2e8f0; padding: 20px 0; }
            .cert-paper { position: relative; box-sizing: border-box; width: 215.9mm; min-height: 279.4mm; margin: auto; padding: 50mm 15mm 28mm; background: white; box-shadow: 0 8px 24px #0002; }
            .page-letterhead { position: absolute; top: 0; left: 0; z-index: 0; }
            .content-body { position: relative; z-index: 1; }
            .no-print { display: flex; justify-content: space-between; gap: 16px; align-items: center; box-sizing: border-box; max-width: 215.9mm; margin: 0 auto 16px; padding: 12px 18px; background: #17305f; color: white; }
            .no-print a { color: white; }
        }
        @endif
    </style>
</head>
<body>
    @php
        $letterheadSrc = 'data:image/png;base64,'.base64_encode(file_get_contents(public_path('images/admission/download-letterhead.png')));
    @endphp
    @if(request('format') !== 'pdf')
        <div class="no-print">
            <strong>Certificate of Admission Test Result · Letter Portrait</strong>
            <div>
                <a href="{{ request()->fullUrlWithQuery(['format' => 'pdf']) }}" target="_blank" rel="noopener">View / Print PDF</a>
                <button type="button" onclick="window.print()">Print Certificate</button>
            </div>
        </div>
    @endif
    <div class="cert-paper">
        <img class="page-letterhead" src="{{ $letterheadSrc }}" alt="">
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

    </div>
    @if(request('format') === 'print')
        <script>window.onload = function () { window.print(); };</script>
    @endif
</body>
</html>
