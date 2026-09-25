@extends('portal.layout')

@section('content')
<div class="mb-4">
    <a href="{{ route('portal.track', ['reference' => $entry->reference]) }}" class="button secondary">&larr; Back to Request Tracker</a>
</div>

<section class="card shadow-sm p-4 border-0">
    <div class="request-banner mb-3">{{ $testTitle }}</div>
    <h2 class="h4 fw-bold mb-1">Guidance Testing Assessment</h2>
    <p class="text-muted small mb-4">Requester: <strong>{{ strtoupper(trim($entry->first_name.' '.$entry->last_name)) }}</strong> | Reference: <code>{{ $entry->reference }}</code></p>

    @if(session('success'))
        <div class="alert alert-success d-flex align-items-center mb-4" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>
            <div>{{ session('success') }}</div>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <div>{{ session('error') }}</div>
        </div>
    @endif

    @if($submission)
        <div class="card mb-4 bg-light border-0 shadow-sm">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3 class="h5 fw-bold mb-0 text-success"><i class="bi bi-check2-circle me-1"></i> Assessment Completed</h3>
                    <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2">
                        Submitted: {{ $submission->completed_at?->timezone('Asia/Manila')->format('M d, Y h:i A') }}
                    </span>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <div class="p-3 bg-white rounded-3 border">
                            <h6 class="fw-bold text-secondary mb-2">Computed Scores</h6>
                            <ul class="list-unstyled mb-0 small">
                                @foreach($submission->scores ?? [] as $metric => $val)
                                    <li class="d-flex justify-content-between py-1 border-bottom">
                                        <span class="text-capitalize">{{ str_replace('_', ' ', $metric) }}:</span>
                                        <strong class="text-primary">{{ $val }}</strong>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="p-3 bg-white rounded-3 border">
                            <h6 class="fw-bold text-secondary mb-2">Severity &amp; Interpretation</h6>
                            <ul class="list-unstyled mb-0 small">
                                @foreach($submission->interpretation ?? [] as $dim => $res)
                                    @if(!in_array($dim, ['scoring_version', 'difficulty_rating', 'difficulty_rating_label']))
                                        <li class="d-flex justify-content-between py-1 border-bottom">
                                            <span class="text-capitalize">{{ str_replace('_', ' ', $dim) }}:</span>
                                            <strong class="badge bg-primary text-wrap">{{ $res }}</strong>
                                        </li>
                                    @endif
                                @endforeach
                                @if(isset($submission->interpretation['difficulty_rating_label']))
                                    <li class="d-flex justify-content-between py-1 border-bottom">
                                        <span>Impact on Daily Life:</span>
                                        <strong class="badge bg-warning text-dark">{{ $submission->interpretation['difficulty_rating_label'] }}</strong>
                                    </li>
                                @endif
                            </ul>
                        </div>
                    </div>
                </div>

                @if($submission->counselor_notes)
                    <div class="p-3 bg-primary-subtle border-start border-primary border-4 rounded-end">
                        <strong class="text-primary d-block mb-1"><i class="bi bi-chat-left-dots me-1"></i> Counselor Notes:</strong>
                        <p class="mb-0 text-dark small">{{ $submission->counselor_notes }}</p>
                    </div>
                @endif
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('guidance.test.submit', ['reference' => $entry->reference, 'test' => $test]) }}">
        @csrf

        <div class="alert alert-info border-0 shadow-sm mb-4">
            <h6 class="fw-bold mb-2"><i class="bi bi-info-circle me-1"></i> Rating Scale &amp; Instructions</h6>
            @if($test === 'dass21')
                <p class="mb-2 small">Please read each statement and select a rating (0 to 3) which indicates how much the statement applied to you over the <strong>past week</strong>.</p>
                <div class="d-flex flex-wrap gap-2 small text-muted">
                    <span class="badge bg-white text-dark border"><strong>0</strong> - Did not apply to me at all — NEVER</span>
                    <span class="badge bg-white text-dark border"><strong>1</strong> - Applied to some degree — SOMETIMES</span>
                    <span class="badge bg-white text-dark border"><strong>2</strong> - Applied a considerable degree — OFTEN</span>
                    <span class="badge bg-white text-dark border"><strong>3</strong> - Applied very much — ALMOST ALWAYS</span>
                </div>
            @elseif($test === 'phq9' || $test === 'gad7')
                <p class="mb-2 small">Over the <strong>last 2 weeks</strong>, how often have you been bothered by any of the following problems?</p>
                <div class="d-flex flex-wrap gap-2 small text-muted">
                    <span class="badge bg-white text-dark border"><strong>0</strong> - Not at all</span>
                    <span class="badge bg-white text-dark border"><strong>1</strong> - Several days</span>
                    <span class="badge bg-white text-dark border"><strong>2</strong> - More than half the days</span>
                    <span class="badge bg-white text-dark border"><strong>3</strong> - Nearly every day</span>
                </div>
            @elseif($test === 'bfpi')
                <p class="mb-1 small">Rate how accurately each statement describes you on a 1–5 scale (1: Disagree strongly to 5: Agree strongly).</p>
            @elseif($test === 'career')
                <p class="mb-1 small">Rate how interested you are in each category on a 1–5 scale (1: Not interested to 5: Extremely interested).</p>
            @endif
        </div>

        <div class="d-flex flex-column gap-3 mb-4">
            @foreach($questions as $index => $qText)
                <div class="card border rounded-3 p-3 bg-white shadow-sm">
                    <div class="d-flex align-items-start mb-2">
                        <span class="badge bg-primary me-2 mt-1">{{ $index }}</span>
                        <p class="fw-semibold mb-0 text-dark">{{ $qText }}</p>
                    </div>

                    @php
                        $selectedVal = old("answers.{$index}", $submission?->answers[$index] ?? null);
                    @endphp

                    @if($test === 'dass21')
                        <!-- DASS-21 Dynamic Choice Options (0 to 3) -->
                        <div class="btn-group w-100 mt-2" role="group" aria-label="Question {{ $index }} Choices">
                            <input type="radio" class="btn-check" name="answers[{{ $index }}]" id="dass_{{ $index }}_0" value="0" @checked((string)$selectedVal === '0') required>
                            <label class="btn btn-outline-secondary py-2" for="dass_{{ $index }}_0">0 - Never</label>

                            <input type="radio" class="btn-check" name="answers[{{ $index }}]" id="dass_{{ $index }}_1" value="1" @checked((string)$selectedVal === '1')>
                            <label class="btn btn-outline-secondary py-2" for="dass_{{ $index }}_1">1 - Sometimes</label>

                            <input type="radio" class="btn-check" name="answers[{{ $index }}]" id="dass_{{ $index }}_2" value="2" @checked((string)$selectedVal === '2')>
                            <label class="btn btn-outline-secondary py-2" for="dass_{{ $index }}_2">2 - Often</label>

                            <input type="radio" class="btn-check" name="answers[{{ $index }}]" id="dass_{{ $index }}_3" value="3" @checked((string)$selectedVal === '3')>
                            <label class="btn btn-outline-secondary py-2" for="dass_{{ $index }}_3">3 - Almost Always</label>
                        </div>
                    @elseif($test === 'phq9' || $test === 'gad7')
                        <!-- PHQ-9 & GAD-7 Dynamic Choice Options (0 to 3) -->
                        <div class="btn-group w-100 mt-2" role="group" aria-label="Question {{ $index }} Choices">
                            <input type="radio" class="btn-check" name="answers[{{ $index }}]" id="phq_{{ $index }}_0" value="0" @checked((string)$selectedVal === '0') required>
                            <label class="btn btn-outline-secondary py-2" for="phq_{{ $index }}_0">0 - Not at all</label>

                            <input type="radio" class="btn-check" name="answers[{{ $index }}]" id="phq_{{ $index }}_1" value="1" @checked((string)$selectedVal === '1')>
                            <label class="btn btn-outline-secondary py-2" for="phq_{{ $index }}_1">1 - Several days</label>

                            <input type="radio" class="btn-check" name="answers[{{ $index }}]" id="phq_{{ $index }}_2" value="2" @checked((string)$selectedVal === '2')>
                            <label class="btn btn-outline-secondary py-2" for="phq_{{ $index }}_2">2 - More than half the days</label>

                            <input type="radio" class="btn-check" name="answers[{{ $index }}]" id="phq_{{ $index }}_3" value="3" @checked((string)$selectedVal === '3')>
                            <label class="btn btn-outline-secondary py-2" for="phq_{{ $index }}_3">3 - Nearly every day</label>
                        </div>
                    @else
                        <!-- 5-Point Scale (BFPI & Career) -->
                        <div class="btn-group w-100 mt-2" role="group" aria-label="Question {{ $index }} Choices">
                            @for($val = 1; $val <= 5; $val++)
                                <input type="radio" class="btn-check" name="answers[{{ $index }}]" id="scale_{{ $index }}_{{ $val }}" value="{{ $val }}" @checked((string)$selectedVal === (string)$val) required>
                                <label class="btn btn-outline-secondary py-2" for="scale_{{ $index }}_{{ $val }}">{{ $val }}</label>
                            @endfor
                        </div>
                    @endif
                </div>
            @endforeach

            {{-- ── Optional PHQ-9 Item 10 / GAD-7 Functional Difficulty Question ── --}}
            @if(in_array($test, ['phq9', 'gad7'], true))
                @php
                    $diffVal = old('difficulty_rating', $submission?->interpretation['difficulty_rating'] ?? null);
                @endphp
                <div class="card border-primary border-2 rounded-3 p-3 bg-light shadow-sm mt-2">
                    <div class="mb-2">
                        <span class="badge bg-primary me-2">Global Difficulty</span>
                        <p class="fw-bold mb-1 text-dark d-inline">
                            If you checked off any problems, how difficult have these problems made it for you to do your work, take care of things at home, or get along with other people?
                        </p>
                        <div class="text-muted small">This functional rating provides context for counselor review (not calculated into symptom score).</div>
                    </div>

                    <div class="btn-group w-100 mt-2" role="group" aria-label="Functional Difficulty Rating">
                        <input type="radio" class="btn-check" name="difficulty_rating" id="diff_0" value="0" @checked((string)$diffVal === '0')>
                        <label class="btn btn-outline-secondary py-2" for="diff_0">Not difficult at all</label>

                        <input type="radio" class="btn-check" name="difficulty_rating" id="diff_1" value="1" @checked((string)$diffVal === '1')>
                        <label class="btn btn-outline-secondary py-2" for="diff_1">Somewhat difficult</label>

                        <input type="radio" class="btn-check" name="difficulty_rating" id="diff_2" value="2" @checked((string)$diffVal === '2')>
                        <label class="btn btn-outline-secondary py-2" for="diff_2">Very difficult</label>

                        <input type="radio" class="btn-check" name="difficulty_rating" id="diff_3" value="3" @checked((string)$diffVal === '3')>
                        <label class="btn btn-outline-secondary py-2" for="diff_3">Extremely difficult</label>
                    </div>
                </div>
            @endif
        </div>

        <div class="d-flex justify-content-end gap-2 pt-3 border-top">
            <button type="submit" class="btn btn-primary btn-lg px-4 fw-bold">
                <i class="bi bi-send-check me-1"></i> {{ $submission ? 'Update Responses' : 'Submit Assessment' }}
            </button>
        </div>
    </form>
</section>
@endsection

