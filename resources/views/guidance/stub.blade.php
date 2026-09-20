<section class="card payment-stub">
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
        <div class="full"><label>Requested testing</label>{{ collect($entry->tests)->map(fn($test) => ['psychological'=>'Psychological Assessment','personality'=>'Personality Test','career'=>'Career Test'][$test] ?? ucfirst($test))->join(', ') }}</div>
        <div class="full"><label>Reason</label>{{ $entry->purpose }}</div>
    </div>
    <p class="muted">This stub confirms your request. The payment office will issue your receipt after payment.</p>
    <button type="button" class="no-print" data-print-stub>PRINT / SAVE STUB</button>
</section>
<section class="card no-print">
    <h3>Payment and assessment pass</h3>
    <ol><li>Show your request stub at the payment office and pay the required fee.</li><li>Return to Track Existing Request and upload the receipt issued after payment.</li><li>Wait for staff/admin verification. Your QR pass and direct test link will appear in this tracking section.</li></ol>
    @if(!$entry->proof_path)
        <p><strong>Awaiting payment receipt.</strong> No QR pass is available yet.</p>
        @if($tracking)
        <form method="post" action="{{ route('guidance.receipt') }}" enctype="multipart/form-data" data-guidance-receipt>@csrf
            <input type="hidden" name="reference" value="{{ $entry->reference }}">
            <label for="receipt-{{ $entry->id }}">Upload payment receipt (JPG, PNG, WebP; up to 5 MB)</label>
            <input id="receipt-{{ $entry->id }}" name="proof" type="file" accept="image/jpeg,image/png,image/webp" required>
            <button style="margin-top:16px">Upload Receipt for Verification</button>
            <p data-upload-message role="status"></p>
        </form>
        @else
        <a class="button" href="/portal?service=testing#track">Track Existing Request / Upload Receipt</a>
        @endif
    @elseif($entry->status === 'proof_review')
        <p class="notice">Receipt uploaded — awaiting staff verification. Your QR/link will appear here once approved.</p>
    @else
        <p class="notice">Receipt received. Check the assessment statuses and QR passes in Track Existing Request.</p>
    @endif
</section>
