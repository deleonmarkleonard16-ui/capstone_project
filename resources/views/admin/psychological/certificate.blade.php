<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Certificate of Psychological Assessment</title>
    <style>
        @page { size: letter portrait; margin: 15mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #000; background: #fff; font-family: Georgia, "Times New Roman", serif; font-size: 14px; line-height: 1.65; }
        .certificate-frame { border: 4px double #000; padding: 30px; margin: 20px; min-height: 470px; }
        .institution { text-align: center; line-height: 1.25; }
        .institution strong { display: block; font-size: 17px; }
        h1 { text-align: center; font-size: 21px; margin: 36px 0 30px; }
        .statement { text-align: left; margin: 0 0 12px; }
        .details { margin-top: 14px; }
        .details p { margin: 4px 0; }
        .signature { width: 230px; margin: 76px 0 0 auto; text-align: center; line-height: 1.25; }
        .signature-line { border-top: 1px solid #000; height: 1px; }
        @media print {
            body { background: #fff; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .certificate-frame { border: 4px double #000; padding: 30px; margin: 20px; break-inside: avoid; }
        }
    </style>
</head>
<body>
    <main class="certificate-frame">
        <header class="institution">
            <strong>PANGASINAN STATE UNIVERSITY</strong>
            <div>San Carlos Campus</div>
            <div>Guidance and Counseling Office</div>
        </header>

        <h1>Certificate of Psychological Assessment</h1>

        <p class="statement">This certifies that <strong>{{ $studentName }}</strong>, student number
            <strong>{{ $studentNumber }}</strong>, enrolled in <strong>{{ $courseName }}</strong>,
            has been issued a certificate of psychological assessment completion.</p>

        <div class="details">
            <p><strong>Purpose:</strong> {{ $purpose }}</p>
            <p><strong>Issued:</strong> {{ $issuedAt }}</p>
        </div>

        <div class="signature">
            <div class="signature-line"></div>
            <div>Guidance Counselor</div>
        </div>
    </main>
    <script>window.onload = function() { window.print(); };</script>
</body>
</html>
