@extends('layouts.app')
@section('content')
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h1 class="h3 mb-1">{{ $archived ? 'Archived Batches' : 'Bundled / Batch Queue' }}</h1>
        <p class="text-muted mb-0">Proctor synchronized batch assessments and manage class rosters.</p>
    </div>
    @unless($archived)
    <div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#create-bundled-modal">
            <i class="bi bi-people-fill me-1"></i> + Create Bundled Request
        </button>
    </div>
    @endunless
</div>

@include('guidance.partials.tabs')
@include('staff.partials.course-filter')

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

<p class="text-muted">Attendance refreshes every 5 seconds while no form or review is open.</p>
@include('guidance.security-feed')
<div data-module-live data-module="{{ $moduleKey ?? 'all' }}" data-view="{{ $archived ? 'archive' : 'batches' }}">
@forelse($batches as $batch)
@php($roster = $batch->appointments->concat($batch->makeupAppointments)->sortBy('guidance_appointment_id'))
@php($incidents = $roster->flatMap(fn ($person) => $person->securityIncidents->map(fn ($incident) => ['incident' => $incident, 'person' => $person]))->sortByDesc(fn ($row) => $row['incident']->created_at))
@php($totalCount = $roster->count())
@php($readyCount = $batch->appointments->where('attendance_status','Ready')->count())
@php($activeCount = $batch->appointments->where('status','In-Progress')->count())
@php($submittedCount = $roster->where('attendance_status','Completed')->count())
@php($absentCount = $batch->appointments->where('attendance_status','Absent')->count())
<details class="card p-3 mb-3" data-batch="{{ $batch->getKey() }}">
<summary><strong>{{ $batch->batch_name }}</strong> · {{ $batch->course }} · {{ $batch->test_type }} <span class="badge text-bg-primary">Ready: {{ $batch->appointments->where('attendance_status','Ready')->count() }}/{{ $batch->appointments->count() }}</span> <span class="badge text-bg-secondary">{{ $batch->status }}</span></summary>
<div class="d-flex flex-wrap gap-2 my-3">
<a class="btn btn-outline-primary" target="_blank" rel="noopener" href="{{ route(auth()->user()->role.'.guidance-batches.qr', $batch) }}"><i class="bi bi-qr-code me-1"></i>View / Project Batch QR</a>
<button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#batch-details-{{ $batch->getKey() }}">Review Batch Details</button>
@unless($archived)
@if($batch->status === 'Pending Registration')
<form method="post" action="{{ route(auth()->user()->role.'.guidance-batches.action', $batch) }}">@csrf<input type="hidden" name="action" value="start"><button class="btn btn-primary">Start Batch Assessment</button></form>
@elseif($batch->status === 'In-Progress')
<form method="post" action="{{ route(auth()->user()->role.'.guidance-batches.action', $batch) }}" onsubmit="return confirm('End this batch assessment now? Active tests will be submitted for counselor review, and students who have not started will be marked absent.')">@csrf<input type="hidden" name="action" value="end"><button class="btn btn-danger"><i class="bi bi-stop-circle me-1"></i>End Assessment</button></form>
@endif
<form method="post" action="{{ route(auth()->user()->role.'.guidance-batches.action', $batch) }}" onsubmit="return confirm('Archive this batch? Students who have not started will be marked absent.')">@csrf<input type="hidden" name="action" value="archive"><button class="btn btn-outline-secondary" @disabled($batch->status === 'In-Progress')>Archive Batch</button></form>
@endunless
</div>
<div class="row g-3 mb-4">
    @foreach([
        ['Total Applicants', $totalCount, ($roster->first()?->student_id_number ?: 'Roster').' – '.($roster->last()?->student_id_number ?: 'students'), 'primary'],
        ['Ready / Checked In', $readyCount, 'Waiting room queue', 'success'],
        ['Taking Test (Live)', $activeCount, 'Active test-takers', 'warning'],
        ['Submitted', $submittedCount, ($totalCount ? round($submittedCount / $totalCount * 100) : 0).'% completed', 'info'],
        ['Absent', $absentCount, 'Roster absentees', 'danger'],
        ['Prohibited Rules', $incidents->count(), $incidents->count() ? 'Security incident(s) logged' : 'Clean / No violations', $incidents->count() ? 'danger' : 'dark'],
    ] as [$label, $value, $hint, $tone])
    <div class="col-6 col-md-4 col-xl-2"><div class="card page-card shadow-xs border-start border-4 border-{{ $tone }} h-100"><div class="card-body p-3"><div class="text-muted small text-uppercase fw-semibold" style="font-size:11px">{{ $label }}</div><div class="h3 mb-0 fw-bold text-{{ $tone }}">{{ $value }}</div><div class="small text-muted mt-1 text-truncate">{{ $hint }}</div></div></div></div>
    @endforeach
</div>
<section class="card page-card shadow-sm mb-4 border-top border-3 {{ $incidents->count() ? 'border-danger' : 'border-primary' }}">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2"><i class="bi bi-shield-exclamation {{ $incidents->count() ? 'text-danger' : 'text-primary' }} fs-5"></i><h2 class="h6 mb-0 fw-bold">Live Prohibited Rules &amp; Security Violations Monitor</h2><span class="badge {{ $incidents->count() ? 'bg-danger' : 'bg-secondary' }}">{{ $incidents->count() }} Incident(s) Logged</span></div>
        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#batch-security-{{ $batch->getKey() }}" aria-expanded="{{ $incidents->count() ? 'true' : 'false' }}"><i class="bi bi-chevron-down"></i> Toggle Feed</button>
    </div>
    <div class="collapse {{ $incidents->count() ? 'show' : '' }}" id="batch-security-{{ $batch->getKey() }}"><div class="table-responsive" style="max-height:220px;overflow-y:auto"><table class="table table-sm table-hover align-middle mb-0" style="font-size:13px"><thead class="table-light sticky-top"><tr><th class="ps-3">Time</th><th>Student Name</th><th>Student ID</th><th>Prohibited Rule Violated</th><th>Strike Number</th><th class="pe-3 text-end">Status</th></tr></thead><tbody>
        @forelse($incidents as $row)
        @php($incident = $row['incident']) @php($person = $row['person'])
        <tr><td class="ps-3 text-muted font-monospace">{{ $incident->created_at?->timezone('Asia/Manila')->format('h:i:s A') ?: '—' }}</td><td class="fw-semibold">{{ $person->applicant?->full_name ?? 'Unknown Student' }}</td><td class="font-monospace">{{ $person->student_id_number ?: '—' }}</td><td><span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-1">{{ \App\Models\GuidanceSecurityIncident::TYPES[$incident->incident_type] ?? str($incident->incident_type)->headline() }}</span></td><td><span class="badge {{ $incident->strike_number >= 3 ? 'bg-danger' : 'bg-warning text-dark' }}">Strike {{ $incident->strike_number }}</span></td><td class="pe-3 text-end"><span class="text-muted small">{{ $incident->strike_number >= 3 ? 'Review required' : 'Logged' }}</span></td></tr>
        @empty
        <tr><td colspan="6" class="text-center text-muted py-3 small"><i class="bi bi-shield-check text-success me-1"></i>No prohibited rule violations logged for this batch yet.</td></tr>
        @endforelse
    </tbody></table></div></div>
</section>
<div class="table-responsive"><table class="table align-middle"><thead><tr><th>Student ID</th><th>Name</th><th>Reference</th><th>Section</th><th>Status</th><th>Actions</th></tr></thead><tbody>
@foreach($roster as $appointment)<tr><td>{{ $appointment->student_id_number }}</td><td>{{ $appointment->applicant->full_name }}</td><td>{{ $appointment->request_code }}</td><td>{{ $appointment->origin_section ?: $batch->year_section ?: '—' }}</td><td>{{ $appointment->source_batch_id ? 'Makeup Exam · ' : '' }}{{ $appointment->attendance_status === 'Ready' ? 'Ready in Session' : $appointment->attendance_status }}<br>@include('guidance.security-controls')</td><td><div class="d-flex flex-wrap gap-2">
<button class="btn btn-sm btn-outline-primary" data-guidance-review="{{ route(auth()->user()->role.'.guidance-appointments.review', $appointment) }}">Review Submission</button>
@if($batch->status === 'In-Progress' && $appointment->batch_id === $batch->getKey() && $appointment->attendance_status === 'Ready' && $appointment->status === 'Receipt Uploaded' && !$appointment->started_at)
<form method="post" action="{{ route(auth()->user()->role.'.guidance-batches.action', $batch) }}">@csrf<input type="hidden" name="action" value="start-student"><input type="hidden" name="appointment_id" value="{{ $appointment->getKey() }}"><button class="btn btn-sm btn-success"><i class="bi bi-play-circle me-1"></i>Start Assessment</button></form>
@endif
@if($appointment->status === 'Under review' && $appointment->response)
<button class="btn btn-sm btn-warning" data-guidance-review="{{ route(auth()->user()->role.'.guidance-appointments.review', $appointment) }}"><i class="bi bi-pencil-square me-1"></i>Review &amp; Evaluate</button>
@endif
@if($appointment->response)<a class="btn btn-sm btn-outline-success" href="{{ route(auth()->user()->role.'.guidance-appointments.show-results', $appointment) }}">View Scores</a>@endif
@if($appointment->is_archived && $appointment->response)
<a class="btn btn-sm btn-success" href="{{ route(auth()->user()->role.'.guidance-appointments.show-results', $appointment) }}">Review Result</a>
@if(in_array($appointment->test_category, ['psychological', 'personality'], true) || in_array($appointment->test_type, ['dass21', 'phq9', 'gad7', 'bfpi'], true))<a class="btn btn-sm btn-outline-success" href="{{ route(auth()->user()->role.'.guidance.report.preview', ['appointment' => $appointment->getKey(), 'auto_print' => 1]) }}" target="_blank" rel="noopener">Download Assessment</a>@elseif($appointment->test_category === 'career')<a class="btn btn-sm btn-outline-success" href="{{ route(auth()->user()->role.'.guidance-appointments.career-report', ['appointment' => $appointment->getKey(), 'format' => 'html', 'auto_print' => 1]) }}" target="_blank" rel="noopener">Download Assessment</a>@else<a class="btn btn-sm btn-outline-success" href="{{ route(auth()->user()->role.'.personality.certificate', ['appointment' => $appointment->getKey()]) }}" target="_blank" rel="noopener">Print Certificate</a>@endif
@endif
@if(!$appointment->started_at && in_array($appointment->attendance_status, ['Pending Scan','Ready']) && !$archived)<form method="post" action="{{ route(auth()->user()->role.'.guidance-batches.action', $batch) }}">@csrf<input type="hidden" name="action" value="absent"><input type="hidden" name="appointment_id" value="{{ $appointment->getKey() }}"><button class="btn btn-sm btn-outline-warning">Mark Absentee</button></form>@endif
@if($appointment->attendance_status === 'Absent')<form method="post" action="{{ route(auth()->user()->role.'.guidance-batches.action', $batch) }}">@csrf<input type="hidden" name="action" value="convert"><input type="hidden" name="appointment_id" value="{{ $appointment->getKey() }}"><button class="btn btn-sm btn-outline-secondary">Convert to Makeup Exam</button></form>@endif
</div></td></tr>@endforeach
</tbody></table></div>
</details>
<div class="modal fade" data-guidance-review-modal id="batch-details-{{ $batch->getKey() }}" tabindex="-1" aria-label="Batch details"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h2 class="modal-title h5">{{ $batch->batch_name }}</h2><button class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body"><dl><dt>Course</dt><dd>{{ $batch->course }}</dd><dt>Category / Reason</dt><dd>{{ $batch->test_type }} / {{ $batch->reason_for_request }}</dd><dt>Created</dt><dd>{{ $batch->created_at->timezone('Asia/Manila')->format('M d, Y g:i A') }}</dd><dt>Started</dt><dd>{{ $batch->started_at?->timezone('Asia/Manila')->format('M d, Y g:i A') ?? 'Not started' }}</dd><dt>Attendance summary</dt><dd>@foreach(['Pending Scan','Ready','Absent','Completed'] as $status){{ $status }}: {{ $batch->appointments->where('attendance_status',$status)->count() }}<br>@endforeach</dd></dl></div></div></div></div>
@empty<p>No batches in this view.</p>@endforelse
{{ $batches->links() }}
</div>
@include('guidance.batches.create-modal')
@include('guidance.receipt-modal')
@include('guidance.review-modal')
@endsection
@push('scripts')
<script src="{{ asset('js/guidance-queue.js') }}" defer></script>
@endpush
