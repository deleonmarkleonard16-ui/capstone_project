<div data-module-live data-module="{{ $moduleKey }}" data-view="{{ $mode }}"><div class="card page-card"><div class="card-body p-4">
    <h2 class="h5 section-title mb-3">{{ $mode === 'archive' ? 'Archived Requests' : 'Individual Request Queue' }}</h2>
    <div class="table-responsive"><table class="table align-middle" @if($mode === 'queue') data-individual-queue data-module="{{ $moduleKey }}" @endif>
        <thead><tr><th>Student / Request</th><th>Program</th><th>Purpose</th><th>Status</th><th>Receipt</th><th>Actions</th></tr></thead>
        <tbody>
        @forelse($requests as $entry)
            <tr data-request-id="{{ $entry->id }}">
                <td><span class="badge text-bg-primary mb-2">{{ $entry->reference }}</span><br><strong>{{ mb_strtoupper(trim($entry->first_name.' '.($entry->middle_name ? $entry->middle_name.' ' : '').$entry->last_name)) }}</strong><div class="small text-muted">{{ $entry->student_number ?: 'Alumni' }} Â· {{ $entry->created_at->timezone('Asia/Manila')->format('M d, Y') }}</div></td>
                <td>{{ $entry->courseLabel() }}</td>
                <td>{{ $entry->purpose ?: 'Not provided' }}@if($entry->copies)<div class="small text-muted">{{ $entry->copies }} {{ \Illuminate\Support\Str::plural('copy', $entry->copies) }}</div>@endif</td>
                <td>@include('staff.partials.document-status')@if($entry->or_number)<div class="small">OR {{ $entry->or_number }} / {{ $entry->or_date?->format('Y-m-d') }}</div>@endif</td>
                <td>@if($entry->proof_path)<button type="button" class="btn btn-link btn-sm p-0" data-receipt="{{ route(auth()->user()->role.'.documents.proof', $entry) }}" data-or-number="{{ $entry->or_number }}" data-or-date="{{ $entry->or_date ? \Illuminate\Support\Carbon::parse($entry->or_date)->format('M d, Y') : '' }}"><i class="bi bi-receipt me-1"></i>Show receipt</button>@else<span class="text-muted"><i class="bi bi-hourglass-split me-1"></i>Not uploaded</span>@endif</td>
                <td class="text-nowrap">@include('staff.partials.document-actions')
                    @if(in_array($entry->status, ['ready', 'completed'], true))<button type="button" class="btn btn-sm btn-outline-success" data-document-print="document-print-{{ $entry->id }}"><i class="bi bi-printer me-1"></i>{{ $moduleKey === 'good-moral' ? 'Print Certificate' : 'Print Exit Clearance Slip' }}</button>@endif
                </td>
            </tr>
        @empty
            <tr data-empty-row><td colspan="6" class="text-center text-muted py-5">No {{ $mode === 'archive' ? 'archived' : 'active' }} {{ \App\Http\Controllers\DocumentRequestController::MODULES[$moduleKey] }} requests.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    {{ $requests->links('pagination::bootstrap-5') }}
</div></div>
@foreach($requests as $entry)
    @if(in_array($entry->status, ['ready', 'completed'], true))
        <div id="document-print-{{ $entry->id }}" hidden><div style="font-family:Georgia,serif;max-width:700px;margin:40px auto;padding:48px;border:5px double #17305f;color:#000">
            <header style="text-align:center"><strong>PANGASINAN STATE UNIVERSITY</strong><br>San Carlos Campus<br>Guidance and Counseling Office</header>
            <h1 style="text-align:center;font-size:20px;margin:32px 0">{{ $moduleKey === 'good-moral' ? 'Certificate of Good Moral Character' : 'Exit Clearance Slip' }}</h1>
            <p style="line-height:2">This certifies that <strong>{{ mb_strtoupper(trim($entry->first_name.' '.($entry->middle_name ? $entry->middle_name.' ' : '').$entry->last_name)) }}</strong>, student number <strong>{{ $entry->student_number ?: 'N/A' }}</strong>, enrolled in <strong>{{ $entry->courseLabel() }}</strong>, has been {{ $moduleKey === 'good-moral' ? 'issued a certificate of good moral character' : 'cleared by the Guidance and Counseling Office' }}.</p>
            <p><strong>Purpose:</strong> {{ $entry->purpose ?: 'Not provided' }}</p><p><strong>Issued:</strong> {{ now()->timezone('Asia/Manila')->format('F j, Y') }}</p>
            <p style="text-align:right;margin-top:70px">_________________________<br>Guidance Counselor</p>
        </div></div>
    @endif
@endforeach
</div>
@push('scripts')<script>
document.addEventListener('click', event => {
    const button = event.target.closest('[data-document-print]');
    if (!button) return;
    const content = document.getElementById(button.dataset.documentPrint);
    const printWindow = window.open('', '_blank');
    if (!content || !printWindow) return;
    printWindow.document.write('<!doctype html><html><head><title>Guidance Document</title></head><body>' + content.innerHTML + '</body></html>');
    printWindow.document.close(); printWindow.focus(); printWindow.print();
});
</script>@endpush
