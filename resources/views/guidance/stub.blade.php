@php($officialFee = $entry->official_fee ?? \App\Support\RequestFees::total($entry->service ?? 'testing', $entry->tests ?? [], $entry->copies ?? 1))
@if($officialFee !== null)<p class="notice"><strong>Official fee: Php {{ number_format($officialFee, 2) }}</strong></p>@endif
<section class="card payment-stub" id="payment-stub">
    <div class="request-banner">TESTING REQUEST STUB</div>
    <h2>Pangasinan State University – San Carlos Campus</h2>
    <p>Present this stub at the payment office as proof that you submitted a request to Guidance.</p>
    <div class="grid">
        <div><label>Tracking reference</label><strong>{{ $entry->reference }}</strong></div>
        <div><label>Date requested</label>{{ $entry->created_at->timezone('Asia/Manila')->format('M d, Y') }}</div>
        <div><label>Student / Alumni name</label>{{ strtoupper(trim($entry->first_name.' '.$entry->middle_name.' '.$entry->last_name)) }}</div>
        <div><label>Student ID</label>{{ mb_strtoupper($entry->student_number ?: 'Not provided') }}</div>
        <div><label>Course</label>{{ $entry->courseLabel() }}</div>
        <div><label>Student status</label>{{ $entry->student_status === 'student' ? 'CURRENTLY ENROLLED' : 'ALUMNI' }}</div>
        <div><label>Amount Paid / Amount Due</label><strong style="color:#16a34a;font-size:1.1em">{{ $entry->official_fee_formatted ?? '₱60.00' }}</strong></div>
        <div class="full"><label>Requested testing</label>{{ collect($entry->tests)->map(fn($test) => ['psychological'=>'Psychological Assessment','personality'=>'Personality Test','career'=>'Career Test'][$test] ?? ucfirst($test))->join(', ') }}</div>
        <div class="full"><label>Reason</label>{{ $entry->purpose }}</div>
    </div>
    <div style="border-top:1px dashed #aaa;margin-top:24px;padding-top:20px">
        <strong>FOR OFFICE USE</strong>
        <p>Amount Paid / Amount Due: <strong>{{ $entry->official_fee_formatted ?? '₱60.00' }}</strong> &nbsp; Receipt / Stub number: __________________</p>
        <p>Payment date: __________________ &nbsp; Verified by / stamp: __________________</p>
    </div>
    <p class="muted">This stub confirms your request. The payment office will issue your receipt after payment.</p>
    <button type="button" class="no-print button" data-download-stub data-ref="{{ $entry->reference }}" style="margin-top:12px;font-weight:700;">
        📥 DOWNLOAD REQUEST STUB (PRINT / SAVE STUB)
    </button>
</section>
<section class="card no-print">
    <h3>Payment and assessment pass</h3>
    <ol><li>Download or print your request stub above and pay the required fee of <strong>₱60.00</strong> at the payment office.</li><li>Return to Track Existing Request and upload the receipt issued after payment.</li><li>Wait for staff/admin verification. Your QR pass and direct test link will appear in this tracking section.</li></ol>
    @if(!$entry->hasReceipt())
        <p><strong>Awaiting payment receipt.</strong> No QR pass is available yet.</p>
        @if($tracking)
        <div class="stub-download-gate" data-gate-ref="{{ $entry->reference }}" style="margin-top:16px;">
            <div class="notice notice-warn stub-download-lock-msg" id="stub-lock-msg-{{ $entry->reference }}" style="background:#fff8e6;border-left:4px solid #f59e0b;padding:14px;border-radius:8px;margin-bottom:14px;">
                <span style="font-size:18px;margin-right:6px;">⚠️</span>
                <strong>Action Required:</strong> You must click <strong>"DOWNLOAD REQUEST STUB"</strong> above before you can proceed to upload your payment receipt.
            </div>
            <div class="receipt-upload-container" id="receipt-upload-box-{{ $entry->reference }}">
                <form method="post" action="{{ route('guidance.receipt') }}" enctype="multipart/form-data" data-guidance-receipt>@csrf
                    <input type="hidden" name="reference" value="{{ $entry->reference }}">
                    <div style="margin-bottom:12px">
                        <label for="or_number-{{ $entry->id }}">Official Receipt Number</label>
                        <input id="or_number-{{ $entry->id }}" name="or_number" type="text" maxlength="50" placeholder="Official Receipt Number" required>
                        <div class="field-error field-error-or_number" style="color:#dc2626;font-size:13px;font-weight:500;margin-top:4px;display:none;"></div>
                    </div>
                    <div style="margin-bottom:12px">
                        <label for="or_date-{{ $entry->id }}">Receipt Date</label>
                        <input id="or_date-{{ $entry->id }}" name="or_date" type="date" max="{{ date('Y-m-d') }}" required>
                        <div class="field-error field-error-or_date" style="color:#dc2626;font-size:13px;font-weight:500;margin-top:4px;display:none;"></div>
                    </div>
                    <div style="margin-bottom:12px">
                        <label for="receipt-{{ $entry->id }}">Upload payment receipt (JPG, PNG, WebP; up to 5 MB)</label>
                        <input id="receipt-{{ $entry->id }}" name="payment_slip" type="file" accept="image/jpeg,image/png,image/webp" required>
                        <div class="field-error field-error-payment_slip" style="color:#dc2626;font-size:13px;font-weight:500;margin-top:4px;display:none;"></div>
                    </div>
                    <button type="submit" data-original-text="Upload Receipt for Verification" style="margin-top:16px">Upload Receipt for Verification</button>
                    <div data-upload-alert class="upload-alert-banner" style="display:none;margin-top:14px;"></div>
                </form>
            </div>
        </div>
        @else
        <a class="button" href="/portal?service=testing#track">Track Existing Request / Upload Receipt</a>
        @endif
    @elseif($entry->status === 'proof_review')
        <div class="notice" style="background:#eff6ff;border-left:4px solid #3b82f6;color:#1e40af;padding:14px;border-radius:8px;">
            <strong class="tracking-status-badge">Receipt Uploaded / Pending Verification</strong>
            <p style="margin:6px 0 0 0;">Receipt uploaded successfully! Your payment is now pending verification by Guidance Staff.</p>
        </div>
    @else
        <p class="notice">Receipt received. Check the assessment statuses and QR passes in Track Existing Request.</p>
    @endif
</section>
