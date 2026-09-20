<details class="mt-1" data-security-details-wrap="{{ $appointment->getKey() }}" @if(!$appointment->strike_count) hidden @endif>
    <summary class="d-inline-block" style="cursor:pointer" aria-label="Show security incidents for {{ $appointment->applicant?->full_name }}">
        <span class="badge text-bg-danger" data-security-count="{{ $appointment->getKey() }}">{{ $appointment->strike_count }} security strike(s) · Details</span>
    </summary>
    <ol class="small mt-2 mb-1 ps-3" data-security-details="{{ $appointment->getKey() }}">
        @foreach($appointment->securityIncidents->sortBy('id') as $incident)
            <li>Strike {{ $incident->strike_number }}: {{ \App\Models\GuidanceSecurityIncident::TYPES[$incident->incident_type] ?? $incident->incident_type }} · {{ $incident->created_at->timezone('Asia/Manila')->format('M d, Y g:i A') }}</li>
        @endforeach
    </ol>
</details>
@if($appointment->terminated_at)
<span class="badge text-bg-danger">Terminated - Violation</span>
@if($appointment->termination_reason)<div class="small text-danger fw-semibold">{{ $appointment->termination_reason }}</div>@endif
@elseif($appointment->status === 'In-Progress')
<details class="mt-2" data-security-termination>
    <summary class="text-danger">Force Submit / Terminate Exam</summary>
    <form method="post" action="{{ route(auth()->user()->role.'.guidance-appointments.terminate', $appointment) }}" class="mt-2">@csrf
        <label class="d-block">Reason for termination<input class="form-control form-control-sm" name="reason" required maxlength="500"></label>
        <p class="small">Ends this assessment immediately using the answers already saved.</p>
        <button class="btn btn-danger btn-sm">Confirm termination</button>
    </form>
</details>
@endif
