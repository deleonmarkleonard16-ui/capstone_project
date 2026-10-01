@extends('portal.layout')
@section('content')
@php
    $selectedService = old('service', request('service', 'testing'));
    $selectedService = array_key_exists($selectedService, \App\Models\ServiceRequest::SERVICES) ? $selectedService : 'testing';
    $trackingReference = session('tracking_reference', session('request_reference', session('guidance_request_code', old('reference', $recentRequest?->reference))));
    $hasActiveQuery = request()->has('service') || request()->has('track') || request()->has('reference');
    $hasSessionData = session('portal_notice') || session('request_reference') || session('tracking_reference') || session('guidance_request_code') || $errors->any();
    $showPanelOnLoad = $hasActiveQuery || $hasSessionData;
    $initialView = (request()->has('track') || session('request_reference') || session('tracking_reference') || session('guidance_request_code')) ? 'track' : 'request';
@endphp

@if(session('portal_notice'))
@php($__pn = session('portal_notice'))
@php($__pnWarn = str_contains($__pn, 'expired') || str_contains($__pn, 'today'))
<div class="notice{{ $__pnWarn ? ' notice-warn' : '' }}" role="alert" style="{{ $__pnWarn ? 'background:#fff8e1;border-left:4px solid #f0a500;' : '' }}">
    @if($__pnWarn)⚠️ @endif{{ $__pn }}
</div>
@endif

{{-- ══ AVAILABLE SERVICES CARDS (LANDING SECTION) ══ --}}
<section class="card" id="available-services-card">
    <h2>Available services</h2>
    <div class="services">
        @foreach(\App\Models\ServiceRequest::SERVICES as $key => $label)
        <a class="service {{ $showPanelOnLoad && $selectedService === $key ? 'selected' : '' }}"
           href="javascript:void(0)"
           onclick="selectPortalService('{{ $key }}')"
           id="service-card-{{ $key }}"
           data-service="{{ $key }}">
            <span class="service-icon {{ $key }}">{{ ['testing'=>'T','good-moral'=>'G','exit-form'=>'E'][$key] }}</span>
            <strong>{{ $label }}</strong>
            <span class="muted">{{ ['testing' => 'Psychological, personality, and career testing.', 'good-moral' => 'Request a certificate of good moral character.', 'exit-form' => 'Request exit clearance processing.'][$key] }}</span>
        </a>
        @endforeach
    </div>
</section>

{{-- ══ DYNAMIC EXPANDED SERVICE & TRACK CONTAINER ══ --}}
<section class="card" id="service-panel" style="{{ $showPanelOnLoad ? '' : 'display: none;' }}">
    {{-- Dynamic Service Banner --}}
    <div class="request-banner" id="request-banner">
        {{ ['testing' => 'Psychological, Personality, and Career Test Request', 'good-moral' => 'Good Moral Certificate Request', 'exit-form' => 'Exit Form Request'][$selectedService] }}
    </div>

    {{-- Dynamic View Switcher Tabs --}}
    <div class="request-tabs" id="portal-view-tabs">
        <button type="button" class="button {{ $initialView === 'request' ? '' : 'secondary' }}" id="tab-btn-request" onclick="switchPortalView('request')">
            Make Appointment / Request
        </button>
        <button type="button" class="button {{ $initialView === 'track' ? '' : 'secondary' }}" id="tab-btn-track" onclick="switchPortalView('track')">
            Track Existing Request
        </button>
    </div>

    {{-- ── TAB VIEW 1: SUBMIT A REQUEST FORM ── --}}
    <div id="view-request" style="{{ $initialView === 'request' ? '' : 'display: none;' }}">
        <h2>Submit a request</h2>
        <p class="muted">Complete the required fields. Middle name is optional.</p>

        <form id="request-form" method="POST" action="{{ route('portal.store') }}">
            @csrf
            <input type="hidden" name="service" id="service" value="{{ $selectedService }}">
            
            <div class="grid">
                @foreach(['first_name'=>'First name','middle_name'=>'Middle name (optional)','last_name'=>'Last name'] as $field=>$label)
                <div>
                    <label for="{{ $field }}">{{ $label }}</label>
                    <input id="{{ $field }}" name="{{ $field }}" type="text" value="{{ old($field) }}" maxlength="100" @required($field !== 'middle_name')>
                </div>
                @endforeach

                <div>
                    <label for="student_status">Student status</label>
                    <select id="student_status" name="student_status" onchange="handleStudentStatusToggle()" required>
                        <option value="Currently Enrolled" @selected(!in_array(old('student_status'), ['alumni', 'Alumni'], true))>Currently enrolled</option>
                        <option value="Alumni" @selected(in_array(old('student_status'), ['alumni', 'Alumni'], true))>Alumni</option>
                    </select>
                </div>

                <div id="student-identity-group">
                    <label for="student_identifier" id="student-id-label">{{ old('student_status') === 'alumni' || old('student_status') === 'Alumni' ? 'Year Graduated' : 'Student ID number' }}</label>
                    <input id="student_identifier" 
                           name="{{ old('student_status') === 'alumni' || old('student_status') === 'Alumni' ? 'year_graduated' : 'student_id' }}" 
                           type="text" 
                           value="{{ old('student_id', old('student_number', old('year_graduated'))) }}" 
                           placeholder="{{ old('student_status') === 'alumni' || old('student_status') === 'Alumni' ? 'e.g., 2023' : '##-SC-####' }}" 
                           maxlength="{{ old('student_status') === 'alumni' || old('student_status') === 'Alumni' ? '4' : '50' }}" 
                           @if(old('student_status') === 'alumni' || old('student_status') === 'Alumni') pattern="[0-9]{4}" inputmode="numeric" @endif 
                           required>
                    <small id="student-id-help" class="muted">{{ old('student_status') === 'alumni' || old('student_status') === 'Alumni' ? 'Enter your graduation year (e.g., 2023).' : 'Required for currently enrolled students.' }}</small>
                </div>

                <div>
                    <label for="course">Course / program</label>
                    <select id="course" name="course" required>
                        <option value="">Select a program</option>
                        @foreach(\App\Support\CourseCatalog::activeOptions() as $code=>$title)
                            <option value="{{ $code }}" @selected(old('course') === $code)>{{ $title }} ({{ $code }})</option>
                        @endforeach
                    </select>
                </div>

                <div class="full" id="test-fields">
                    <label for="requested-test">Requested testing</label>
                    <select id="requested-test" name="tests[]" required>
                        @foreach(['psychological'=>'Psychological Assessment','personality'=>'Personality Test','career'=>'Career Test'] as $key=>$label)
                            <option value="{{ $key }}" @selected(in_array($key, (array) old('tests', ['psychological'])))>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div id="copy-fields">
                    <label for="copies">Number of copies</label>
                    <input id="copies" type="number" name="copies" min="1" max="10" value="{{ old('copies', 1) }}">
                </div>

                <div class="full" id="testing-reason">
                    <label for="reason">Reason for request</label>
                    <select id="reason" name="reason">
                        <option value="">Select a reason</option>
                        @foreach(['OJT'=>'OJT','FIELD STUDY'=>'Field Study','Others'=>'Others'] as $value=>$label)
                            <option value="{{ $value }}" @selected(old('reason') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="full" id="other-reason-fields">
                    <label for="other_reason">Please specify your reason</label>
                    <input id="other_reason" name="other_reason" maxlength="2000" value="{{ old('other_reason') }}" placeholder="Enter your reason for this request">
                </div>

                <label class="full">
                    <input type="checkbox" name="consent" value="1" required @checked(old('consent'))>
                    I confirm these details are correct and agree that the guidance office may use them to process this request.
                </label>

                <div class="full" style="text-align:right">
                    <button type="button" id="review-button">SUBMIT</button>
                </div>
            </div>

            {{-- Confirmation Modal Dialog (Inside Form) --}}
            <dialog id="review-panel" class="review-dialog" aria-labelledby="review-title" aria-describedby="review-description">
                <div class="review-heading">
                    <h2 id="review-title">Confirm Guidance Testing Request</h2>
                    <button type="button" id="close-review" class="review-close" aria-label="Close confirmation">&times;</button>
                </div>
                <p id="review-description" class="muted">Please review the information below before submitting.</p>
                <dl id="review-details" class="review-grid"></dl>
                <div class="review-actions">
                    <button type="button" id="edit-request" class="secondary">EDIT INPUTS</button>
                    <button type="button" id="confirm-submit">SUBMIT</button>
                </div>
            </dialog>
        </form>
    </div>

    {{-- ── TAB VIEW 2: TRACK EXISTING REQUEST ── --}}
    <div id="view-track" style="{{ $initialView === 'track' ? '' : 'display: none;' }}">
        <h2>Track an existing request</h2>
        @if(session('request_reference'))
            <div class="notice" role="status">
                <strong>Your request has been submitted.</strong><br>
                Save your tracking reference: <strong>{{ session('request_reference') }}</strong><br>
                Your details, printable stub, next steps, and receipt upload are below.
            </div>
        @endif
        <p class="muted">Enter your private tracking reference shown after submission (e.g., G-8A2F).</p>
        
        <form id="guidance-track" method="POST" action="{{ route('api.track-request') }}" data-auto-track="{{ session('tracking_reference') || session('guidance_request_code') ? 'true' : 'false' }}">
            @csrf
            <div class="track-form-row">
                <div class="track-input-group">
                    <label for="reference">Tracking reference</label>
                    <input id="reference" name="reference" value="{{ $trackingReference }}" required autocomplete="off" placeholder="Enter tracking reference (e.g. G-8A2F)">
                </div>
                <button type="submit" class="track-submit-btn">Track request</button>
            </div>
        </form>

        <div id="tracking-result" role="status" aria-live="polite"></div>
        <div id="tracking-passes">
            @if($recentRequest && $recentRequest->reference === $trackingReference)
                @include('portal.stub', ['entry' => $recentRequest, 'tracking' => true])
            @endif
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script src="{{ asset('js/guidance-tracking.js') }}" defer></script>
<script>
const service = document.getElementById('service');
const statusSelect = document.getElementById('student_status');
const idLabel = document.getElementById('student-id-label');
const idInput = document.getElementById('student_identifier');
const idHelp = document.getElementById('student-id-help');
const servicePanel = document.getElementById('service-panel');
const requestBanner = document.getElementById('request-banner');

const serviceBanners = {
    'testing': 'Psychological, Personality, and Career Test Request',
    'good-moral': 'Good Moral Certificate Request',
    'exit-form': 'Exit Form Request'
};

function selectPortalService(key) {
    if (!service) return;
    service.value = key;

    // Update selected visual state on cards
    document.querySelectorAll('.services .service').forEach(el => {
        if (el.dataset.service === key) {
            el.classList.add('selected');
            el.setAttribute('aria-current', 'true');
        } else {
            el.classList.remove('selected');
            el.removeAttribute('aria-current');
        }
    });

    // Update body theme accent
    document.body.dataset.service = key;

    // Update banner
    if (requestBanner && serviceBanners[key]) {
        requestBanner.textContent = serviceBanners[key];
    }

    // Show container
    if (servicePanel) {
        servicePanel.style.display = '';
    }

    // Default to Make Appointment / Request view on service selection
    switchPortalView('request');

    // Update fields visibility
    updateFields();
    requirements();

    // Smooth scroll down to container
    servicePanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function switchPortalView(viewMode) {
    const viewRequest = document.getElementById('view-request');
    const viewTrack = document.getElementById('view-track');
    const btnRequest = document.getElementById('tab-btn-request');
    const btnTrack = document.getElementById('tab-btn-track');

    if (viewMode === 'track') {
        if (viewRequest) viewRequest.style.display = 'none';
        if (viewTrack) viewTrack.style.display = '';
        if (btnRequest) btnRequest.className = 'button secondary';
        if (btnTrack) btnTrack.className = 'button';
    } else {
        if (viewTrack) viewTrack.style.display = 'none';
        if (viewRequest) viewRequest.style.display = '';
        if (btnRequest) btnRequest.className = 'button';
        if (btnTrack) btnTrack.className = 'button secondary';
    }
}

function handleStudentStatusToggle() {
    if (!statusSelect || !idLabel || !idInput || !idHelp) return;
    const isAlumni = statusSelect.value === 'alumni' || statusSelect.value === 'Alumni';
    if (isAlumni) {
        idLabel.textContent = 'Year Graduated';
        idInput.name = 'year_graduated';
        idInput.type = 'text';
        idInput.placeholder = 'e.g., 2023';
        idInput.setAttribute('maxlength', '4');
        idInput.setAttribute('pattern', '[0-9]{4}');
        idInput.setAttribute('inputmode', 'numeric');
        idInput.title = 'Please enter a 4-digit graduation year (e.g., 2023)';
        idHelp.textContent = 'Enter your graduation year (e.g., 2023).';
    } else {
        idLabel.textContent = 'Student ID number';
        idInput.name = 'student_id';
        idInput.type = 'text';
        idInput.placeholder = '##-SC-####';
        idInput.setAttribute('maxlength', '50');
        idInput.removeAttribute('pattern');
        idInput.removeAttribute('inputmode');
        idInput.removeAttribute('title');
        idHelp.textContent = 'Required for currently enrolled students.';
    }
    idInput.required = true;
}

function updateFields(){
    if (!service) return;
    const isTesting = service.value === 'testing';
    const isDoc = ['good-moral', 'exit-form'].includes(service.value);

    const testFields = document.getElementById('test-fields');
    const copyFields = document.getElementById('copy-fields');

    if (testFields) {
        testFields.hidden = !isTesting;
        testFields.querySelectorAll('input, select').forEach(input => input.disabled = !isTesting);
    }
    if (copyFields) {
        copyFields.hidden = !isDoc;
        copyFields.querySelectorAll('input, select').forEach(input => input.disabled = !isDoc);
    }
}

const form = document.getElementById('request-form');
const review = document.getElementById('review-panel');

function requirements(){
    handleStudentStatusToggle();
    if (!service) return;
    const isTesting = service.value === 'testing';
    const reasonSelect = document.getElementById('reason');
    const isOther = reasonSelect && reasonSelect.value === 'Others';

    const testReason = document.getElementById('testing-reason');
    const otherFields = document.getElementById('other-reason-fields');

    if (testReason) {
        testReason.hidden = false;
        const field = document.getElementById('reason');
        if (field) { field.disabled = false; field.required = true; }
    }
    if (otherFields) {
        otherFields.hidden = !isOther;
        const field = document.getElementById('other_reason');
        if (field) { field.disabled = !isOther; field.required = isOther; }
    }
}

if (form && review) {
    form.addEventListener('change', () => { updateFields(); requirements(); if (review.open) review.close(); });
    form.addEventListener('input', () => { if (review.open) review.close(); });
}

const reviewButton = document.getElementById('review-button');
if (reviewButton && form && review) {
    reviewButton.addEventListener('click', () => {
        if (!form.reportValidity()) return;
        if (service.value === 'testing' && !form.querySelector('[name="tests[]"]').value) {
            alert('Select at least one test.');
            return;
        }
        const details = document.getElementById('review-details');
        details.replaceChildren();
        const data = new FormData(form);
        const testing = service.value === 'testing';
        document.getElementById('review-title').textContent = testing ? 'Confirm Guidance Testing Request' : (service.value === 'good-moral' ? 'Confirm Good Moral Request' : 'Confirm Exit Form Request');
        
        function summary(label, value, full = false) {
            const card = document.createElement('div');
            card.className = 'review-summary' + (full ? ' full' : '');
            const dt = document.createElement('dt');
            dt.textContent = label;
            const dd = document.createElement('dd');
            dd.textContent = value || 'Not provided';
            card.append(dt, dd);
            details.append(card);
        }

        summary('Student Name', ['first_name', 'middle_name', 'last_name'].map(key => (data.get(key) || '').trim()).filter(Boolean).join(' '));
        const isAlumni = data.get('student_status') === 'alumni' || data.get('student_status') === 'Alumni';
        if (isAlumni) {
            summary('Year Graduated', data.get('year_graduated') || data.get('student_id') || data.get('student_number'));
        } else {
            summary('Student ID Number', data.get('student_id') || data.get('student_number'));
        }
        summary('Student Status', isAlumni ? 'Alumni' : 'Currently Enrolled');
        summary('Course', data.get('course'));
        if (testing) {
            const labels = { psychological: 'Psychological Assessment', personality: 'Personality Test', career: 'Career Test' };
            summary('Requested Testing', data.getAll('tests[]').map(test => labels[test]).join(', '), true);
        } else {
            summary('Number of Copies', data.get('copies'));
        }
        summary('Reason for Request', data.get('reason') === 'Others' ? data.get('other_reason') : (data.get('reason') === 'FIELD STUDY' ? 'Field Study' : data.get('reason')), true);
        
        review.removeAttribute('hidden');
        if (!review.open) review.showModal();
        document.body.classList.add('review-open');
    });
}

const confirmSubmitBtn = document.getElementById('confirm-submit');
const reviewBtn = document.getElementById('review-button');

function disableSubmit() {
    if (confirmSubmitBtn) {
        confirmSubmitBtn.disabled = true;
        confirmSubmitBtn.textContent = 'Submitting...';
    }
    if (reviewBtn) {
        reviewBtn.disabled = true;
    }
}

function enableSubmit() {
    if (confirmSubmitBtn) {
        confirmSubmitBtn.disabled = false;
        confirmSubmitBtn.textContent = 'SUBMIT';
    }
    if (reviewBtn) {
        reviewBtn.disabled = false;
    }
}

if (confirmSubmitBtn && form) {
    confirmSubmitBtn.addEventListener('click', (e) => {
        e.preventDefault();
        disableSubmit();
        form.submit();
    });
}

const editRequestBtn = document.getElementById('edit-request');
if (editRequestBtn && review) {
    editRequestBtn.addEventListener('click', () => {
        enableSubmit();
        review.close();
        document.getElementById('first_name')?.focus();
    });
}

if (form) {
    form.addEventListener('submit', event => {
        if (review && !review.open) {
            event.preventDefault();
            document.getElementById('review-button')?.click();
            return;
        }
        disableSubmit();
    });
}

window.addEventListener('pageshow', () => {
    enableSubmit();
});

updateFields();
requirements();

const closeReviewBtn = document.getElementById('close-review');
if (closeReviewBtn && review) {
    closeReviewBtn.addEventListener('click', () => {
        enableSubmit();
        review.close();
    });
}

if (review) {
    review.addEventListener('close', () => {
        enableSubmit();
        document.body.classList.remove('review-open');
    });
}

// ── Auto-uppercase all text inputs ─────────────────────────
const uppercaseFields = ['first_name', 'middle_name', 'last_name', 'other_reason'];
uppercaseFields.forEach(id => {
    const el = document.getElementById(id);
    if (!el) return;
    el.addEventListener('input', () => {
        const pos = el.selectionStart;
        el.value = el.value.toUpperCase();
        el.setSelectionRange(pos, pos);
    });
});

// ── Student ID mask: ##-SC-#### ──────────────────────────
(function(){
    const sid = document.getElementById('student_identifier');
    if (!sid) return;
    const MID = '-SC-';
    const PREFIX_LEN = 2;
    const SUFFIX_LEN = 4;

    function applyMask(raw){
        const digits = raw.replace(/\D/g, '');
        const pre = digits.slice(0, PREFIX_LEN);
        const suf = digits.slice(PREFIX_LEN, PREFIX_LEN + SUFFIX_LEN);
        if (!pre) return '';
        if (pre.length < PREFIX_LEN) return pre;
        return pre + MID + suf;
    }

    sid.addEventListener('input', function(){
        if (statusSelect && (statusSelect.value === 'alumni' || statusSelect.value === 'Alumni')) return;
        const before = this.value;
        const caret  = this.selectionStart;
        let rawCount = 0;
        for(let i = 0; i < caret && i < before.length; i++){
            if(/\d/.test(before[i])) rawCount++;
        }
        const masked = applyMask(before);
        this.value = masked;
        let newCaret = 0, rc = 0;
        while(newCaret < masked.length && rc < rawCount){
            if(/\d/.test(masked[newCaret])) rc++;
            newCaret++;
        }
        this.setSelectionRange(newCaret, newCaret);
    });
})();

// ── Hash navigation support & Automatic post-submission tracking ──────
document.addEventListener('DOMContentLoaded', () => {
    const isTrackTarget = window.location.hash === '#track' || @json((bool) (session('request_reference') || session('tracking_reference') || session('guidance_request_code')));
    
    if (isTrackTarget) {
        if (servicePanel) servicePanel.style.display = '';
        switchPortalView('track');
        servicePanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
    } else if (window.location.hash === '#request') {
        if (servicePanel) servicePanel.style.display = '';
        switchPortalView('request');
        servicePanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    // ── Automatic Tracking Reference Copy & Toast ──────────
    const newRef = @json(session('tracking_reference') ?? session('request_reference') ?? session('copied_reference'));
    if (newRef) {
        const refInput = document.getElementById('reference');
        if (refInput) {
            refInput.value = newRef;
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(newRef).then(() => {
                if (window.showPortalToast) {
                    window.showPortalToast("Tracking Reference copied to clipboard!", 'success');
                }
            }).catch(() => {
                if (window.showPortalToast) {
                    window.showPortalToast("Tracking Reference copied to clipboard!", 'success');
                }
            });
        }
    }
});
</script>
@endpush
