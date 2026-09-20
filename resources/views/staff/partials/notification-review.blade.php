<dl class="row mb-3">
    <dt class="col-sm-4">Student</dt><dd class="col-sm-8">{{ trim($entry->first_name.' '.($entry->middle_name ? $entry->middle_name.' ' : '').$entry->last_name) }}</dd>
    <dt class="col-sm-4">Student ID</dt><dd class="col-sm-8">{{ $entry->student_number ?: 'Not provided' }}</dd>
    <dt class="col-sm-4">Module</dt><dd class="col-sm-8">{{ match($moduleType) { 'psychological' => 'Psychological Assessment', 'personality' => 'Personality Test', 'career' => 'Career Test', 'good-moral' => 'Good Moral', default => 'Exit Form' } }}</dd>
    <dt class="col-sm-4">Reference</dt><dd class="col-sm-8">{{ $entry->reference }}</dd>
    <dt class="col-sm-4">Program</dt><dd class="col-sm-8">{{ $entry->courseLabel() }}</dd>
    <dt class="col-sm-4">Purpose</dt><dd class="col-sm-8">{{ $entry->purpose ?: 'Not provided' }}</dd>
    <dt class="col-sm-4">Status</dt><dd class="col-sm-8">{{ ucfirst(str_replace('_', ' ', $entry->status)) }}</dd>
</dl>
@if($entry->proof_path)
    <button class="btn btn-outline-primary btn-sm mb-2" data-receipt="{{ in_array($moduleType, ['good-moral', 'exit-form'], true) ? route(auth()->user()->role.'.documents.proof', $entry) : route(auth()->user()->role.'.'.$moduleType.'.proof', $entry) }}">Review receipt</button>
@endif
@if(in_array($moduleType, ['good-moral', 'exit-form'], true))
    @include('staff.partials.document-actions')
@elseif($appointment)
    <a class="btn btn-outline-primary btn-sm" href="{{ route(auth()->user()->role.'.guidance-appointments.review', $appointment) }}" target="_blank" rel="noopener">Open assessment details</a>
@endif
