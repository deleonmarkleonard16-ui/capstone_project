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
                <td>@if($entry->hasReceipt())<button type="button" class="btn btn-link btn-sm p-0" data-receipt="{{ route(auth()->user()->role.'.documents.proof', $entry) }}" data-or-number="{{ $entry->or_number }}" data-or-date="{{ $entry->or_date ? \Illuminate\Support\Carbon::parse($entry->or_date)->format('M d, Y') : '' }}"><i class="bi bi-receipt me-1"></i>Show receipt</button>@else<span class="text-muted"><i class="bi bi-hourglass-split me-1"></i>Not uploaded</span>@endif</td>
                <td class="text-nowrap">@include('staff.partials.document-actions')
                    @if(($moduleKey === 'good-moral' && in_array($entry->status, ['approved', 'processing', 'ready', 'completed'], true)) || ($moduleKey === 'exit-form' && $entry->status === 'completed'))
                        @foreach(['html' => ($moduleKey === 'good-moral' ? 'Print Certificate' : 'Print Exit Clearance Slip'), 'pdf' => 'PDF', 'docx' => 'DOCX'] as $format => $label)
                            <a class="btn btn-sm btn-outline-success" href="{{ route(auth()->user()->role.'.'.$moduleKey.'.export', ['request_id' => $entry->id, 'type' => 'certificate', 'format' => $format, 'auto_print' => $format === 'html' ? 1 : 0]) }}" target="_blank" rel="noopener">{{ $label }}</a>
                        @endforeach
                    @endif
                </td>
            </tr>
        @empty
            <tr data-empty-row><td colspan="6" class="text-center text-muted py-5">No {{ $mode === 'archive' ? 'archived' : 'active' }} {{ \App\Http\Controllers\DocumentRequestController::MODULES[$moduleKey] }} requests.</td></tr>
        @endforelse
        </tbody>
    </table></div>
    {{ $requests->links('pagination::bootstrap-5') }}
</div></div>
</div>
