<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Guidance Archived Requests</title>
<style>body{font:10px 'DejaVu Sans',sans-serif;margin:12px;color:#111}table{border-collapse:collapse;width:100%;table-layout:fixed}th,td{border:1px solid #999;padding:6px;text-align:left;overflow-wrap:anywhere}thead{display:table-header-group}tr{page-break-inside:avoid}@page{size:A4 landscape;margin:12mm}</style></head>
<body>
<h1>Guidance Archived Requests</h1><p>Pangasinan State University — San Carlos Campus<br>Generated {{ now()->timezone('Asia/Manila')->format('M d, Y g:i A') }} (Philippine time). Confidential — authorized staff only.</p>
<table><thead><tr><th>Reference</th><th>Student / ID</th><th>Assessments</th><th>Status</th><th>Archived (Philippine time)</th></tr></thead><tbody>
@forelse($appointments as $entry)<tr><td>{{ $entry->serviceRequest?->reference ?? $entry->request_code }}</td><td>{{ $entry->applicant->full_name }}<br>{{ $entry->serviceRequest?->student_number }}</td><td>{{ $entry->testLabel() }}</td><td>{{ $entry->status }}</td><td>{{ $entry->archived_at?->timezone('Asia/Manila')->format('Y-m-d H:i') }}</td></tr>@empty<tr><td colspan="5">No archived requests match these filters.</td></tr>@endforelse
</tbody></table></body></html>
