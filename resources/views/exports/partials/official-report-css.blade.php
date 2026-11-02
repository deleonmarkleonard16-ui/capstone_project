@page { size: A4 {{ $orientation }}; margin: {{ $officialTopMargin }}mm 13mm {{ $officialBottomMargin }}mm; }
body { margin: 0; line-height: 1.35; }
.official-report-page { position: fixed; top: -50mm; left: -13mm; width: 210mm; height: 297mm; z-index: -1000; }
.official-report-header { position: fixed; top: -{{ $officialTopMargin }}mm; left: -13mm; width: {{ $officialPageWidth }}mm; height: auto; z-index: -1000; }
.official-report-footer { position: fixed; bottom: -{{ $officialBottomMargin }}mm; left: -13mm; width: {{ $officialPageWidth }}mm; height: auto; z-index: -1000; }
.screen-official-letterhead { display: none; }
.document-header { border-bottom: 1px solid #17305f; padding-bottom: 3mm; margin-bottom: 3mm; }
.document-header h2 { font-size: 14px; margin: 1mm 0; }
.metadata { margin: 3mm 0; }
.metadata p { overflow-wrap: break-word; }
th, td { font-size: {{ count($columns) > 7 ? 8 : 9 }}px; padding: 4px; overflow-wrap: break-word; }
th { color: #17305f; background: #edf1f6; }
thead { display: table-header-group; }
tr, .signatures { page-break-inside: avoid; }
.signatures { margin-top: 6mm; }
@if($format === 'html')
@media screen {
    body { box-sizing: border-box; width: {{ $officialPageWidth }}mm; min-height: {{ $officialPageHeight }}mm; margin: 0 auto; padding: 0 13mm 4mm; background: #fff; }
    .official-report-page, .official-report-header, .official-report-footer { display: none; }
    .screen-official-letterhead { display: block; width: {{ $officialPageWidth }}mm; height: auto; margin-left: -13mm; }
    .screen-official-letterhead.header { margin-bottom: 5mm; }
    .screen-official-letterhead.footer { margin-top: 8mm; }
}
@endif
@media print {
    body { width: auto; min-height: 0; margin: 0; padding: 0; }
    .screen-official-letterhead { display: none; }
    .official-report-page, .official-report-header, .official-report-footer { display: block; }
}
