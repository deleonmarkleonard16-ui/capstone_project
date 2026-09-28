@extends('layouts.app')
@section('content')
<h1 class="h3 mb-2">Admission Archive</h1>
<p class="text-muted">Archived admission cycles and their applicant records.</p>
<div class="card page-card p-4">
    <div class="table-responsive">
    <table class="table align-middle mb-0">
        <thead>
            <tr>
                <th>Cycle / Academic Year</th>
                <th>Applicants</th>
                <th>Completion</th>
                <th class="text-end">Actions</th>
            </tr>
        </thead>
        <tbody>
        @forelse($cycles as $cycle)
        <tr>
            <td>
                <div class="fw-semibold">{{ $cycle->display_name }}</div>
                <small class="text-muted">S.Y. {{ str_replace('-', ' – ', $cycle->academic_year ?: $cycle->display_name) }}</small>
            </td>
            <td>
                <span class="badge text-bg-primary">{{ number_format($cycle->applicants_count) }} Applicants</span>
            </td>
            <td>
                <span class="badge text-bg-secondary">Completed / Archived</span>
                <div class="small text-muted mt-1">{{ $cycle->updated_at?->timezone('Asia/Manila')->format('M d, Y g:i A') ?? 'Date unavailable' }}</div>
            </td>
            <td class="text-end">
                <div class="d-flex flex-wrap justify-content-end gap-2">
                    <a class="btn btn-sm btn-outline-primary"
                       href="{{ route('admin.admission.masterlist', ['cycle_id' => $cycle->id]) }}">
                        <i class="bi bi-table me-1"></i>Inspect Masterlist Roster
                    </a>
                    <a class="btn btn-sm btn-outline-secondary"
                       href="{{ route('admin.admission.analytics', ['cycle_id' => $cycle->id]) }}">
                        <i class="bi bi-bar-chart-line me-1"></i>View Analytics
                    </a>
                    <a class="btn btn-sm btn-primary"
                       href="{{ route('admin.admission.report', ['cycle_id' => $cycle->id, 'type' => 'summary', 'format' => 'docx']) }}">
                        <i class="bi bi-file-earmark-word me-1"></i>Export Evaluation DOCX
                    </a>
                    <a class="btn btn-sm btn-outline-danger"
                       href="{{ route('admin.admission.report', ['cycle_id' => $cycle->id, 'type' => 'summary', 'format' => 'pdf']) }}">
                        <i class="bi bi-file-earmark-pdf me-1"></i>Export Evaluation PDF
                    </a>
                </div>
            </td>
        </tr>
        @empty
        <tr><td colspan="4" class="text-center text-muted py-4">No archived admission cycles.</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
</div>
@endsection
