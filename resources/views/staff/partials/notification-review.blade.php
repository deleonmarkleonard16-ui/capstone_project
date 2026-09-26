<style>
    .notification-request-details { display: grid; grid-template-columns: minmax(130px, 34%) minmax(0, 1fr); gap: .55rem 1.5rem; }
    .notification-request-details dt, .notification-request-details dd { margin: 0; }
    .notification-request-details dd { overflow-wrap: anywhere; }
    .notification-review-actions { display: flex; align-items: stretch; flex-wrap: wrap; gap: .5rem; }
    .notification-review-actions > form { display: flex; margin: 0; }
    .notification-review-actions .btn { display: inline-flex; align-items: center; justify-content: center; gap: .35rem; min-height: 34px; }
    .notification-review-actions .btn i { margin-right: 0 !important; line-height: 1; }
    @media (max-width: 575.98px) {
        .notification-request-details { grid-template-columns: 1fr; gap: .15rem; }
        .notification-request-details dt { margin-top: .55rem; font-size: .8rem; text-transform: uppercase; }
        .notification-request-details dd { padding-bottom: .35rem; border-bottom: 1px solid var(--bs-border-color); }
        .notification-review-actions .btn { flex: 1 1 auto; }
    }
</style>

<dl class="notification-request-details mb-4">
    <dt>Student</dt><dd>{{ trim($entry->first_name.' '.($entry->middle_name ? $entry->middle_name.' ' : '').$entry->last_name) }}</dd>
    <dt>Student ID</dt><dd>{{ $entry->student_number ?: 'Not provided' }}</dd>
    <dt>Module</dt><dd>{{ match($moduleType) { 'psychological' => 'Psychological Assessment', 'personality' => 'Personality Test', 'career' => 'Career Test', 'good-moral' => 'Good Moral', default => 'Exit Form' } }}</dd>
    <dt>Reference</dt><dd>{{ $entry->reference }}</dd>
    <dt>Program</dt><dd>{{ $entry->courseLabel() }}</dd>
    <dt>Purpose</dt><dd>{{ $entry->purpose ?: 'Not provided' }}</dd>
    <dt>Status</dt><dd>{{ ucfirst(str_replace('_', ' ', $entry->status)) }}</dd>
</dl>
<div class="notification-review-actions" data-notification-review-actions>
@if($entry->proof_path)
    <button class="btn btn-outline-primary btn-sm" data-receipt="{{ in_array($moduleType, ['good-moral', 'exit-form'], true) ? route(auth()->user()->role.'.documents.proof', $entry) : route(auth()->user()->role.'.'.$moduleType.'.proof', $entry) }}">Review receipt</button>
@endif
@if(in_array($moduleType, ['good-moral', 'exit-form'], true))
    @include('staff.partials.document-actions')
@elseif($appointment)
    <a class="btn btn-outline-primary btn-sm" href="{{ route(auth()->user()->role.'.guidance-appointments.review', $appointment) }}" target="_blank" rel="noopener">Open assessment details</a>
@endif
</div>
