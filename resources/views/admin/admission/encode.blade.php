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
            Exam Score: <strong>{{ $applicant->exam_score ?? '—' }}</strong> ·
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
                <strong>{{ $applicant->exam_score !== null ? $applicant->exam_score . ' / 80' : 'Not yet scored' }}</strong>
            </div>
        </div>
    </div>
</div>

{{-- ── ANSWER ENCODING GRID ── --}}
<div class="card page-card shadow-sm">
    <div class="card-header bg-white py-3">
        <h2 class="h6 mb-0 fw-bold">
            <i class="bi bi-grid me-1"></i>
            Answer Grid — 80 Items
            <span class="badge bg-secondary ms-2 fw-normal" id="filled-count">0 / 80 filled</span>
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

            {{-- Horizontal answer grid: each row = one question, columns = A B C D --}}
            <div class="row g-0" id="answer-grid">
                @php $existingAnswers = old('answers', []); @endphp
                @for ($i = 1; $i <= 80; $i++)
                <div class="col-12 col-md-6 col-xl-4">
                    <div class="d-flex align-items-center border-bottom py-2 px-3 answer-row {{ isset($existingAnswers[$i]) && $existingAnswers[$i] ? 'answered' : '' }}"
                         data-item="{{ $i }}">
                        <span class="text-muted fw-semibold me-3 fs-7" style="min-width:60px; font-size:13px;">
                            Item {{ $i }}
                        </span>
                        <div class="d-flex gap-2">
                            @foreach(['A','B','C','D'] as $letter)
                            <div class="form-check form-check-inline mb-0">
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
                </div>
                @endfor
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

@push('scripts')
<script>
// ── Live filled counter ──
function updateCount() {
    const filled = document.querySelectorAll('.answer-radio:checked').length;
    document.getElementById('filled-count').textContent = `${filled} / 80 filled`;
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
<style>
.answer-row { transition: background 0.15s; }
.answer-row.answered { background: #f0fdf4; }
</style>
@endpush

@endsection
