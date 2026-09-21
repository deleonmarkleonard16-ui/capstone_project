<div class="row g-4" data-review-state="{{ $appointment->status }}">
    <section class="col-lg-6" aria-label="Student profile and receipt">
        <span class="badge text-bg-primary fs-5 mb-3">{{ $appointment->request_code }}</span>
        <h3 class="h5">Student profile</h3>
        <dl class="row">
            <dt class="col-sm-4">Full name</dt><dd class="col-sm-8">{{ mb_strtoupper($appointment->applicant->full_name) }}</dd>
            <dt class="col-sm-4">Student ID</dt><dd class="col-sm-8">{{ mb_strtoupper($appointment->student_id_number ?: ($appointment->serviceRequest?->student_number ?: 'Not provided')) }}</dd>
            <dt class="col-sm-4">Course / Program</dt><dd class="col-sm-8">{{ mb_strtoupper($appointment->batch?->course ?? $appointment->sourceBatch?->course ?? $appointment->serviceRequest?->course ?? 'Not provided') }}</dd>
            <dt class="col-sm-4">Student status</dt><dd class="col-sm-8">{{ $appointment->student_status === 'alumni' ? 'Alumni' : 'Currently enrolled' }}</dd>
            <dt class="col-sm-4">Request status</dt><dd class="col-sm-8"><strong>{{ $appointment->status }}</strong></dd>
            <dt class="col-sm-4">Assessments</dt><dd class="col-sm-8">{{ $appointment->testLabel() }}</dd>
            @if($appointment->appointment_at)<dt class="col-sm-4">Appointment</dt><dd class="col-sm-8">{{ $appointment->appointment_at->timezone('Asia/Manila')->format('M d, Y g:i A') }} (Philippine time)</dd>@endif
            @if($appointment->serviceRequest)<dt class="col-sm-4">Purpose</dt><dd class="col-sm-8">{{ $appointment->serviceRequest->purpose }}</dd>@endif
        </dl>
        @if($appointment->batch || $appointment->sourceBatch)<p><strong>Reason:</strong> {{ ($appointment->batch ?? $appointment->sourceBatch)->reason_for_request }}</p>@endif
        <h3 class="h5">Payment receipt</h3>
        @php($receiptOrNum = $appointment->or_number ?: $appointment->serviceRequest?->or_number)
        @php($receiptOrDate = $appointment->or_date ?: $appointment->serviceRequest?->or_date)
        @if($receiptOrNum || $receiptOrDate)
            <dl class="row mb-2">
                @if($receiptOrNum)<dt class="col-sm-4">O.R. Number</dt><dd class="col-sm-8"><span class="badge text-bg-light border text-dark fs-6">{{ $receiptOrNum }}</span></dd>@endif
                @if($receiptOrDate)<dt class="col-sm-4">O.R. Date</dt><dd class="col-sm-8">{{ \Illuminate\Support\Carbon::parse($receiptOrDate)->format('M d, Y') }}</dd>@endif
            </dl>
        @endif
        @if($appointment->payment_slip_path)
            <a href="{{ route(auth()->user()->role.'.guidance-appointments.receipt', $appointment) }}" target="_blank" rel="noopener">Open original receipt</a>
            <div class="border rounded mt-2 p-2 text-center" data-review-preview aria-busy="true">
                <div data-review-receipt-loading role="status"><span class="review-receipt-spinner" aria-hidden="true"></span><span class="d-block small mt-2">Loading receipt preview...</span></div>
                <img data-src="{{ route(auth()->user()->role.'.guidance-appointments.receipt', $appointment) }}" loading="lazy" decoding="async" fetchpriority="low" class="img-fluid" style="max-height:420px" alt="Uploaded payment receipt" data-review-receipt hidden>
                <p class="text-danger" data-review-receipt-error role="status" hidden>Image preview unavailable. Use Open original receipt.</p>
            </div>
        @else<p class="text-muted">Awaiting receipt upload.</p>@endif
    </section>
    <section class="col-lg-6" aria-label="Assessment pass and results">
        <h3 class="h5">Assessment QR pass</h3>
        @if($appointment->status === 'Completed')<p class="text-muted">Used QR pass (inactive).</p>@endif
        @if($testUrl)
            <img class="img-fluid d-block mb-3" width="260" height="260" src="{{ $qrImage }}" alt="{{ $appointment->testLabel() }} QR pass">
            <a href="{{ $testUrl }}" target="_blank" rel="noopener">Click here if QR scanner is unavailable</a>
            <p class="small text-muted mt-2 text-break">{{ $testUrl }}</p>
        @elseif($appointment->status === 'Completed')<p class="text-muted">Assessment completed. The QR pass is inactive.</p>
        @else<p class="text-muted">The QR pass appears after receipt verification.</p>@endif
        @if($appointment->status === 'Receipt Uploaded' && !$appointment->is_archived && !$appointment->batch_id)
            <form method="post" action="{{ route(auth()->user()->role.'.guidance-appointments.verify', $appointment) }}" data-review-verify>@csrf
                <button class="btn btn-primary mt-3" @disabled($appointment->awaitsScoringConfiguration())>Verify &amp; Generate QR</button>
                @if($appointment->awaitsScoringConfiguration())<p class="text-muted small mt-2">Scoring configuration is pending. This request will remain awaiting approval.</p>@endif
            </form>
        @endif
        @if($appointment->response)
            <h3 class="h5 mt-4">Submission results</h3>
            @foreach($appointment->response->testSummaries() as $test => $summary)
                <template data-review-deferred>
                <h4 class="h6 mt-3">{{ \App\Services\GuidanceTestScoringService::LABELS[$test] ?? $test }}</h4>
                @if(isset($summary['scores']))
                    <table class="table table-sm"><thead><tr><th>Scale</th><th>Score</th><th>{{ $test === 'career' ? 'Interest level' : 'Severity' }}</th></tr></thead><tbody>
                    @foreach($summary['scores'] as $scale => $score)<tr><td>{{ ucfirst($scale) }}</td><td>{{ $score }}</td><td>{{ $summary['interpretation'][$scale === 'total' ? 'severity' : $scale] ?? 'For counselor review' }}</td></tr>@endforeach
                    </tbody></table>
                    @if($test === 'career')<p><strong>Top RIASEC Traits:</strong> {{ $summary['interpretation']['top_traits'] }}</p><p class="small text-muted">{{ $summary['interpretation']['review'] }}</p>@endif
                @else<p>{{ $summary['interpretation']['review'] ?? 'Incomplete; no score calculated.' }}</p>@endif
                </template>
            @endforeach
            <h4 class="h6 mt-3">Raw item answers (JSON)</h4>
            <pre class="bg-light border rounded p-3" style="max-height:280px;overflow:auto;white-space:pre-wrap" tabindex="0" aria-label="Raw item answers">@foreach(mb_str_split(json_encode($appointment->response->answers, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), 4096) as $chunk)<template data-review-deferred>{{ $chunk }}</template>@endforeach</pre>
            <a class="btn btn-outline-success btn-sm" href="{{ route(auth()->user()->role.'.guidance-appointments.show-results', $appointment) }}">Review Student Submission</a>
        @endif
    </section>
</div>
