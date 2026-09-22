@extends('layouts.app')
@section('content')
<div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
    <div>
        <h1 class="h3 mb-1">{{ $module['label'] ?? 'Psychological Assessment' }} {{ $mode === 'analytics' ? 'Request Analytics' : ($mode === 'archive' ? 'Request Archive' : 'Testing Requests') }}</h1>
        <p class="text-muted mb-0">{{ $module['description'] ?? 'Review requests, receipts, and appointment schedules.' }}</p>
    </div>
    @unless($mode === 'analytics' || $mode === 'archive')
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#create-bundled-modal">
            <i class="bi bi-people-fill me-1"></i> + Create Bundled Request
        </button>
    </div>
    @endunless
</div>

@include('guidance.partials.tabs')
@include('staff.partials.course-filter')

@if($mode === 'archive')
<div class="card page-card mb-4"><div class="card-body p-4">
    <h2 class="h5 section-title">Archived Batches</h2>
    @forelse($archivedBatches as $batch)
        <div class="border rounded p-3 mb-2"><strong>{{ $batch->batch_name }}</strong> Â· {{ $batch->course }} Â· {{ $batch->test_type }}
            <a class="btn btn-sm btn-outline-primary ms-2" href="{{ route(auth()->user()->role.'.'.$moduleKey.'.batches', ['archived' => 1]) }}">Review archived batches</a>
        </div>
    @empty <p class="text-muted mb-0">No archived batches for this test.</p> @endforelse
    {{ $archivedBatches->links() }}
</div></div>
<div class="card page-card mb-4"><div class="card-body p-4">
    <h2 class="h5 section-title">Archived Individual Assessments</h2>
    <div class="table-responsive"><table class="table"><thead><tr><th>Student</th><th>Reference</th><th>Assessment</th><th>Action</th></tr></thead><tbody>
    @forelse($archivedAppointments as $appointment)
        <tr><td>{{ $appointment->applicant?->full_name }}</td><td>{{ $appointment->request_code }}</td><td>{{ $appointment->testLabel() }}</td><td><a href="{{ route(auth()->user()->role.'.guidance-appointments.show-results', $appointment) }}">Review results</a></td></tr>
    @empty <tr><td colspan="4" class="text-muted">No archived individual assessments for this test.</td></tr> @endforelse
    </tbody></table></div>{{ $archivedAppointments->links() }}
</div></div>
@endif

<div class="row g-3 mb-4">
    @foreach($stats as $label => $value)
        <div class="col-6 col-xl-3">
            <div class="card stat-card h-100">
                <div class="card-body">
                    <p class="text-muted mb-2">{{ $label }}</p>
                    <div class="h2 mb-0">{{ $value }}</div>
                </div>
            </div>
        </div>
    @endforeach
</div>

@if($mode === 'analytics')
<div class="card page-card mb-4"><div class="card-body p-4"><h2 class="h5 section-title mb-3">Status Breakdown</h2><div class="row g-3">@foreach(\App\Models\GuidanceAppointment::STATUSES as $status)<div class="col-md-4"><div class="border rounded-3 p-3 bg-light"><div class="text-muted">{{ ucfirst(str_replace('_',' ',$status)) }}</div><strong class="fs-4">{{ $counts[$status] ?? 0 }}</strong></div></div>@endforeach</div></div></div>
<div class="card page-card"><div class="card-body p-4"><h2 class="h5 section-title">Completed Appointments</h2><p class="text-muted">Counts represent requests marked completed by the guidance office.</p><div class="row g-3">@foreach(['Male','Female','Prefer not to say'] as $gender)<div class="col-md-4"><div class="border rounded-3 p-3">{{ $gender }}<div class="fs-4 fw-bold">{{ $genderCounts[$gender] ?? 0 }}</div></div></div>@endforeach</div></div></div>
@else
<div class="card page-card"><div class="card-body p-4"><h2 class="h5 section-title">{{ $mode === 'archive' ? 'Archived Requests' : 'Request Queue' }}</h2><p class="text-muted small">{{ $mode === 'archive' ? 'Completed, declined, and cancelled requests are retained here.' : 'Review uploaded receipts and verify requests to issue assessment passes. Appointment time is set when the receipt is uploaded. Finished requests move to the archive.' }}</p>
<section data-live-queue><form method="GET" data-live-filters class="row g-2 mb-4"><div class="col-md-4"><label class="visually-hidden" for="search">Search requests</label><input class="form-control" id="search" name="q" value="{{ request('q') }}" placeholder="Search name, student ID or reference"></div><div class="col-md-3"><label class="visually-hidden" for="filter">Status</label><select class="form-select" name="status" id="filter"><option value="">All statuses</option>@foreach(\App\Models\GuidanceAppointment::STATUSES as $status)<option value="{{ $status }}" @selected(request('status')===$status)>{{ ucfirst(str_replace('_',' ',$status)) }}</option>@endforeach</select></div>@if($moduleKey !== 'psychological')<div class="col-md-3"><label class="visually-hidden" for="test-filter">Filter by assessment</label><select id="test-filter" class="form-select" name="test_type"><option value="">All assessments</option>@foreach($module['tests'] as $key=>$label)<option value="{{ $key }}" @selected(request('test_type')===$key)>{{ $label }}</option>@endforeach</select></div>@endif<div class="col-md-2"><button class="btn btn-primary">Search</button><a class="btn btn-link" href="{{ url()->current() }}">Clear</a></div></form>
<p data-live-status class="small text-muted" role="status">Live updates every 5 seconds.</p><div data-live-results><div class="table-responsive"><table class="table align-middle" @if($mode === 'queue') data-individual-queue data-module="{{ $moduleKey }}" @endif><thead><tr><th>Student / Request</th><th>Requested Testing</th><th>Status</th><th>Receipt</th><th>Schedule</th><th>Details</th></tr></thead><tbody>
@forelse($requests as $entry)
<tr data-request-id="{{ $entry->id }}"><td><span class="badge text-bg-primary mb-2">{{ $entry->reference }}</span><br><strong>{{ mb_strtoupper($entry->first_name . " " . $entry->last_name) }}</strong><div class="small text-muted">{{ mb_strtoupper($entry->student_number ?: "Alumni") }} Â· {{ mb_strtoupper($entry->course) }}</div><div class="small text-muted">{{ $entry->created_at->format('M d, Y') }}</div></td><td>{{ $module['label'] }}</td><td><span class="badge text-bg-{{ $entry->status === 'completed' ? 'success' : 'primary' }}">{{ ucfirst(str_replace('_',' ',$entry->status)) }}</span></td><td>@if($entry->proof_path)<button type="button" class="btn btn-link p-0" data-receipt="{{ route(auth()->user()->role.'.psychological.proof',$entry) }}" data-or-number="{{ $entry->or_number }}" data-or-date="{{ $entry->or_date ? \Illuminate\Support\Carbon::parse($entry->or_date)->format('M d, Y') : '' }}">Show receipt</button>@else<span class="text-muted">Not uploaded</span>@endif</td><td>@if($entry->scheduled_at){{ $entry->scheduled_at->timezone('Asia/Manila')->format('M d, Y g:i A') }}<div class="small">{{ $entry->venue }}</div>@else<span class="text-muted">Not scheduled</span>@endif</td><td><details data-review="{{ $entry->id }}"><summary class="text-primary">Review</summary><div class="py-3" style="min-width:260px"><p class="small"><strong>Purpose:</strong> {{ $entry->purpose }}</p>
@foreach($entry->guidanceAppointments as $appointment)
<button type="button" class="btn btn-outline-primary btn-sm mb-2" data-guidance-review="{{ route(auth()->user()->role.'.guidance-appointments.review', $appointment) }}">Review Details</button>
<p class="small">{{ $appointment->testLabel() }}: <strong>{{ $appointment->status }}</strong></p>@if($appointment->response)<a class="btn btn-sm btn-outline-success mb-2" href="{{ route(auth()->user()->role.'.guidance-appointments.show-results', $appointment) }}">Review Student Submission</a>@endif
@if($appointment->status === 'Receipt Uploaded')
<form method="post" action="{{ route(auth()->user()->role.'.guidance-appointments.verify', $appointment) }}">@csrf
<button class="btn btn-primary btn-sm">Verify &amp; Generate QR</button></form>
@endif
@endforeach
</div></details></td></tr>
@empty<tr><td colspan="6" class="text-center text-muted py-5">No {{ strtolower($module['short_label'] ?? 'psychological') }} requests {{ $mode === 'archive' ? 'in the archive' : 'waiting in the queue' }}.</td></tr>@endforelse
</tbody></table></div>{{ $requests->links('pagination::bootstrap-5') }}</div></section></div></div>
@endif
@include('guidance.batches.create-modal')
@include('guidance.receipt-modal')
@include('guidance.review-modal')
@endsection

@push('scripts')<script src="{{ asset('js/guidance-queue.js') }}" defer></script>@endpush
