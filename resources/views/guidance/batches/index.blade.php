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
<details class="card p-3 mb-3" data-batch="{{ $batch->getKey() }}">
<summary><strong>{{ $batch->batch_name }}</strong> · {{ $batch->course }} · {{ $batch->test_type }} <span class="badge text-bg-primary">Ready: {{ $batch->appointments->where('attendance_status','Ready')->count() }}/{{ $batch->appointments->count() }}</span> <span class="badge text-bg-secondary">{{ $batch->status }}</span></summary>
<div class="d-flex flex-wrap gap-2 my-3">
<a class="btn btn-outline-primary" target="_blank" rel="noopener" href="{{ route(auth()->user()->role.'.guidance-batches.qr', $batch) }}">View/Print Batch QR</a>
<button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#batch-details-{{ $batch->getKey() }}">Review Batch Details</button>
@unless($archived)
@foreach(['start'=>'Start Batch Assessment','archive'=>'Archive Batch'] as $action=>$label)<form method="post" action="{{ route(auth()->user()->role.'.guidance-batches.action', $batch) }}">@csrf<input type="hidden" name="action" value="{{ $action }}"><button class="btn {{ $action === 'start' ? 'btn-primary' : 'btn-outline-secondary' }}" @disabled($action === 'start' && $batch->status !== 'Pending Registration')>{{ $label }}</button></form>@endforeach
@endunless
</div>
<div class="table-responsive"><table class="table align-middle"><thead><tr><th>Student ID</th><th>Name</th><th>Reference</th><th>Section</th><th>Status</th><th>Actions</th></tr></thead><tbody>
@foreach($roster as $appointment)<tr><td>{{ $appointment->student_id_number }}</td><td>{{ $appointment->applicant->full_name }}</td><td>{{ $appointment->request_code }}</td><td>{{ $appointment->origin_section ?: $batch->year_section ?: '—' }}</td><td>{{ $appointment->source_batch_id ? 'Makeup Exam · ' : '' }}{{ $appointment->attendance_status === 'Ready' ? 'Ready in Session' : $appointment->attendance_status }}<br>@include('guidance.security-controls')</td><td><div class="d-flex flex-wrap gap-2">
<button class="btn btn-sm btn-outline-primary" data-guidance-review="{{ route(auth()->user()->role.'.guidance-appointments.review', $appointment) }}">Review Submission</button>
@if($appointment->response)<a class="btn btn-sm btn-outline-success" href="{{ route(auth()->user()->role.'.guidance-appointments.show-results', $appointment) }}">View Scores</a>@endif
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
