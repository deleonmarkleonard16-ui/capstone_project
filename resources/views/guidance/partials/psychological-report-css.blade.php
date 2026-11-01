@page { size: A4 portrait; margin: 50mm 13mm 30mm; }
body { font-family: 'DejaVu Sans', Arial, sans-serif; color: #111; font-size: 10px; line-height: 1.25; margin: 0; }
.assessment-letterhead { position: fixed; top: -50mm; left: -13mm; width: 210mm; height: 297mm; z-index: -1000; }
.assessment-report { width: 100%; max-width: 184mm; margin: 0 auto; }
.letterhead { display: none; }
.letterhead-copy { display: table-cell; width: 86%; text-align: center; vertical-align: middle; }
.letterhead-copy span, .letterhead-copy strong { display: block; }
.letterhead-copy .republic { font-size: 8px; margin-bottom: 1mm; }
.university-title { display: block; width: 77mm; max-height: 9mm; height: auto; object-fit: contain; margin: 0 auto 1mm; }
.university-name { color: #17305f; font-family: Georgia, serif; font-size: 18px; margin: 0 0 1mm; }
.letterhead-copy strong { font-size: 9px; }
.letterhead-copy span:last-child { font-size: 7px; margin-top: 1mm; }
.university-seal { display: table-cell; width: 22mm; height: 22mm; object-fit: contain; vertical-align: middle; }
.office-heading { text-align: center; border-bottom: 1px solid #333; margin-bottom: 3mm; padding-bottom: 2mm; }
h1, h2 { font-size: 10px; margin: 1px 0; }
.office-heading h2 { font-size: 9px; text-decoration: underline; }
.office-heading h3 { font-size: 14px; margin: 2px 0 0; }
h3 { font-size: 9px; margin: 3mm 0 1mm; }
table { width: 100%; border-collapse: collapse; table-layout: fixed; }
.profile td { width: 50%; padding: 3px 6px 3px 0; vertical-align: top; }
.field { border-bottom: 1px solid #555; overflow-wrap: break-word; }
.matrix th, .matrix td { border: 1px solid #222; padding: 3px 3px; vertical-align: middle; }
.matrix .scale { width: 43%; text-align: left; }
.career-matrix thead th:nth-child(1) { width: 24%; }
.career-matrix thead th:nth-child(2) { width: 33%; }
.career-matrix thead th:nth-child(n+3):nth-child(-n+7) { width: 5%; }
.career-matrix thead th:nth-child(8) { width: 18%; }
.career-matrix .scale { width: 24%; }
.matrix th, .matrix td { overflow-wrap: break-word; }
.career-matrix .career-description, .career-matrix .career-interpretation { font-size: 9px; }
.career-matrix .mark { font-size: 10px; padding: 2px 1px; }
.top-code { margin: 0 0 1mm; }
.legend { margin: 1mm 0 0; font-size: 7px; }
.matrix thead .scale { text-align: center; }
.matrix th { font-weight: bold; }
.description { display: block; font-weight: normal; font-size: 7px; }
.mark { text-align: center; font-weight: bold; font-size: 10px; }
tr, .recommendations, .signatures, footer { page-break-inside: avoid; }
thead { display: table-header-group; }
h3 { page-break-after: avoid; }
.recommendations p { margin: 2px 0; }
.note, .incomplete { font-size: 8px; }
.signatures { margin: 10px 0; }
.signatures td { width: 50%; padding-right: 24px; vertical-align: top; }
.signature-line { border-bottom: 1px solid #222; min-height: 13px; padding-top: 5px; }
footer { font-size: 8px; margin-top: 8px; }
.footer-accent { display: none; }
.no-print { padding: 15px; text-align: center; }
.no-print a, .no-print button { margin: 0 6px; }
.assessment-result-details { page-break-before: always; }
.assessment-result-details h2 { font-size: 14px; color: #17305f; margin: 5mm 0 3mm; page-break-after: avoid; }
.result-detail-table { margin-bottom: 4mm; }
.result-detail-table th, .result-detail-table td { border: 1px solid #999; padding: 4px; overflow-wrap: break-word; }
.result-detail-table th { background: #e8edf5; text-align: left; }
.raw-item-choices th:first-child { width: 18%; }
@media print {
    .no-print { display: none !important; }
    body { margin: 0; }
    .assessment-report { max-width: none; margin: 0; }
}
