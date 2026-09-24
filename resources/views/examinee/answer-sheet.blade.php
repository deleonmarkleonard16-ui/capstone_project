@extends('layouts.app')

@section('content')
<div class="row g-4">
    <div class="col-lg-3">
        <div class="timer-box sticky-top" style="top: 20px;">
            <div class="card page-card shadow-sm border-0">
                <div class="card-body p-4 text-center">
                    <p class="text-uppercase text-danger fw-bold small mb-1">Exam Timer</p>
                    <h1 class="display-6 fw-bold text-dark my-2 font-monospace" id="countdown">40:00</h1>
                    <p class="text-muted small mb-0">Locked automatically upon timer expiration.</p>
                </div>
            </div>
            
            <div class="card page-card shadow-sm border-0 mt-3 d-none d-lg-block">
                <div class="card-body p-3 small text-muted">
                    <div class="fw-bold text-dark mb-1"><i class="bi bi-info-circle me-1"></i> Paper-Based Mode</div>
                    Read items from your paper questionnaire and shade your corresponding answers below.
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-9">
        <div class="card page-card shadow-sm border-0">
            <div class="card-body p-4">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 pb-3 mb-4 border-bottom">
                    <div>
                        <h1 class="h4 mb-1 fw-bold text-primary">PSU-CAT Paper-Based Answer Sheet</h1>
                        <p class="text-muted small mb-0">
                            <strong>{{ $assignment->applicant->full_name }}</strong> &nbsp;·&nbsp;
                            <code>{{ $assignment->applicant->application_number }}</code> &nbsp;·&nbsp;
                            {{ $assignment->testSession->title }}
                        </p>
                    </div>
                    <div class="text-md-end">
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 fs-6">
                            80 Items (A, B, C, D)
                        </span>
                    </div>
                </div>

                <form method="POST" action="{{ route('answers.submit') }}" id="answerSheetForm">
                    @csrf
                    
                    {{-- ── VERTICAL MULTI-COLUMN ANSWER GRID ── --}}
                    @php
                        // Chunk 80 questions into 4 balanced vertical columns of 20 items each
                        $chunks = array_chunk($questionNumbers, 20);
                    @endphp

                    <div class="row g-3 vertical-sheet-container">
                        @foreach ($chunks as $colIndex => $chunk)
                            <div class="col-12 col-sm-6 col-xl-3">
                                <div class="vertical-column-card p-2 border rounded-3 bg-light-subtle h-100">
                                    <div class="text-center py-1 mb-2 border-bottom fw-bold small text-secondary bg-white rounded-2">
                                        Items {{ $chunk[0] }} – {{ end($chunk) }}
                                    </div>
                                    <div class="vertical-items-list">
                                        @foreach ($chunk as $number)
                                            <div class="d-flex align-items-center justify-content-between py-1 px-2 border-bottom vertical-item-row" data-item="{{ $number }}">
                                                <span class="fw-bold small text-muted font-monospace item-label" style="min-width: 32px;">
                                                    {{ str_pad($number, 2, '0', STR_PAD_LEFT) }}.
                                                </span>
                                                <div class="d-flex gap-1 gap-sm-2 choices-group">
                                                    @foreach (['A', 'B', 'C', 'D'] as $choice)
                                                        <label class="vertical-bubble-label mb-0" for="q{{ $number }}{{ $choice }}" title="Item {{ $number }}: {{ $choice }}">
                                                            <input class="form-check-input vertical-bubble-input" 
                                                                   type="radio" 
                                                                   name="q{{ $number }}" 
                                                                   id="q{{ $number }}{{ $choice }}" 
                                                                   value="{{ $choice }}" 
                                                                   @checked(old("q{$number}", $answerSheet->{"q{$number}"} ?? null) === $choice)>
                                                            <span class="bubble-text">{{ $choice }}</span>
                                                        </label>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top flex-wrap gap-2">
                        <div class="small text-muted">
                            <span id="answered-count-badge" class="badge bg-secondary">0 / 80 Answered</span>
                        </div>
                        <button type="submit" class="btn btn-primary px-4 fw-bold">
                            <i class="bi bi-check2-circle me-1"></i> Submit Answer Sheet
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
/* ── Vertical Column Answer Sheet Styling ── */
.vertical-item-row {
    transition: background-color 0.15s ease;
}
.vertical-item-row:hover {
    background-color: #f1f5f9;
}
.vertical-bubble-label {
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 50%;
    border: 1.5px solid #cbd5e1;
    background: #ffffff;
    font-size: 12px;
    font-weight: 700;
    color: #475569;
    user-select: none;
    transition: all 0.15s ease;
}
.vertical-bubble-label:hover {
    border-color: #3b82f6;
    color: #1d4ed8;
    background: #eff6ff;
}
.vertical-bubble-input {
    display: none !important;
}
.vertical-bubble-label:has(.vertical-bubble-input:checked) {
    background: #1e40af !important;
    border-color: #1e40af !important;
    color: #ffffff !important;
    box-shadow: 0 2px 4px rgba(30, 64, 175, 0.35);
}
@media print {
    .timer-box, button[type="submit"], header, nav { display: none !important; }
    .col-lg-9 { width: 100% !important; }
    .vertical-bubble-label { border-color: #000 !important; }
}
</style>
@endsection

@push('scripts')
    <script>
        const countdownEl = document.getElementById('countdown');
        const form = document.getElementById('answerSheetForm');
        const csrfToken = form.querySelector('input[name="_token"]').value;
        const endTime = new Date(@json($endTimeIso)).getTime();
        const saveProgressUrl = @json(route('answers.save-progress'));
        let hasSubmitted = false;
        let isFinalizing = false;
        let saveProgressTimeout = null;

        function updateAnsweredCount() {
            const answered = form.querySelectorAll('input[type="radio"]:checked').length;
            const badge = document.getElementById('answered-count-badge');
            if (badge) {
                badge.textContent = `${answered} / 80 Answered`;
                badge.className = answered === 80 ? 'badge bg-success' : (answered > 0 ? 'badge bg-primary' : 'badge bg-secondary');
            }
        }

        const saveProgress = async () => {
            if (hasSubmitted && !isFinalizing) {
                return;
            }

            const formData = new FormData(form);

            try {
                await fetch(saveProgressUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                        'Accept': 'application/json',
                    },
                    body: formData,
                });
            } catch (error) {
                console.error('Auto-save failed.', error);
            }
        };

        const queueSaveProgress = () => {
            updateAnsweredCount();
            clearTimeout(saveProgressTimeout);
            saveProgressTimeout = setTimeout(() => {
                void saveProgress();
            }, 300);
        };

        const renderCountdown = async () => {
            const now = Date.now();
            const diff = endTime - now;
            if (diff <= 0 && !hasSubmitted) {
                isFinalizing = true;
                countdownEl.textContent = '00:00';
                clearInterval(autoSaveInterval);
                await saveProgress();
                hasSubmitted = true;
                form.requestSubmit();
                return;
            }
            const totalSeconds = Math.floor(diff / 1000);
            const minutes = String(Math.floor(totalSeconds / 60)).padStart(2, '0');
            const seconds = String(totalSeconds % 60).padStart(2, '0');
            countdownEl.textContent = `${minutes}:${seconds}`;
        };

        form.addEventListener('submit', () => {
            hasSubmitted = true;
            clearInterval(autoSaveInterval);
        });

        form.querySelectorAll('input[type="radio"]').forEach((input) => {
            input.addEventListener('change', queueSaveProgress);
        });

        const autoSaveInterval = setInterval(() => {
            void saveProgress();
        }, 30000);

        updateAnsweredCount();
        void renderCountdown();
        setInterval(() => {
            void renderCountdown();
        }, 1000);
    </script>
@endpush
