@php($officialFee = $entry->official_fee ?? \App\Support\RequestFees::total($entry->service, $entry->tests ?? [], $entry->copies ?? 1))
@if($officialFee !== null)<p class="notice"><strong>Official fee: Php {{ number_format($officialFee, 2) }}</strong></p>@endif
@if($entry->guidanceAppointments()->exists())
@include('guidance.stub', ['tracking' => $tracking ?? false])
@else
<section class="card payment-stub" id="payment-stub">
    <div class="request-banner">{{ ['good-moral'=>'GOOD MORAL CHARACTER REQUEST STUB','exit-form'=>'EXIT FORM CLEARANCE STUB'][$entry->service] ?? 'TESTING REQUEST STUB' }}</div>
    <h2>Pangasinan State University – San Carlos Campus</h2>
    <p>Present this stub to the Registrar or Cashier for payment / clearance processing.</p>
    <div class="grid">
        <div><label>Request Number</label><strong>{{ $entry->reference }}</strong></div>
        <div><label>Date Requested</label>{{ $entry->created_at->timezone('Asia/Manila')->format('M d, Y') }}</div>
        <div><label>Action Deadline (5 Days)</label><strong style="color:{{ $entry->isVoid() ? '#dc2626' : ($entry->expires_at && now()->greaterThan($entry->expires_at) ? '#dc2626' : '#2563eb') }}">{{ $entry->expires_at ? $entry->expires_at->timezone('Asia/Manila')->format('M d, Y g:i A') : 'N/A' }} ({{ $entry->timeRemainingText() }})</strong></div>
        <div><label>Student / Alumni Name</label><strong>{{ strtoupper(trim($entry->first_name.' '.$entry->middle_name.' '.$entry->last_name)) }}</strong></div>
        <div><label>Student ID Number</label>{{ $entry->student_number ?: 'NOT PROVIDED' }}</div>
        <div><label>Student Status</label>{{ $entry->student_status === 'student' ? 'CURRENTLY ENROLLED' : 'ALUMNI' }}</div>
        <div><label>Course</label>{{ $entry->courseLabel() }}</div>
        <div><label>Amount Paid / Amount Due</label><strong style="color:#16a34a;font-size:1.1em">{{ $entry->official_fee_formatted ?? '₱60.00' }}</strong></div>
        @if($entry->service === 'testing' && $entry->tests)
        <div class="full"><label>Requested Testing</label>{{ collect($entry->tests)->map(fn($test) => ['psychological'=>'Psychological Assessment','personality'=>'Personality Test','career'=>'Career Test'][$test] ?? ucfirst($test))->join(', ') }}</div>
        @endif
        @if($entry->copies)
        <div><label>Number of Copies</label><strong>{{ $entry->copies }}</strong></div>
        @endif
        <div class="full"><label>Reason / Purpose</label>{{ $entry->purpose }}</div>
    </div>
    <div style="border-top:1px dashed #aaa;margin-top:24px;padding-top:20px"><strong>FOR OFFICE USE</strong><p>Amount Paid / Amount Due: <strong>{{ $entry->official_fee_formatted ?? '₱60.00' }}</strong> &nbsp; Receipt / Stub number: __________________</p><p>Payment date: __________________ &nbsp; Verified by / stamp: __________________</p></div>
    <p class="muted">This is a request stub, not proof of payment. The payment office confirms the applicable fee.</p>
    <button type="button" class="no-print" onclick="window.print()">PRINT / SAVE STUB</button>
</section>

@if($entry->isVoid())
<section class="card no-print" style="border: 2px solid #ef4444; background: #fef2f2;">
    <h2 style="color:#991b1b; margin-top:0;">Request Void / Expired</h2>
    <p style="color:#7f1d1d;">This request has exceeded the 5-day action window without completing the next steps and is now <strong>void</strong>. A new request must be submitted if you still wish to proceed.</p>
    <a href="{{ route('portal.index') }}" class="button" style="background:#dc2626;">Submit New Request</a>
</section>
@else
<section class="card no-print"><h2>What to do next</h2><ol>
    <li>Print or save your request stub and keep your tracking number: <strong>{{ $entry->reference }}</strong></li>
    <li>Present the stub to the Registrar or Cashier and follow payment instructions. <em>(Note: You have 5 days from request date to complete this step before the request is voided.)</em></li>
    <li>Upload a clear photo or PDF of your paid/stamped stub or official payment receipt below.</li>
    <li>Wait for the guidance office to verify your payment. Track this request for updates.</li>
    @if($entry->service === 'testing')
    <li>Complete your requested guidance assessments online or attend your scheduled session.</li>
    @else
    <li>Once marked ready, claim your requested document at the Guidance Office.</li>
    @endif
</ol>
<p><strong>Status:</strong> <span style="font-weight:bold; color:{{ $entry->status === 'ready' || $entry->status === 'completed' ? '#16a34a' : '#2563eb' }};">{{ ['pending'=>'Awaiting payment / paid stub','approved'=>'Approved — Paid/stamped stub required','proof_review'=>'Stub submitted — awaiting verification','processing'=>'Payment verified — processing document','ready'=>'Ready for pick-up at Guidance Office','scheduled'=>'Verified — scheduled for testing','completed'=>'Completed','declined'=>'Declined','cancelled'=>'Cancelled','void'=>'Void (5-day window expired)'][$entry->status] ?? ucfirst($entry->status) }}</span></p>
@if($entry->staff_message)<p><strong>Office message:</strong> {{ $entry->staff_message }}</p>@endif

@if($entry->service === 'testing')
<div style="margin:20px 0; padding:16px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:8px;">
    <h3 style="margin-top:0; color:#166534;">Assessment access</h3>
    <p class="muted" style="margin-bottom:0;">Your verified assessment pass will appear when you track this request after receipt approval.</p>
</div>
@endif

@if(in_array($entry->status, ['pending','approved']) && ($tracking ?? false))
<form method="POST" action="{{ route('portal.receipt') }}" enctype="multipart/form-data" data-guidance-receipt>
    @csrf
    <input type="hidden" name="reference" value="{{ $entry->reference }}">
    <div style="margin-bottom:12px">
        <label for="portal-or_number-{{ $entry->id }}">Official Receipt Number</label>
        <input id="portal-or_number-{{ $entry->id }}" name="or_number" type="text" maxlength="50" placeholder="Official Receipt Number" required>
    </div>
    <div style="margin-bottom:12px">
        <label for="portal-or_date-{{ $entry->id }}">Receipt Date</label>
        <input id="portal-or_date-{{ $entry->id }}" name="or_date" type="date" max="{{ date('Y-m-d') }}" required>
    </div>
    <div style="margin-bottom:12px">
        <label for="proof-{{ $entry->id }}">Upload paid/stamped stub or receipt (JPG, PNG, PDF; up to 5 MB)</label>
        <input id="proof-{{ $entry->id }}" name="payment_slip" type="file" accept=".jpg,.jpeg,.png,.pdf,image/*" required>
    </div>
    <button style="margin-top:16px">UPLOAD STUB FOR VERIFICATION</button>
    <p data-upload-message role="status"></p>
</form>
@elseif(in_array($entry->status, ['pending','approved']))
<a class="button" href="/portal?service={{ $entry->service }}#track">Track Existing Request / Upload Receipt</a>
@endif
@if($entry->scheduled_at)<div class="notice" style="margin-top:16px"><strong>Test schedule:</strong> {{ $entry->scheduled_at->timezone('Asia/Manila')->format('M d, Y g:i A') }} (Philippine time)<br><strong>Venue:</strong> {{ $entry->venue }}</div>@endif
</section>
@endif

@endif
