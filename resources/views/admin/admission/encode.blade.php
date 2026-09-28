@extends('layouts.app')
@section('content')

{{-- ══════════════════════════════════════════════════════════
     PSU-CAT PAPER ANSWER SHEET ENCODER
     Allows proctor to manually key in scanned OMR answers
     for a specific applicant. The answer grid mirrors the
     digital exam layout (horizontal A B C D per item).
     ══════════════════════════════════════════════════════════ --}}

<div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
    <div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-1 small">
                <li class="breadcrumb-item"><a href="{{ route('admin.admission.index') }}">Admission Setup</a></li>
                <li class="breadcrumb-item"><a href="{{ route('admin.admission.masterlist') }}">Masterlist</a></li>
                <li class="breadcrumb-item active">Paper Encode</li>
            </ol>
        </nav>
        <h1 class="h3 mb-1"><i class="bi bi-pencil-square me-2"></i>Paper Answer Sheet Encoder</h1>
        <p class="text-muted mb-0 small">
            Encoding for:
            <strong class="text-dark">{{ mb_strtoupper($applicant->full_name) }}</strong>
            &nbsp;·&nbsp;
            <code>{{ $applicant->application_number }}</code>
            &nbsp;·&nbsp;
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle">{{ $applicant->course_choice }}</span>
            &nbsp;·&nbsp;
            <span class="badge bg-info-subtle text-info border border-info-subtle">{{ $totalItems }} Items</span>
        </p>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary btn-sm"
           href="{{ route('admin.admission.paper', $applicant) }}"
           target="_blank">
            <i class="bi bi-printer me-1"></i> Print OMR Sheet
        </a>
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.admission.masterlist') }}">
            <i class="bi bi-arrow-left me-1"></i> Back to Masterlist
        </a>
    </div>
</div>

@if ($applicant->submitted_at)
    <div class="alert alert-info d-flex align-items-center gap-2 mb-4">
        <i class="bi bi-check-circle-fill fs-5"></i>
        <div>
            This applicant's answers were <strong>already submitted</strong>
            ({{ $applicant->submitted_at->format('M d, Y g:i A') }}).
            Exam Score: <strong>{{ $applicant->exam_score ?? '—' }} / {{ $totalItems }}</strong> ·
            Stanine: <strong>{{ $applicant->stanine_score ?? '—' }}</strong> ·
            Status: <strong>{{ $applicant->qualification_status ?? 'Pending' }}</strong>
        </div>
    </div>
@endif

{{-- ── APPLICANT INFO CARD ── --}}
<div class="card page-card shadow-sm mb-4">
    <div class="card-body py-3">
        <div class="row g-3 align-items-center">
            <div class="col-md-3 small">
                <span class="text-muted d-block">GWA</span>
                <strong>{{ $applicant->gwa ?? '—' }}</strong>
            </div>
            <div class="col-md-3 small">
                <span class="text-muted d-block">Session</span>
                <strong>{{ $applicant->session_label ?? '—' }}</strong>
            </div>
            <div class="col-md-3 small">
                <span class="text-muted d-block">Special Group</span>
                <strong>{{ $applicant->special_group ?? 'None' }}</strong>
            </div>
            <div class="col-md-3 small">
                <span class="text-muted d-block">Current Score</span>
                <strong>{{ $applicant->exam_score !== null ? $applicant->exam_score . ' / ' . $totalItems : 'Not yet scored' }}</strong>
            </div>
        </div>
    </div>
</div>

{{-- ── ANSWER ENCODING GRID ── --}}
<div class="card page-card shadow-sm">
    <div class="card-header bg-white py-3">
        <h2 class="h6 mb-0 fw-bold">
            <i class="bi bi-grid me-1"></i>
            Answer Grid — {{ $totalItems }} Items
            <span class="badge bg-secondary ms-2 fw-normal" id="filled-count">0 / {{ $totalItems }} filled</span>
        </h2>
    </div>
    <div class="card-body p-4">

        @if (session('success'))
            <div class="alert alert-success d-flex align-items-center gap-2">
                <i class="bi bi-check-circle-fill"></i>
                {{ session('success') }}
            </div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('admin.admission.encode.submit', $applicant) }}" id="encodeForm">
            @csrf

            <div class="answer-grid-scroll">
                {{-- Explicit groups keep numbering vertical: 1–20, 21–40, etc. --}}
                <div class="vertical-answer-grid" id="answer-grid" data-total-items="{{ $totalItems }}">
                    @php
                        $existingAnswers = old('answers', []);
                        $columnCount = min(4, max(1, (int) ceil($totalItems / 10)));
                        $itemsPerColumn = (int) ceil($totalItems / $columnCount);
                    @endphp
                    @for ($column = 0; $column < $columnCount; $column++)
                    <div class="answer-column">
                        @for ($i = ($column * $itemsPerColumn) + 1; $i <= min(($column + 1) * $itemsPerColumn, $totalItems); $i++)
                        <div class="answer-row {{ isset($existingAnswers[$i]) && $existingAnswers[$i] ? 'answered' : '' }}" data-item="{{ $i }}">
                            <span class="answer-item-label">Item {{ $i }}</span>
                            <div class="answer-options" role="radiogroup" aria-label="Answer for item {{ $i }}">
                                @foreach(['A','B','C','D'] as $letter)
                                <div class="answer-option">
                                    <input class="form-check-input answer-radio"
                                           type="radio"
                                           name="answers[{{ $i }}]"
                                           id="q{{ $i }}_{{ $letter }}"
                                           value="{{ $letter }}"
                                           @checked(($existingAnswers[$i] ?? '') === $letter)>
                                    <label class="form-check-label fw-semibold" for="q{{ $i }}_{{ $letter }}">{{ $letter }}</label>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        @endfor
                    </div>
                    @endfor
                </div>
            </div>

            {{-- Action bar --}}
            <div class="d-flex align-items-center justify-content-between mt-4 gap-3 flex-wrap">
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="clearAllBtn">
                        <i class="bi bi-x-circle me-1"></i> Clear All
                    </button>
                </div>
                <button class="btn btn-success fw-semibold px-4"
                        type="submit"
                        onclick="return confirm('Submit these answers and compute the exam score for {{ $applicant->application_number }}? This cannot be undone.')">
                    <i class="bi bi-calculator me-1"></i> Score Answer Sheet
                </button>
            </div>
        </form>
    </div>
</div>

@push('styles')
<style>
    .answer-grid-scroll { width:100%; overflow-x:auto; padding:1rem; background:#fff; border:1px solid #e5e7eb; border-radius:.75rem; box-shadow:0 .125rem .25rem rgba(0,0,0,.04); -webkit-overflow-scrolling:touch; }
    .vertical-answer-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(180px, 1fr)); gap:1rem 1.5rem; min-width:min-content; }
    .answer-column { min-width:245px; padding:0 .5rem; border-left:1px solid #e5e7eb; }
    .answer-column:first-child { border-left:0; }
    .answer-row { display:flex; align-items:center; gap:.5rem; min-width:0; min-height:44px; overflow:hidden; padding:.5rem .375rem; border-bottom:1px solid #e5e7eb; transition:background .15s; }
    .answer-row.answered { background:#f0fdf4; }
    .answer-item-label { flex:0 0 58px; min-width:58px; color:#6c757d; font-size:13px; font-weight:600; white-space:nowrap; }
    .answer-options { display:inline-flex; align-items:center; flex-wrap:nowrap; gap:.55rem; min-width:0; white-space:nowrap; }
    .answer-option { display:inline-flex; align-items:center; flex:0 0 auto; gap:.25rem; margin:0 .125rem; }
    .answer-option .form-check-input { float:none; flex:0 0 auto; margin:0; }
    .answer-option .form-check-label { flex:0 0 auto; }
    @media (max-width:1023.98px) { .vertical-answer-grid { grid-template-columns:repeat(2, minmax(245px, 1fr)); } }
    @media (max-width:575.98px) { .answer-grid-scroll { padding:.75rem; } .vertical-answer-grid { grid-template-columns:minmax(245px, 1fr); gap:.75rem; } .answer-column { padding:0; border-left:0; } }
</style>
@endpush

@push('scripts')
<script>
const TOTAL_ITEMS = {{ (int)$totalItems }};
// ── Live filled counter ──
function updateCount() {
    const filled = document.querySelectorAll('.answer-radio:checked').length;
    document.getElementById('filled-count').textContent = `${filled} / ${TOTAL_ITEMS} filled`;
    document.querySelectorAll('.answer-row').forEach(row => {
        const item = row.dataset.item;
        const checked = row.querySelector(`input[name="answers[${item}]"]:checked`);
        row.classList.toggle('answered', !!checked);
    });
}

document.querySelectorAll('.answer-radio').forEach(r => r.addEventListener('change', updateCount));
document.getElementById('clearAllBtn').addEventListener('click', () => {
    if (!confirm('Clear all selected answers?')) return;
    document.querySelectorAll('.answer-radio').forEach(r => { r.checked = false; });
    updateCount();
});

// Init count for old() values
updateCount();
</script>
@endpush

@endsection
