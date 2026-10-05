<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Paper Answer Sheets — {{ $session->session_name }}</title>
    <style>
        @page { size: A4 landscape; margin: 8mm; }
        body { margin: 0; font-family: Arial, Helvetica, sans-serif; color: #000; background: #f3f5f8; }
        .toolbar { position: sticky; top: 0; z-index: 2; padding: 12px; text-align: center; background: #fff; border-bottom: 1px solid #d8dee8; }
        .toolbar button { border: 0; border-radius: 6px; padding: 9px 16px; background: #0f3f97; color: #fff; cursor: pointer; font-weight: 700; }
        .toolbar small { display: block; color: #526177; margin-top: 6px; }
        .sheet { width: 280mm; min-height: 188mm; box-sizing: border-box; margin: 12px auto; padding: 10mm 12mm; background: #fff; page-break-after: always; break-after: page; }
        .sheet:last-child { page-break-after: auto; break-after: auto; }
        .header { display: flex; gap: 12px; align-items: flex-start; border-bottom: 1px solid #222; padding-bottom: 6px; }
        .logo { width: 50px; height: 50px; object-fit: contain; }
        h1 { margin: 0; font-size: 18px; text-transform: uppercase; }
        h2 { margin: 2px 0 7px; font-size: 13px; }
        .identity { font-size: 12px; line-height: 1.55; flex: 1; }
        .qr-box { width: 96px; text-align: center; flex: 0 0 96px; }
        .qr-box img { display: block; width: 82px; height: 82px; margin: 0 auto; border: 1px solid #222; padding: 2px; background: #fff; }
        .qr-box small { display: block; margin-top: 3px; font-size: 8px; font-weight: 700; word-break: break-all; }
        .notice { margin: 8px 0; padding: 5px 8px; font-size: 10px; line-height: 1.3; background: #f4f4f4; border-left: 3px solid #0f3f97; }
        .grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 0 12px; border: 1px solid #b8c1cd; padding: 8px 10px; }
        .column { min-width: 0; }
        .choice-header, .question { display: grid; grid-template-columns: 32px repeat(4, 1fr); align-items: center; text-align: center; }
        .choice-header { height: 22px; font-size: 10px; font-weight: 700; border-bottom: 1px solid #b8c1cd; }
        .question { height: 20px; font-size: 10px; }
        .question-number { text-align: right; padding-right: 6px; font-weight: 700; }
        .bubble { width: 13px; height: 13px; margin: auto; border: 1.2px solid #111; border-radius: 50%; box-sizing: border-box; }
        .footer { display: flex; justify-content: space-between; margin-top: 7px; padding-top: 4px; border-top: 1px solid #999; font-size: 9px; color: #444; }
        @media print { body { background: #fff; } .toolbar { display: none; } .sheet { margin: 0 auto; } }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Print all {{ $applicants->count() }} paper answer sheet(s)</button>
        <small>{{ $session->session_name }} · one answer sheet per page</small>
    </div>

    @foreach ($applicants as $applicant)
        @php
            $itemsPerColumn = (int) ceil($totalItems / 4);
        @endphp
        <section class="sheet">
            <header class="header">
                <img class="logo" src="{{ $psuLogoUrl }}" alt="Pangasinan State University logo">
                <div class="identity">
                    <h1>Pangasinan State University — San Carlos Campus</h1>
                    <h2>PSU College Admission Test (PSU-CAT) Official Answer Sheet</h2>
                    <div><strong>Full Name:</strong> {{ mb_strtoupper($applicant->full_name) }}</div>
                    <div><strong>Application No.:</strong> {{ $applicant->application_number }}</div>
                    <div><strong>Session:</strong> {{ $session->session_name }} &nbsp; <strong>Program:</strong> {{ \App\Support\CourseCatalog::label($applicant->course_choice) }}</div>
                </div>
                <div class="qr-box">
                    <img src="{{ app(\App\Services\GuidanceQrService::class)->dataUri($applicant->application_number) }}" alt="Applicant QR code for {{ $applicant->application_number }}">
                    <small>{{ $applicant->application_number }}</small>
                </div>
            </header>
            <div class="notice"><strong>NOTICE TO PROCTOR:</strong> Verify the examinee's identity before issuing this sheet. Use a dark pencil or pen and shade one answer only for each item.</div>

            <div class="grid" aria-label="{{ $totalItems }}-item OMR answer grid">
                @for ($column = 0; $column < 4; $column++)
                    <div class="column">
                        <div class="choice-header"><span>No.</span><span>A</span><span>B</span><span>C</span><span>D</span></div>
                        @for ($row = 1; $row <= $itemsPerColumn; $row++)
                            @php $item = ($column * $itemsPerColumn) + $row; @endphp
                            @if ($item <= $totalItems)
                                <div class="question"><span class="question-number">{{ $item }}.</span><span class="bubble"></span><span class="bubble"></span><span class="bubble"></span><span class="bubble"></span></div>
                            @endif
                        @endfor
                    </div>
                @endfor
            </div>
            <footer class="footer"><span>OMR Engine v2.0 · PSU San Carlos Campus Guidance Office</span><span>{{ $totalItems }}-Item Sheet · Form No. GC-CAT-01</span></footer>
        </section>
    @endforeach
</body>
</html>
