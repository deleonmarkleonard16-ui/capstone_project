@page { size: A4 portrait; margin: 14mm 16mm; }
body { font-family: 'DejaVu Sans', Arial, sans-serif; color: #111; font-size: 10px; line-height: 1.35; margin: 0; }
.assessment-report { width: 100%; max-width: 178mm; margin: 0 auto; }
header { text-align: center; margin-bottom: 18px; }
h1, h2 { font-size: 13px; margin: 3px 0; }
h3 { font-size: 11px; margin: 14px 0 5px; }
table { width: 100%; border-collapse: collapse; table-layout: fixed; }
.profile td { width: 50%; padding: 5px 8px 5px 0; vertical-align: top; }
.field { border-bottom: 1px solid #555; overflow-wrap: break-word; }
.matrix th, .matrix td { border: 1px solid #222; padding: 6px 4px; vertical-align: middle; }
.matrix .scale { width: 43%; text-align: left; }
.matrix thead .scale { text-align: center; }
.matrix th { font-weight: bold; }
.description { display: block; font-weight: normal; font-size: 9px; }
.mark { text-align: center; font-weight: bold; font-size: 13px; }
tr, .recommendations, .signatures, footer { page-break-inside: avoid; }
thead { display: table-header-group; }
h3 { page-break-after: avoid; }
.recommendations p { margin: 4px 0; }
.note, .incomplete { font-size: 9px; }
.signatures { margin: 20px 0; }
.signatures td { width: 50%; padding-right: 24px; vertical-align: top; }
.signature-line { border-bottom: 1px solid #222; min-height: 16px; padding-top: 8px; }
footer { font-size: 9px; margin-top: 18px; }
.no-print { padding: 15px; text-align: center; }
.no-print a, .no-print button { margin: 0 6px; }
@media print {
    .no-print { display: none !important; }
    body { margin: 0; }
    .assessment-report { max-width: none; margin: 0; }
}
