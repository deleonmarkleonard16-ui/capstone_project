@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4 d-print-none">
    <div>
        <h1 class="h3 mb-1">Ranked List of Passers</h1>
        <p class="text-muted mb-0">{{ $session->name ?? ('Session #'.$session->id) }}</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="{{ route(auth()->user()->role.'.admission-evaluation.index', ['session_id' => $session->id]) }}" class="btn btn-outline-secondary">&larr; Back to Evaluation</a>
        <button onclick="window.print()" class="btn btn-outline-primary">🖨️ Print List</button>
    </div>
</div>

{{-- Cutoff filter --}}
<form method="GET" class="d-flex gap-3 align-items-end mb-4 d-print-none">
    <input type="hidden" name="session_id" value="{{ $session->id }}">
    <div>
        <label class="form-label mb-1 fw-semibold">Show top (cutoff rank, 0 = all)</label>
        <input type="number" name="cutoff" class="form-control" style="width:120px;" min="0" value="{{ $cutoff }}">
    </div>
    <button class="btn btn-primary">Filter</button>
</form>

{{-- Print header (only shown when printing) --}}
<div class="d-none d-print-block text-center mb-4">
    <div style="font-size:13pt;font-weight:bold;">PANGASINAN STATE UNIVERSITY – SAN CARLOS CITY CAMPUS</div>
    <div style="font-size:11pt;">Guidance and Admission Office</div>
    <div style="font-size:14pt;font-weight:bold;margin-top:8px;text-transform:uppercase;letter-spacing:1px;">
        Official List of Passers
    </div>
    <div style="font-size:10pt;">{{ $session->name ?? ('Session #'.$session->id) }} &nbsp;|&nbsp; Printed: {{ now()->timezone('Asia/Manila')->format('F j, Y') }}</div>
    <hr>
</div>

<div class="alert alert-info mb-3 d-print-none">
    <strong>{{ $weights['exam'] }}% Exam + {{ $weights['gwa'] }}% GWA + {{ $weights['interview'] }}% Interview = Total Marks</strong>
    &nbsp;·&nbsp; Showing {{ $evaluations->count() }} applicant(s){{ $cutoff > 0 ? ' (top '.$cutoff.')' : '' }}.
</div>

<div class="card page-card">
    <div class="card-body p-0">
        <table class="table table-bordered mb-0">
            <thead class="table-dark">
                <tr>
                    <th>Rank</th>
                    <th>Name</th>
                    <th>Exam Score</th>
                    <th>Exam ({{ $weights['exam'] }}%)</th>
                    <th>GWA</th>
                    <th>GWA ({{ $weights['gwa'] }}%)</th>
                    <th>Interview</th>
                    <th>Interview ({{ $weights['interview'] }}%)</th>
                    <th>Total Marks</th>
                </tr>
            </thead>
            <tbody>
                @forelse($evaluations as $eval)
                <tr @if($loop->index < 3) class="table-success fw-semibold" @endif>
                    <td class="fw-bold text-center">{{ $eval->rank }}</td>
                    <td>{{ $eval->applicant->last_name ?? '—' }}, {{ $eval->applicant->first_name ?? '' }}</td>
                    <td class="text-center">{{ number_format($eval->exam_score, 1) }}</td>
                    <td class="text-center">{{ number_format($eval->exam_percentage, 2) }}</td>
                    <td class="text-center">{{ number_format($eval->gwa, 2) }}</td>
                    <td class="text-center">{{ number_format($eval->gwa_percentage, 2) }}</td>
                    <td class="text-center">{{ number_format($eval->interview_score, 1) }}</td>
                    <td class="text-center">{{ number_format($eval->interview_percentage, 2) }}</td>
                    <td class="text-center fw-bold">{{ number_format($eval->total_marks, 2) }}</td>
                </tr>
                @empty
                <tr><td colspan="9" class="text-center text-muted py-4">No evaluations found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Print signature line --}}
<div class="d-none d-print-block mt-5" style="display:flex;justify-content:flex-end;">
    <div style="text-align:center;">
        <div style="border-top:1px solid #000;width:260px;padding-top:6px;font-size:10pt;">
            <strong>Guidance Counselor / Admission Officer</strong><br>PSU San Carlos City Campus
        </div>
    </div>
</div>

<style>
@media print {
    .d-print-none { display: none !important; }
    .d-print-block { display: block !important; }
    .card { border: none !important; box-shadow: none !important; }
}
</style>
@endsection
