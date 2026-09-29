@extends('guidance.layout')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/answer-sheet.css') }}">
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
        <p class="text-muted">Digital Answer Sheet</p>
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
        $applicant = $appointment->applicant;
    @endphp

    {{-- ═══ 1. TOP HEADER SECTION & FLOW STEPPER ═══ --}}
    <div class="sticky-assessment-header">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
            <div>
                <h2 class="h5 mb-0 fw-bold text-primary">{{ $appointment->testLabel() }}</h2>
                <small class="text-muted">Digital Answer Sheet · Step <span id="current-step-label">1</span> of {{ $totalSteps }}</small>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="small text-muted d-none d-sm-inline">Time Remaining:</span>
                <span id="timer-display" class="badge text-bg-primary timer-badge px-3 py-2">10:00</span>
            </div>
        </div>

        {{-- Psychological Assessment Flow Stepper (Done / Current / Pending) --}}
        <div class="d-flex flex-wrap align-items-center gap-2 pt-2 border-top border-slate-100">
            <span class="small text-muted fw-semibold">Flow:</span>
            @foreach($sections as $sIdx => $sec)
                @php
                    $stepNum = $sIdx + 1;
                    $isActive = ($stepNum === 1);
                    $secLabel = strtoupper($sec['test']);
                @endphp
                <span class="badge {{ $isActive ? 'bg-primary text-white' : 'bg-light text-secondary border' }} px-2.5 py-1.5 stepper-flow-badge" id="flow-badge-{{ $stepNum }}">
                    {{ $secLabel }}
                </span>
                @if(!$loop->last)
                    <i class="bi bi-chevron-right text-muted small"></i>
                @endif
            @endforeach
        </div>
    </div>

    {{-- ═══ 2. RESPONDENT PROFILE METADATA BLOCK (2-COLUMN GRID) ═══ --}}
    <div class="card mb-3 border shadow-sm" style="border-radius: 12px; background: #ffffff;">
        <div class="card-body p-3">
            <div class="d-flex align-items-center gap-2 mb-2 pb-1 border-bottom">
                <i class="bi bi-person-badge text-primary"></i>
                <span class="fw-bold small text-dark text-uppercase">Respondent Information</span>
            </div>
            <div class="row g-2 small">
                <div class="col-12 col-md-6">
                    <span class="text-muted d-block" style="font-size: 0.725rem; text-transform: uppercase;">Respondent Name</span>
                    <strong class="text-dark">{{ $applicant ? strtoupper($applicant->full_name) : 'REGISTERED APPLICANT' }}</strong>
                </div>
                <div class="col-12 col-md-6">
                    <span class="text-muted d-block" style="font-size: 0.725rem; text-transform: uppercase;">Student ID / App Number</span>
                    <strong class="text-dark font-monospace">{{ $appointment->student_id_number ?? $appointment->serviceRequest?->student_number ?? $applicant?->application_number ?? 'N/A' }}</strong>
                </div>
                <div class="col-12 col-md-6">
                    <span class="text-muted d-block" style="font-size: 0.725rem; text-transform: uppercase;">O.R. # / Request Code</span>
                    <strong class="text-dark font-monospace">{{ $appointment->or_number ?: 'N/A' }}</strong>
                </div>
                <div class="col-12 col-md-6">
                    <span class="text-muted d-block" style="font-size: 0.725rem; text-transform: uppercase;">Course</span>
                    <strong class="text-dark">{{ $appointment->serviceRequest?->courseLabel() ?: ($appointment->origin_course ?? 'N/A') }}</strong>
                </div>
            </div>
        </div>
    </div>

    <div class="stepper-indicator mb-3">
        @for($s = 1; $s <= $totalSteps; $s++)
            <div class="stepper-pill {{ $s === 1 ? 'active' : '' }}" id="step-pill-{{ $s }}"></div>
        @endfor
    </div>

    <form id="assessment-form" action="{{ route('guidance.submit', $token) }}" data-token="{{ $token }}" data-strike-threshold="{{ \App\Models\GuidanceSetting::valueOf('strike_threshold', '3') }}" data-strikes="{{ route('guidance.log-strike') }}" data-complete="{{ route('guidance.complete', $token) }}" data-start="{{ route('guidance.start', $token) }}" data-progress="{{ route('guidance.progress', $token) }}" data-state="{{ route('guidance.state', $token) }}" data-section="{{ $appointment->section_index }}" data-remaining="{{ $remainingSeconds }}" data-started="{{ $appointment->started_at ? 'true' : 'false' }}" data-course-program="{{ $appointment->origin_course ?? $appointment->serviceRequest?->course ?? 'Not provided' }}" data-current-test-taking="{{ $appointment->categoryLabel() }}" novalidate>
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
                    <div class="answer-sheet-matrix" data-total-items="{{ count($section['items']) }}" style="--vertical-grid-rows: {{ count($section['items']) }}">
                        @foreach($section['items'] as $item)
                            @php
                                $inputName = $isSingle ? "answers[{$item}]" : "answers[{$test}][{$item}]";
                                $currentVal = old($isSingle ? "answers.$item" : "answers.$test.$item", $sessionState['answers'][$test][$item] ?? $sessionState['answers'][$item] ?? null);
                            @endphp
                            <x-answer-sheet-item :item="$item" :name="$inputName" :choices="range($def['min'], $def['max'])" :selected="$currentVal" :id-prefix="'item-'.$test" :card-id="'card-'.$test.'-'.$item" />
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
    <div class="card border-0 shadow-sm p-4 p-md-5 text-center mx-auto" style="max-width: 560px; border-radius: 16px;">
        <div class="mb-3">
            <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-success bg-opacity-10" style="width:64px;height:64px;">
                <i class="bi bi-check-circle-fill text-success" style="font-size:2rem;"></i>
            </span>
        </div>
        <h2 class="h4 fw-bold mb-2">Assessment completed</h2>
        <p class="text-muted mb-4">Assessment completed. Your saved responses have been recorded for Guidance Office review. Please wait for the printed result that will be provided by the Guidance Office.</p>
        <a class="btn btn-primary px-4" href="/portal?service=good-moral#track">Return to portal</a>
    </div>
</section>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.answer-sheet-matrix').forEach((grid) => {
        const sync = () => {
            const columns = window.innerWidth < 576 ? 1 : window.innerWidth < 992 ? 1 : window.innerWidth < 1400 ? 2 : 3;
            grid.style.setProperty('--vertical-grid-rows', Math.ceil(Number(grid.dataset.totalItems) / columns));
        };
        sync(); window.addEventListener('resize', sync);
    });
});
</script>
<script src="{{ asset('js/guidance-assessment.js') }}" defer></script>
@endpush
