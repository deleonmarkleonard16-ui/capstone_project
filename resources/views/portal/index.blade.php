@extends('portal.layout')
@section('content')
<p class="notice">Official fees: Good Moral Certificate, Personality Test, Psychological Assessment, and Career Test ? Php 60.00 each.</p>
@php($selectedService = old('service', request('service', 'testing')))
@php($selectedService = array_key_exists($selectedService, \App\Models\ServiceRequest::SERVICES) ? $selectedService : 'testing')
@if(session('portal_notice'))
@php($__pn = session('portal_notice'))
<div class="notice{{ str_contains($__pn, 'expired') ? ' notice-warn' : '' }}" role="status" style="{{ str_contains($__pn, 'expired') ? 'background:#fff8e1;border-left:4px solid #f0a500;' : '' }}">
    @if(str_contains($__pn, 'expired'))⚠️ @endif{{ $__pn }}
</div>
@endif
@php($trackingReference = session('tracking_reference', session('guidance_request_code', old('reference', $recentRequest?->reference))))
<section class="card"><h2>Available services</h2><div class="services">
@foreach(\App\Models\ServiceRequest::SERVICES as $key => $label)
<a class="service {{ $selectedService === $key ? 'selected' : '' }}" href="?service={{ $key }}#request" @if($selectedService === $key) aria-current="true" @endif><span class="service-icon {{ $key }}">{{ ['testing'=>'T','good-moral'=>'G','exit-form'=>'E'][$key] }}</span><strong>{{ $label }}</strong><span class="muted">{{ ['testing' => 'Psychological, personality, and career testing.', 'good-moral' => 'Request a certificate of good moral character.', 'exit-form' => 'Request exit clearance processing.'][$key] }}</span></a>
@endforeach
</div></section>
<section class="card" id="request"><div class="request-banner" id="request-banner">Psychological, Personality, and Career Test Request</div><div class="request-tabs"><a class="button" href="#request">Make Appointment / Request</a><a class="button secondary" href="#track">Track Existing Request</a></div><h2>Submit a request</h2><p class="muted">Complete the required fields. Middle name is optional.</p>
<form id="request-form" method="POST" action="{{ route('portal.store') }}">@csrf
<div class="grid">
@foreach(['first_name'=>'First name','middle_name'=>'Middle name (optional)','last_name'=>'Last name'] as $field=>$label)
<div><label for="{{ $field }}">{{ $label }}</label><input id="{{ $field }}" name="{{ $field }}" type="text" value="{{ old($field) }}" maxlength="100" @required($field !== 'middle_name')></div>
@endforeach
<input type="hidden" name="service" id="service" value="{{ $selectedService }}">
<div><label for="student_status">Student status</label><select id="student_status" name="student_status" required><option value="student" @selected(old('student_status') === 'student')>Currently enrolled</option><option value="alumni" @selected(old('student_status') === 'alumni')>Alumni</option></select></div>
@foreach(['student_number'=>'Student ID number'] as $field=>$label)
<div><label for="{{ $field }}">{{ $label }}</label><input id="{{ $field }}" name="{{ $field }}" type="{{ $field === 'email' ? 'email' : 'text' }}" value="{{ old($field) }}" maxlength="{{ ['first_name'=>100,'middle_name'=>100,'last_name'=>100,'student_number'=>50,'email'=>255,'contact_number'=>30,'course'=>150][$field] }}" @required(!in_array($field, ['middle_name', 'student_number']))>@if($field === 'student_number')<small id="student-id-help" class="muted">Required for currently enrolled students. Optional for alumni.</small>@endif</div>
@endforeach
<div><label for="course">Course / program</label><select id="course" name="course" required><option value="">Select a program</option>@foreach(\App\Support\CourseCatalog::activeOptions() as $code=>$title)<option value="{{ $code }}" @selected(old('course') === $code)>{{ $title }} ({{ $code }})</option>@endforeach</select></div>
<div class="full" id="test-fields"><label for="requested-test">Requested testing</label><select id="requested-test" name="tests[]" required>@foreach(['psychological'=>'Psychological Assessment','personality'=>'Personality Test','career'=>'Career Test'] as $key=>$label)<option value="{{ $key }}" @selected(in_array($key, (array) old('tests', ['psychological'])))>{{ $label }}</option>@endforeach</select></div>
<div id="copy-fields"><label for="copies">Number of copies</label><input id="copies" type="number" name="copies" min="1" max="10" value="{{ old('copies', 1) }}"></div>
<div class="full" id="testing-reason"><label for="reason">Reason for request</label><select id="reason" name="reason"><option value="">Select a reason</option>@foreach(['OJT'=>'OJT','FIELD STUDY'=>'Field Study','Others'=>'Others'] as $value=>$label)<option value="{{ $value }}" @selected(old('reason') === $value)>{{ $label }}</option>@endforeach</select></div>
<div class="full" id="other-reason-fields"><label for="other_reason">Please specify your reason</label><input id="other_reason" name="other_reason" maxlength="2000" value="{{ old('other_reason') }}" placeholder="Enter your reason for this request"></div>

<label class="full"><input type="checkbox" name="consent" value="1" required @checked(old('consent'))> I confirm these details are correct and agree that the guidance office may use them to process this request.</label>
<div class="full" style="text-align:right"><button type="button" id="review-button">SUBMIT</button></div><dialog id="review-panel" class="review-dialog" aria-labelledby="review-title" aria-describedby="review-description">
    <div class="review-heading"><h2 id="review-title">Confirm Guidance Testing Request</h2><button type="button" id="close-review" class="review-close" aria-label="Close confirmation">&times;</button></div>
    <p id="review-description" class="muted">Please review the information below before submitting.</p>
    <dl id="review-details" class="review-grid"></dl>
    <div class="review-actions"><button type="button" id="edit-request" class="secondary">EDIT INPUTS</button><button type="submit" id="confirm-submit">SUBMIT</button></div>
</dialog>
</div></form></section>
<section class="card" id="track"><h2>Track an existing request</h2>
@if(session('request_reference'))
<div class="notice" role="status"><strong>Your request has been submitted.</strong><br>Save your tracking reference: <strong>{{ session('request_reference') }}</strong><br>Your details, printable stub, next steps, and receipt upload are below.</div>
@endif
<p class="muted">Enter your private tracking reference shown after submission.</p><form id="guidance-track" method="POST" action="{{ route('api.track-request') }}" data-auto-track="{{ session('tracking_reference') || session('guidance_request_code') ? 'true' : 'false' }}">@csrf<div class="grid"><div><label for="reference">Tracking reference</label><input id="reference" name="reference" value="{{ $trackingReference }}" required autocomplete="off"></div><div><button>Track request</button></div></div></form><div id="tracking-result" role="status" aria-live="polite"></div><div id="tracking-passes">
@if($recentRequest && $recentRequest->reference === $trackingReference)
@include('portal.stub', ['entry' => $recentRequest, 'tracking' => true])
@endif
</div></section>
@endsection
@push('scripts')
<script src="{{ asset('js/guidance-tracking.js') }}" defer></script>
<script>
const service = document.getElementById('service');
function updateFields(){
    document.getElementById('request-banner').textContent=service.value==='testing'?'Psychological, Personality, and Career Test Request':({'good-moral':'Good Moral Request','exit-form':'Exit Form'})[service.value];
    for(const [id, active] of [['test-fields',service.value==='testing'],['copy-fields',['good-moral','exit-form'].includes(service.value)]]){
        const group=document.getElementById(id);group.hidden=!active;
        group.querySelectorAll('input, select').forEach(input=>input.disabled=!active);
    }
}
const form=document.getElementById('request-form');
const review=document.getElementById('review-panel');
function requirements(){
    const enrolled=document.getElementById('student_status').value==='student';
    document.getElementById('student_number').required=enrolled;
    document.getElementById('student-id-help').textContent=enrolled?'Student ID is required for currently enrolled students.':'Student ID is optional for alumni. Enter it if you remember it.';
    const testing=service.value==='testing';
    const other=document.getElementById('reason').value==='Others';
    for(const [container,id,active] of [['testing-reason','reason',true],['other-reason-fields','other_reason',other]]){
        document.getElementById(container).hidden=!active;
        const field=document.getElementById(id);field.disabled=!active;field.required=active;
    }
}
form.addEventListener('change',()=>{updateFields();requirements();review.close();});
form.addEventListener('input',()=>{if(review.open)review.close();});
document.getElementById('review-button').addEventListener('click',()=>{
    if(!form.reportValidity())return;
    if(service.value==='testing' && !form.querySelector('[name="tests[]"]').value){alert('Select at least one test.');return;}
    const details=document.getElementById('review-details');details.replaceChildren();
    const data=new FormData(form);
    const testing=service.value==='testing';
    document.getElementById('review-title').textContent=testing?'Confirm Guidance Testing Request':service.value==='good-moral'?'Confirm Good Moral Request':'Confirm Exit Form Request';
    function summary(label,value,full=false){
        const card=document.createElement('div');card.className='review-summary'+(full?' full':'');
        const dt=document.createElement('dt');dt.textContent=label;
        const dd=document.createElement('dd');dd.textContent=value || 'Not provided';
        card.append(dt,dd);details.append(card);
    }
    summary('Student Name',['first_name','middle_name','last_name'].map(key=>(data.get(key)||'').trim()).filter(Boolean).join(' '));
    summary('Student ID Number',data.get('student_number'));
    summary('Student Status',data.get('student_status')==='student'?'Currently Enrolled':'Alumni');
    summary('Course',data.get('course'));
    if(testing){
        const labels={psychological:'Psychological Assessment',personality:'Personality Test',career:'Career Test'};
        summary('Requested Testing',data.getAll('tests[]').map(test=>labels[test]).join(', '),true);
    } else summary('Number of Copies',data.get('copies'));
    summary('Reason for Request',data.get('reason')==='Others'?data.get('other_reason'):data.get('reason')==='FIELD STUDY'?'Field Study':data.get('reason'),true);
    review.removeAttribute('hidden');
    if(!review.open)review.showModal();
    document.body.classList.add('review-open');
});
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

confirmSubmitBtn.addEventListener('click', () => {
    if (review.open) {
        setTimeout(disableSubmit, 0);
    }
});

document.getElementById('edit-request').addEventListener('click',()=>{
    enableSubmit();
    review.close();
    document.getElementById('first_name').focus();
});
form.addEventListener('submit',event=>{
    if(!review.open){
        event.preventDefault();
        document.getElementById('review-button').click();
        return;
    }
    disableSubmit();
});
window.addEventListener('pageshow', () => {
    enableSubmit();
});
updateFields();requirements();
document.getElementById('close-review').addEventListener('click',()=>{
    enableSubmit();
    review.close();
});
review.addEventListener('close',()=>{
    enableSubmit();
    document.body.classList.remove('review-open');
});

// ── Auto-uppercase all text inputs except email ─────────────────────────
const uppercaseFields = ['first_name','middle_name','last_name','other_reason'];
uppercaseFields.forEach(id=>{
    const el=document.getElementById(id);
    if(!el) return;
    el.addEventListener('input',()=>{
        const pos=el.selectionStart;
        el.value=el.value.toUpperCase();
        el.setSelectionRange(pos,pos);
    });
});

// ── Student ID mask: ##-SC-#### ──────────────────────────────────────────
// Format: 2 digits (year), fixed -SC-, 4 digits (unique number) e.g. 23-SC-4143
(function(){
    const sid = document.getElementById('student_number');
    if(!sid) return;
    const MID = '-SC-';
    const PREFIX_LEN = 2;  // 2-digit year (e.g. 23)
    const SUFFIX_LEN = 4;  // 4-digit unique number

    function applyMask(raw){
        // Keep only digits
        const digits = raw.replace(/\D/g,'');
        const pre = digits.slice(0, PREFIX_LEN);
        const suf = digits.slice(PREFIX_LEN, PREFIX_LEN + SUFFIX_LEN);
        if(!pre) return '';
        if(pre.length < PREFIX_LEN) return pre;
        // prefix full — insert fixed -SC-
        return pre + MID + suf;
    }

    sid.addEventListener('input',function(){
        const before = this.value;
        const caret  = this.selectionStart;
        // Count digits before caret
        let rawCount = 0;
        for(let i=0;i<caret && i<before.length;i++){
            if(/\d/.test(before[i])) rawCount++;
        }
        const masked = applyMask(before);
        this.value = masked;
        // Reposition caret at same digit count
        let newCaret = 0, rc = 0;
        while(newCaret < masked.length && rc < rawCount){
            if(/\d/.test(masked[newCaret])) rc++;
            newCaret++;
        }
        this.setSelectionRange(newCaret, newCaret);
    });

    sid.addEventListener('keydown',function(e){
        // Allow: backspace, delete, arrows, tab, home, end
        if([8,46,37,38,39,40,9,35,36].includes(e.keyCode)) return;
        // Only allow digit keys
        const isDigit = (e.key >= '0' && e.key <= '9') || (e.keyCode >= 96 && e.keyCode <= 105);
        if(!isDigit){ e.preventDefault(); return; }
        // Block when max digits reached
        const digits = this.value.replace(/\D/g,'');
        if(digits.length >= PREFIX_LEN + SUFFIX_LEN) e.preventDefault();
    });

    sid.placeholder = '23-SC-4143';
    sid.setAttribute('maxlength', PREFIX_LEN + MID.length + SUFFIX_LEN); // 2+4+4=10
    sid.setAttribute('pattern', '\\d{' + PREFIX_LEN + '}-SC-\\d{0,' + SUFFIX_LEN + '}');
    sid.setAttribute('inputmode', 'numeric');
})();
</script>
@endpush
