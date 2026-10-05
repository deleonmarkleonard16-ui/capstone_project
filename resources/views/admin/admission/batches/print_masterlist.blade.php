<!doctype html>
<html lang="en"><head><meta charset="utf-8"><title>Batch Masterlist - {{ $batch->batch_name }}</title><style>
body{font-family:Arial,sans-serif;color:#111;margin:28px}.toolbar{text-align:center;margin-bottom:20px}.toolbar button{background:#0f3f97;color:#fff;border:0;border-radius:5px;padding:9px 15px;font-weight:bold;cursor:pointer}h1{font-size:20px;margin:0}h2{font-size:15px;margin:22px 0 6px;border-bottom:2px solid #0f3f97;padding-bottom:5px}p{margin:4px 0;color:#444}table{border-collapse:collapse;width:100%;font-size:11px}th,td{border:1px solid #888;padding:6px;text-align:left}th{background:#edf3ff}@media print{.toolbar{display:none}body{margin:12mm}.session{page-break-inside:avoid}}
</style></head><body>
<div class="toolbar"><button onclick="window.print()">Print Batch Masterlist</button></div>
<h1>Pangasinan State University — San Carlos Campus</h1><p><strong>PSU-CAT Batch Masterlist:</strong> {{ $batch->batch_name }}</p>
@foreach($sessions as $session)
<section class="session"><h2>{{ $session->session_name }} @if($session->start_time) · {{ $session->start_time->format('M d, Y h:i A') }} @endif</h2><p>Venue: {{ $session->room ?: ($batch->room ?: 'Not specified') }}</p>
<table><thead><tr><th>#</th><th>Application No.</th><th>Examinee</th><th>Program</th><th>Attendance / Submission</th></tr></thead><tbody>
@forelse($session->applicants as $applicant)<tr><td>{{ $loop->iteration }}</td><td>{{ $applicant->application_number }}</td><td>{{ $applicant->full_name }}</td><td>{{ \App\Support\CourseCatalog::label($applicant->course_choice) }}</td><td>{{ $applicant->submitted_at ? 'Submitted' : ($applicant->checked_in_at ? 'Checked in' : 'Pending') }}</td></tr>@empty<tr><td colspan="5">No applicants assigned.</td></tr>@endforelse
</tbody></table></section>@endforeach
</body></html>
