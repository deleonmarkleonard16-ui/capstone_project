@extends('layouts.app')

@section('content')
    <div class="row g-4">
        <div class="col-lg-3">
            <div class="timer-box">
                <div class="card page-card">
                    <div class="card-body">
                        <p class="text-uppercase text-danger fw-semibold mb-2">Global Exam Timer</p>
                        <h1 class="display-6 fw-bold" id="countdown">40:00</h1>
                        <p class="text-muted mb-0">The answer sheet will be locked automatically when the timer ends.</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-9">
            <div class="card page-card">
            <div class="card page-card">
                <div class="card-body p-4">
                    <div class="d-flex flex-wrap justify-content-between gap-3 mb-4">
                        <div>
                            <h1 class="h3 mb-1">Digital Answer Sheet</h1>
                            <p class="text-muted mb-0">{{ $assignment->applicant->full_name }} | {{ $assignment->testSession->title }}</p>
                        </div>
                        <div class="text-md-end">
                            <div class="fw-semibold">Exactly 80 items</div>
                            <div class="text-muted">Choose one answer per question: A, B, C, or D</div>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('answers.submit') }}" id="answerSheetForm">
                        @csrf
                        <div class="row row-cols-1 row-cols-md-2 row-cols-xl-4 g-3">
                            @foreach ($questionNumbers as $number)
                                <div class="col">
                                    <div class="question-block">
                                        <div class="fw-semibold mb-3">Question {{ $number }}</div>
                                        @foreach (['A', 'B', 'C', 'D'] as $choice)
                                            <div class="form-check mb-2">
                                                <input class="form-check-input" type="radio" name="q{{ $number }}" id="q{{ $number }}{{ $choice }}" value="{{ $choice }}" @checked(old("q{$number}", $answerSheet->{"q{$number}"} ?? null) === $choice)>
                                                <label class="form-check-label" for="q{{ $number }}{{ $choice }}">{{ $choice }}</label>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <div class="d-flex justify-content-end mt-4">
                            <button type="submit" class="btn btn-primary btn-lg">Submit Answer Sheet</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
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

        void renderCountdown();
        setInterval(() => {
            void renderCountdown();
        }, 1000);
    </script>
@endpush
