<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>PSU-CAT Answer Sheet – {{ $applicant->application_number }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 8mm;
        }
        body {
            font-family: Arial, Helvetica, sans-serif;
            margin: 0;
            padding: 8px;
            color: #000;
            background: #fff;
        }
        .sheet-container {
            width: 280mm;
            height: 188mm;
            position: relative;
            box-sizing: border-box;
            border: 1px solid #ddd;
            padding: 10px 14px;
            margin: 0 auto;
        }
        @media print {
            body { padding: 0; }
            .sheet-container { border: none; }
            .no-print { display: none !important; }
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }
        .header-table td {
            vertical-align: top;
        }
        .title-block h1 {
            font-size: 18px;
            font-weight: bold;
            margin: 0 0 2px 0;
            text-transform: uppercase;
        }
        .title-block .subtitle {
            font-size: 13px;
            font-weight: bold;
            color: #111;
            margin-bottom: 6px;
        }
        .applicant-info {
            font-size: 12px;
            line-height: 1.45;
        }
        .applicant-info strong {
            display: inline-block;
            min-width: 130px;
        }
        .proctor-notice {
            font-size: 10.5px;
            color: #222;
            background: #f4f4f4;
            border-left: 3px solid #0f3f97;
            padding: 3px 8px;
            margin-top: 5px;
            line-height: 1.3;
        }
        .qr-box {
            text-align: right;
            width: 110px;
        }
        .qr-box img {
            width: 95px;
            height: 95px;
            border: 1px solid #333;
            padding: 2px;
            background: #fff;
        }
        .qr-box .qr-caption {
            font-size: 9px;
            font-weight: bold;
            text-align: center;
            margin-top: 2px;
        }

        /* SVG Grid for High Accuracy OMR */
        svg.omr-grid {
            width: 100%;
            height: 125mm;
            display: block;
        }

        .footer-note {
            font-size: 9.5px;
            color: #444;
            display: flex;
            justify-content: space-between;
            margin-top: 4px;
            border-top: 1px solid #ccc;
            padding-top: 3px;
        }
        .btn-print {
            padding: 8px 18px;
            background: #0f3f97;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-weight: bold;
            cursor: pointer;
            font-size: 14px;
            margin-bottom: 12px;
        }
    </style>
</head>
<body>

<div class="no-print" style="text-align: center; margin-bottom: 10px;">
    <button class="btn-print" onclick="window.print()">🖨️ Print Answer Sheet (A4 Landscape)</button>
    <a href="{{ route('admin.admission.masterlist') }}" style="margin-left: 12px; font-size: 13px; color: #0f3f97;">← Return to Masterlist</a>
</div>

<div class="sheet-container">
    {{-- Top Header Section --}}
    <table class="header-table">
        <tr>
            <td class="title-block">
                <h1>Pangasinan State University – San Carlos Campus</h1>
                <div class="subtitle">PSU College Admission Test (PSU-CAT) Official Answer Sheet</div>
                <div class="applicant-info">
                    <div><strong>Full Name:</strong> <span style="font-size: 13px; font-weight: bold; text-decoration: underline;">{{ mb_strtoupper($applicant->full_name) }}</span></div>
                    <div><strong>Application No.:</strong> <span style="font-family: monospace; font-size: 13px; font-weight: bold;">{{ $applicant->application_number }}</span></div>
                    <div><strong>1st Choice Program:</strong> {{ \App\Support\CourseCatalog::label($applicant->course_choice) }}</div>
                </div>
                <div class="proctor-notice">
                    <strong>NOTICE TO PROCTOR:</strong> Match valid test permit / valid ID against the printed name above before handing out this sheet.
                    Examinees must completely shade only one bubble per item using dark pencil or pen.
                </div>
            </td>
            <td class="qr-box">
                <img src="{{ app(\App\Services\GuidanceQrService::class)->dataUri($applicant->application_number) }}" alt="Applicant QR">
                <div class="qr-caption">{{ $applicant->application_number }}</div>
            </td>
        </tr>
    </table>

    {{-- OMR Answer Grid with 4 Corner Markers (Width: 1080, Height: dynamic) --}}
    @php
        $omrCols      = 4;
        $omrRowsPerCol = (int) ceil($totalItems / $omrCols);
        // Scale SVG height based on rows: header(54) + rows*21 + bottom-pad(16)
        $omrSvgHeight = 54 + ($omrRowsPerCol * 21) + 16;
        // Ensure corner markers fit (min 480)
        $omrSvgHeight = max($omrSvgHeight, 480);
        $markerBottom  = $omrSvgHeight - 24;
    @endphp
    <svg class="omr-grid" viewBox="0 0 1080 {{ $omrSvgHeight }}" xmlns="http://www.w3.org/2000/svg" aria-label="PSU-CAT Answer Sheet Matrix — {{ $totalItems }} Items">
        <!-- Sheet Background -->
        <rect width="1080" height="{{ $omrSvgHeight }}" fill="#ffffff"/>

        <!-- 4 Precise High-Contrast Corner Markers for Webcam / Scanner Computer Vision -->
        <!-- Top-Left -->
        <rect x="10" y="10" width="14" height="14" fill="#000000"/>
        <!-- Top-Right -->
        <rect x="1056" y="10" width="14" height="14" fill="#000000"/>
        <!-- Bottom-Right -->
        <rect x="1056" y="{{ $markerBottom }}" width="14" height="14" fill="#000000"/>
        <!-- Bottom-Left -->
        <rect x="10" y="{{ $markerBottom }}" width="14" height="14" fill="#000000"/>

        <!-- {{ $omrCols }} Columns of {{ $omrRowsPerCol }} Rows = {{ $totalItems }} Items Total -->
        @for ($col = 0; $col < $omrCols; $col++)
            @php $colStartX = 40 + ($col * 260); @endphp

            <!-- Column Header (Letters A, B, C, D) -->
            <g font-family="Arial" font-size="11" font-weight="bold" fill="#333" text-anchor="middle">
                @foreach (['A', 'B', 'C', 'D'] as $optIndex => $letter)
                    <text x="{{ $colStartX + 85 + ($optIndex * 36) }}" y="32">{{ $letter }}</text>
                @endforeach
            </g>

            <!-- {{ $omrRowsPerCol }} Question Rows -->
            @for ($row = 0; $row < $omrRowsPerCol; $row++)
                @php
                    $qNum = ($col * $omrRowsPerCol) + $row + 1;
                    if ($qNum > $totalItems) continue; // skip phantom items
                    $rowY = 54 + ($row * 21);
                @endphp

                <!-- Item Number -->
                <text x="{{ $colStartX + 10 }}" y="{{ $rowY + 4 }}"
                      font-family="Arial" font-size="11" font-weight="bold" fill="#111" text-anchor="start">
                    {{ str_pad($qNum, 2, ' ', STR_PAD_LEFT) }}.
                </text>

                <!-- Bubbles A, B, C, D -->
                @foreach (['A', 'B', 'C', 'D'] as $optIndex => $letter)
                    @php $bubbleX = $colStartX + 85 + ($optIndex * 36); @endphp
                    <circle cx="{{ $bubbleX }}" cy="{{ $rowY }}" r="7.5"
                            fill="#ffffff" stroke="#111111" stroke-width="1.2"/>
                    <text x="{{ $bubbleX }}" y="{{ $rowY + 3 }}"
                          font-family="Arial" font-size="8" fill="#666666" text-anchor="middle">
                        {{ $letter }}
                    </text>
                @endforeach
            @endfor

            <!-- Column Separator Line (except last column) -->
            @if ($col < 3)
                <line x1="{{ $colStartX + 248 }}" y1="20" x2="{{ $colStartX + 248 }}" y2="{{ $markerBottom + 10 }}"
                      stroke="#e0e0e0" stroke-width="1" stroke-dasharray="3,3"/>
            @endif
        @endfor
    </svg>

    {{-- Footer Info --}}
    <div class="footer-note">
        <span>OMR Engine v2.0 – PSU San Carlos Campus Guidance Office</span>
        <span>{{ $totalItems }}-Item Sheet · IMPORTANT: Keep 4 black corner markers fully visible. Do not fold or crease.</span>
        <span>Form No. GC-CAT-01</span>
    </div>
</div>

</body>
</html>
