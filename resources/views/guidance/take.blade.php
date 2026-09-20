@extends('guidance.layout')
@push('styles')
<style>
body { user-select:none; -webkit-user-select:none; }
#assessment-lock { position:fixed;inset:0;z-index:2000;background:#000;display:flex;align-items:center;justify-content:center;padding:20px; }
#assessment-lock[hidden] { display:none; }
#security-blackout { position:fixed; inset:0; z-index:10000; background:#000; }
#security-blackout[hidden] { display:none; }
.assessment-security-lock #assessment-content { visibility:hidden; }
#assessment-lock .card { max-width:540px; }
@media print { body { display:none !important; } }
.timer-badge { font-family: monospace; letter-spacing: 1px; font-weight: bold; font-size: 1.25rem; }
.sticky-assessment-header { position: sticky; top: 0; z-index: 1020; background: #ffffff; border-bottom: 2px solid #e2e8f0; padding: 12px 16px; margin-bottom: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
.stepper-indicator { display: flex; gap: 8px; align-items: center; margin-bottom: 12px; }
.stepper-pill { flex: 1; height: 6px; background: #e2e8f0; border-radius: 3px; transition: background 0.3s; }
.stepper-pill.active { background: #2563eb; }
.stepper-pill.completed { background: #16a34a; }
.item-card { border: 1px solid #e2e8f0; border-radius: 8px; transition: border-color 0.2s; }
.item-card:hover { border-color: #94a3b8; }
.item-card.unanswered { border-color: #ef4444; background: #fef2f2; }
.option-label { cursor: pointer; transition: all 0.15s; }
.option-label:hover { background: #f1f5f9; border-color: #cbd5e1; }
input[type="radio"]:checked + span, .form-check-input:checked ~ .option-text { font-weight: bold; color: #2563eb; }
</style>
@endpush

@section('content')
<div id="security-blackout" hidden aria-hidden="true"></div>
<div id="security-warning-modal" class="modal fade" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="security-modal-title" style="z-index: 10500;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-danger shadow-lg">
            <div class="modal-header bg-danger text-white py-2">
                <h5 class="modal-title fw-bold" id="security-modal-title">SECURITY WARNING</h5>
            </div>
            <div class="modal-body p-4 text-center">
                <div class="mb-3">
                    <span id="security-modal-badge" class="badge text-bg-warning fs-6 px-3 py-2">Strike 1 of 3</span>
                </div>
                <p id="security-modal-message" class="fs-5 fw-semibold mb-3 text-danger"></p>
                <p class="text-muted small mb-0">Security violations are logged and dispatched immediately to the Proctor's Live Dashboard.</p>
            </div>
            <div class="modal-footer justify-content-center py-2">
                <button type="button" class="btn btn-danger px-4 fw-bold" id="security-modal-ack" data-bs-dismiss="modal">Acknowledge &amp; Resume Assessment</button>
            </div>
        </div>
    </div>
</div>
<div id="assessment-lock" role="dialog" aria-modal="true" aria-labelledby="lock-title">
    <div class="card shadow-lg"><div class="card-body p-4 text-center">
        <h1 class="h4 fw-bold mb-3" id="lock-title">Ready to start?</h1>
        <p class="text-muted">Record each answer in the assessment below. For paper-based tests, use the question booklet provided by the Guidance Office.</p>
        <div class="alert alert-warning text-start small">
            <strong>Lockdown Rules:</strong>
            <ul class="mb-0 ps-3 mt-1">
                <li>You must remain in <strong>Fullscreen Mode</strong> until final submission.</li>
                <li>Security incidents are recorded and reported to the proctor.</li>
                <li>Leaving fullscreen, switching apps, or capturing screenshots will trigger security strikes.</li>
                <li>Each section has a <strong>10-minute countdown</strong>. The next section starts automatically when time expires.</li>
            </ul>
        </div>
        <p id="lock-message" class="text-danger fw-semibold" role="alert"></p>
        <button class="btn btn-primary btn-lg w-100 mb-2" type="button" id="start-assessment">Start Assessment in Fullscreen Mode</button>
        <p class="small text-muted mb-0">Resume here to continue. Ask the proctor if you need to end your session.</p>
    </div></div>
</div>

<section id="assessment-content" inert>
    @php
        $testTypes = $appointment->testTypes();
        $totalSteps = count($sections);
        $remainingSeconds = $sessionState['remaining_seconds'] ?? 600;
    @endphp

    <div class="sticky-assessment-header d-flex flex-wrap justify-content-between align-items-center">
        <div>
            <h2 class="h5 mb-0 fw-bold">{{ $appointment->testLabel() }}</h2>
            <small class="text-muted">Digital Answer Sheet · Step <span id="current-step-label">1</span> of {{ $totalSteps }}</small>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span class="small text-muted d-none d-sm-inline">Time Remaining:</span>
            <span id="timer-display" class="badge text-bg-primary timer-badge px-3 py-2">10:00</span>
        </div>
    </div>

    <div class="stepper-indicator mb-3">
        @for($s = 1; $s <= $totalSteps; $s++)
            <div class="stepper-pill {{ $s === 1 ? 'active' : '' }}" id="step-pill-{{ $s }}"></div>
        @endfor
    </div>

    <form id="assessment-form" action="{{ route('guidance.submit', $token) }}" data-token="{{ $token }}" data-strike-threshold="{{ \App\Models\GuidanceSetting::valueOf('strike_threshold', '3') }}" data-strikes="{{ route('guidance.log-strike') }}" data-complete="{{ route('guidance.complete', $token) }}" data-start="{{ route('guidance.start', $token) }}" data-progress="{{ route('guidance.progress', $token) }}" data-state="{{ route('guidance.state', $token) }}" data-section="{{ $appointment->section_index }}" data-remaining="{{ $remainingSeconds }}" data-started="{{ $appointment->started_at ? 'true' : 'false' }}" novalidate>
        @csrf
        <fieldset id="answer-fields" disabled>
            @foreach($sections as $stepIndex => $section)
                @php
                    $stepNum = $stepIndex + 1;
                    $test = $section['test'];
                    $def = $definitions[$test] ?? $definition;
                    $isSingle = !$appointment->test_types;
                @endphp

                <div class="assessment-step" data-step="{{ $stepNum }}" data-test="{{ $test }}" @if($stepNum !== 1) hidden @endif>
                    <div class="card mb-4 bg-light border-0">
                        <div class="card-body p-3">
                            <h3 class="h5 mb-1 fw-bold text-primary">{{ $section['label'] }}</h3>
                            <p class="text-muted small mb-0">@if($test === 'career')Rate your interest in each activity from 1 (Not interested) to 5 (Extremely interested).@else Refer to your paper question booklet for {{ \App\Services\GuidanceTestScoringService::LABELS[$test] ?? $test }}. Record your answer for all {{ $def['items'] }} questions below.@endif</p>
                        </div>
                    </div>

                    <div class="row g-3">
                        @foreach($section['items'] as $item)
                            @php
                                $inputName = $isSingle ? "answers[{$item}]" : "answers[{$test}][{$item}]";
                                $fieldId = "item-{$test}-{$item}";
                            @endphp
                            <div class="col-md-6 col-lg-4">
                                <div class="card item-card p-3 h-100" id="card-{{ $test }}-{{ $item }}">
                                    <div class="fw-bold mb-2 text-secondary small">ITEM {{ $item }}</div>
                                    @if($test === 'career')<p>{{ $def['questions'][$item] }}</p>@endif
                                    <div class="d-flex flex-wrap gap-2">
                                        @for($choice = $def['min']; $choice <= $def['max']; $choice++)
                                            <label class="form-check-label border rounded px-3 py-2 option-label flex-fill text-center" for="{{ $fieldId }}-{{ $choice }}">
                                                <input class="form-check-input me-1" id="{{ $fieldId }}-{{ $choice }}" type="radio" name="{{ $inputName }}" value="{{ $choice }}" @checked(isset($sessionState['answers'][$test][$item]) && (int) $sessionState['answers'][$test][$item] === $choice) required>
                                                <span class="option-text">{{ $test === 'bfpi' ? number_format($choice, 2) : $choice }}@if($test === 'career') — {{ $def['choices'][$choice] }}@endif</span>
                                            </label>
                                        @endfor
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                        @if($stepNum < $totalSteps)
                            <button type="button" class="btn btn-primary next-step">Next Step: {{ $sections[$stepIndex + 1]['label'] }} →</button>
                        @else
                            <button type="submit" class="btn btn-success btn-lg" id="submit-assessment">Submit Final Answers</button>
                        @endif
                    </div>
                </div>
            @endforeach
        </fieldset>
    </form>
    <p id="submission-message" class="mt-3 text-center fw-semibold" role="alert"></p>
</section>

<section id="assessment-complete" hidden>
    <div class="alert alert-success p-4 shadow-sm text-center">
        <h2 class="h4 fw-bold">Assessment Completed</h2>
        <p class="mb-3">Your responses have been successfully recorded and processed for Guidance Counselor review. This QR pass is now permanently inactive.</p>
        <a class="btn btn-primary" href="/portal?service=good-moral#track">Return to Tracking Portal</a>
    </div>
</section>
@endsection

@push('scripts')
<script src="{{ asset('js/guidance-assessment.js') }}" defer></script>
@endpush
