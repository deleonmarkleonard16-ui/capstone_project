@if($archived)
<div class="d-flex gap-2 mb-3">
    <a class="btn btn-outline-secondary" href="{{ route(auth()->user()->role.'.guidance-appointments.export', array_merge($filters, ['format' => 'csv'])) }}"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Export CSV</a>
    <a class="btn btn-outline-secondary" href="{{ route(auth()->user()->role.'.guidance-appointments.export', array_merge($filters, ['format' => 'pdf'])) }}"><i class="bi bi-file-earmark-pdf me-1"></i>Export PDF</a>
</div>
@endif
<form id="archive-selection" method="post" action="{{ route(auth()->user()->role.'.guidance-appointments.archive-selected') }}">
    @csrf
    <input type="hidden" name="archive" value="{{ $archived ? '0' : '1' }}">
    <button class="btn btn-outline-primary mb-3"><i class="bi bi-archive me-1"></i>{{ $archived ? 'Restore selected' : 'Archive selected completed requests' }}</button>
</form>
<div class="table-responsive"><table class="table align-middle">
<thead><tr><th><input class="form-check-input" type="checkbox" data-select-all aria-label="Select all completed requests on this page"></th><th>Student / assessment</th><th>Receipt</th><th>Status / action</th><th>Submission</th></tr></thead>
<tbody>
@forelse($appointments as $appointment)
<tr data-appointment="{{ $appointment->getKey() }}">
    <td>@if($appointment->status === 'Completed')<input class="form-check-input" type="checkbox" name="ids[]" value="{{ $appointment->getKey() }}" form="archive-selection" aria-label="Select {{ $appointment->applicant->full_name }}">@endif</td>
    <td><span class="badge text-bg-primary mb-2">{{ $appointment->request_code }}</span><br><strong>{{ mb_strtoupper($appointment->applicant->full_name) }}</strong><div>{{ $appointment->testLabel() }}</div>
        @if($appointment->serviceRequest)<div class="small mt-2">Student ID: {{ mb_strtoupper($appointment->serviceRequest->student_number ?: 'Not provided') }} / {{ mb_strtoupper($appointment->serviceRequest->course) }}<br>Purpose: {{ $appointment->serviceRequest->purpose }}</div>@endif
    </td>
    <td>
        @if($appointment->hasReceipt())
            <button type="button" class="btn btn-outline-primary btn-sm" data-receipt="{{ route(auth()->user()->role.'.guidance-appointments.receipt', $appointment) }}" data-or-number="{{ $appointment->or_number ?: $appointment->serviceRequest?->or_number }}" data-or-date="{{ ($appointment->or_date ?: $appointment->serviceRequest?->or_date) ? \Illuminate\Support\Carbon::parse($appointment->or_date ?: $appointment->serviceRequest?->or_date)->format('M d, Y') : '' }}"><i class="bi bi-receipt me-1"></i>Show receipt</button>
            @if($appointment->or_number || $appointment->serviceRequest?->or_number)
                <div class="small text-muted mt-1">O.R. # <strong>{{ $appointment->or_number ?: $appointment->serviceRequest?->or_number }}</strong></div>
            @endif
            @if($appointment->or_date || $appointment->serviceRequest?->or_date)
                <div class="small text-muted">{{ \Illuminate\Support\Carbon::parse($appointment->or_date ?: $appointment->serviceRequest?->or_date)->format('M d, Y') }}</div>
            @endif
        @else<span class="text-muted"><i class="bi bi-hourglass-split me-1"></i>Awaiting receipt upload</span>@endif
    </td>
    <td><div data-status-badge><strong>{{ $appointment->status }}</strong></div>@include('guidance.security-controls')
        @if($appointment->appointment_at)<p class="small">Appointment: {{ $appointment->appointment_at->timezone('Asia/Manila')->format('M d, Y g:i A') }} (Philippine time)</p>@endif
        @if($appointment->status === 'Receipt Uploaded' && !$archived)
        <form method="post" action="{{ route(auth()->user()->role.'.guidance-appointments.verify', $appointment) }}">@csrf<button class="btn btn-primary btn-sm" @disabled(!$appointment->hasReceipt() || $appointment->awaitsScoringConfiguration())><i class="bi bi-qr-code me-1"></i>Verify &amp; Generate QR</button>@if($appointment->awaitsScoringConfiguration())<p class="small text-muted mt-2">Scoring configuration pending.</p>@endif</form>
        @endif
        @if($appointment->archived_at)<p class="small text-muted">Archived {{ $appointment->archived_at->timezone('Asia/Manila')->format('M d, Y g:i A') }}</p>@endif
    </td>
    <td><button type="button" class="btn btn-outline-primary btn-sm mb-2" data-guidance-review="{{ route(auth()->user()->role.'.guidance-appointments.review', $appointment) }}"><i class="bi bi-eye me-1"></i>Review Details</button><br>@if($appointment->response)<a class="btn btn-sm btn-success" href="{{ route(auth()->user()->role.'.guidance-appointments.show-results', $appointment) }}" data-guidance-review="{{ route(auth()->user()->role.'.guidance-appointments.review', $appointment) }}"><i class="bi bi-file-earmark-text me-1"></i>Review Student Submission</a>@else<span class="text-muted">Awaiting submission</span>@endif</td>
</tr>
@empty<tr><td colspan="5" class="text-muted text-center py-4">No requests match these filters.</td></tr>@endforelse
</tbody></table></div>
{{ $appointments->links('pagination::bootstrap-5') }}
