@extends('portal.layout')

@section('content')
<div class="mb-4">
    <a href="{{ route('portal.track', ['reference' => $entry->reference]) }}" class="button secondary">&larr; Back to Request Tracker</a>
</div>

<section class="card">
    <div class="request-banner">{{ $testTitle }}</div>
    <h2>Guidance Testing Assessment</h2>
    <p class="muted">Requester: <strong>{{ strtoupper(trim($entry->first_name.' '.$entry->last_name)) }}</strong> | Reference: <strong>{{ $entry->reference }}</strong></p>

    @if(session('success'))
        <div class="notice" style="background:#dcfce7; border-color:#22c55e; color:#15803d; margin-bottom: 20px;">
            {{ session('success') }}
        </div>
    @endif

    @if($submission)
        <div style="background:#f8fafc; border:1px solid #cbd5e1; border-radius:8px; padding:20px; margin-bottom:24px;">
            <h3 style="margin-top:0; color:#0f172a;">Assessment Completed</h3>
            <p class="muted">Submitted on: {{ $submission->completed_at?->timezone('Asia/Manila')->format('M d, Y h:i A') }}</p>

            <div style="margin-top:16px;">
                <strong>Computed Results:</strong>
                <pre style="background:#fff; border:1px solid #e2e8f0; padding:12px; border-radius:6px; overflow-x:auto;">{{ json_encode($submission->interpretation, JSON_PRETTY_PRINT) }}</pre>
            </div>

            @if($submission->counselor_notes)
                <div style="margin-top:16px; background:#eff6ff; border-left:4px solid #3b82f6; padding:12px;">
                    <strong>Counselor Notes:</strong>
                    <p style="margin:4px 0 0 0;">{{ $submission->counselor_notes }}</p>
                </div>
            @endif
        </div>
    @endif

    <form method="POST" action="{{ route('guidance.test.submit', ['reference' => $entry->reference, 'test' => $test]) }}">
        @csrf

        <div style="margin-bottom: 20px; background:#f1f5f9; padding:12px 16px; border-radius:6px;">
            <strong>Instructions:</strong>
            @if($test === 'dass21')
                Please read each statement and select a score (0 to 3) which indicates how much the statement applied to you over the past week.<br>
                <em>0 = Did not apply to me at all (Never) | 1 = Applied to some degree (Sometimes) | 2 = Applied a considerable degree (Often) | 3 = Applied very much (Almost Always)</em>
            @elseif($test === 'phq9' || $test === 'gad7')
                Over the last 2 weeks, how often have you been bothered by the following problems?<br>
                <em>0 = Not at all | 1 = Several days | 2 = More than half the days | 3 = Nearly every day</em>
            @elseif($test === 'bfpi')
                Rate how accurately each statement describes you.<br>
                <em>1 = Disagree strongly | 2 = Disagree a little | 3 = Neither agree nor disagree | 4 = Agree a little | 5 = Agree strongly</em>
            @elseif($test === 'career')
                Rate how interested you are in each category.<br>
                <em>1 = Not interested | 2 = Slightly interested | 3 = Moderately interested | 4 = Very interested | 5 = Extremely interested</em>
            @endif
        </div>

        <div style="display:flex; flex-direction:column; gap:16px;">
            @foreach($questions as $index => $qText)
                <div style="padding:14px; background:#fff; border:1px solid #e2e8f0; border-radius:6px;">
                    <p style="margin:0 0 8px 0; font-weight:600;">{{ $index }}. {{ $qText }}</p>

                    <div style="display:flex; gap:16px; flex-wrap:wrap;">
                        @if($test === 'dass21')
                            @foreach([0 => '0 - Never', 1 => '1 - Sometimes', 2 => '2 - Often', 3 => '3 - Almost Always'] as $val => $lbl)
                                <label style="display:inline-flex; align-items:center; gap:6px; cursor:pointer;">
                                    <input type="radio" name="answers[{{ $index }}]" value="{{ $val }}" @checked(old("answers.$index", $submission?->answers[$index] ?? null) == $val) required>
                                    <span>{{ $lbl }}</span>
                                </label>
                            @endforeach
                        @elseif($test === 'phq9' || $test === 'gad7')
                            @foreach([0 => '0 - Not at all', 1 => '1 - Several days', 2 => '2 - More than half', 3 => '3 - Nearly every day'] as $val => $lbl)
                                <label style="display:inline-flex; align-items:center; gap:6px; cursor:pointer;">
                                    <input type="radio" name="answers[{{ $index }}]" value="{{ $val }}" @checked(old("answers.$index", $submission?->answers[$index] ?? null) == $val) required>
                                    <span>{{ $lbl }}</span>
                                </label>
                            @endforeach
                        @else
                            @for($val = 1; $val <= 5; $val++)
                                <label style="display:inline-flex; align-items:center; gap:6px; cursor:pointer;">
                                    <input type="radio" name="answers[{{ $index }}]" value="{{ $val }}" @checked(old("answers.$index", $submission?->answers[$index] ?? null) == $val) required>
                                    <span>Scale {{ $val }}</span>
                                </label>
                            @endfor
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <div style="margin-top:24px; text-align:right;">
            <button type="submit" class="button">{{ $submission ? 'UPDATE RESPONSES' : 'SUBMIT ASSESSMENT' }}</button>
        </div>
    </form>
</section>
@endsection
