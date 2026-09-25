@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-9 col-xl-8">
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-primary text-white py-3">
                    <h1 class="h4 fw-bold mb-0">{{ $testTitle ?? 'Guidance Testing & Assessment' }}</h1>
                    <small class="text-white-50">Digital Management System for Guidance Testing and Admission (DMSGTA)</small>
                </div>
                <div class="card-body p-4">
                    {{-- ── Rating Scale Legend / Instructions ── --}}
                    @if(($testType ?? 'dass21') === 'dass21')
                        <div class="alert alert-info border-0 shadow-sm mb-4">
                            <h6 class="fw-bold mb-2"><i class="bi bi-info-circle me-1"></i> DASS-21 Instructions (Past Week)</h6>
                            <p class="small mb-2">Please read each statement and select a score from <strong>0 to 3</strong> indicating how much the statement applied to you over the past week:</p>
                            <ul class="list-unstyled mb-0 small row g-1">
                                <li class="col-md-6"><span class="badge bg-secondary me-1">0</span> <strong>Never:</strong> Did not apply to me at all</li>
                                <li class="col-md-6"><span class="badge bg-secondary me-1">1</span> <strong>Sometimes:</strong> Applied to me to some degree, or some of the time</li>
                                <li class="col-md-6"><span class="badge bg-secondary me-1">2</span> <strong>Often:</strong> Applied to me to a considerable degree, or a good part of time</li>
                                <li class="col-md-6"><span class="badge bg-secondary me-1">3</span> <strong>Almost Always:</strong> Applied to me very much, or most of the time</li>
                            </ul>
                        </div>
                    @elseif(in_array(($testType ?? ''), ['phq9', 'gad7'], true))
                        <div class="alert alert-info border-0 shadow-sm mb-4">
                            <h6 class="fw-bold mb-2"><i class="bi bi-info-circle me-1"></i> {{ strtoupper($testType) }} Instructions (Last 2 Weeks)</h6>
                            <p class="small mb-2">Over the <strong>last 2 weeks</strong>, how often have you been bothered by any of the following problems?</p>
                            <ul class="list-unstyled mb-0 small row g-1">
                                <li class="col-md-6"><span class="badge bg-secondary me-1">0</span> <strong>Not at all</strong></li>
                                <li class="col-md-6"><span class="badge bg-secondary me-1">1</span> <strong>Several days</strong></li>
                                <li class="col-md-6"><span class="badge bg-secondary me-1">2</span> <strong>More than half the days</strong></li>
                                <li class="col-md-6"><span class="badge bg-secondary me-1">3</span> <strong>Nearly every day</strong></li>
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ $submitUrl ?? route('guidance.submit', $token ?? '') }}" id="takeTestForm">
                        @csrf

                        <div class="questions-list">
                            @foreach($questions as $i => $questionText)
                                <div class="card border rounded-3 p-3 mb-3 bg-light-subtle shadow-sm question-item" data-item="{{ $i }}">
                                    <div class="d-flex align-items-start mb-2">
                                        <span class="badge bg-primary rounded-pill me-2 fs-6 px-2 py-1">{{ $i }}</span>
                                        <p class="fw-semibold mb-0 text-dark pt-1">{{ $questionText }}</p>
                                    </div>

                                    @if(($testType ?? 'dass21') === 'dass21')
                                        <!-- DASS-21 Choice Options -->
                                        <div class="btn-group w-100 mb-1 mt-2" role="group" aria-label="Question {{ $i }} Choices">
                                            <input type="radio" class="btn-check" name="answers[dass21][{{ $i }}]" id="dass_{{ $i }}_0" value="0" @checked(old("answers.dass21.{$i}", $savedAnswers[$i] ?? null) === 0 || old("answers.dass21.{$i}", $savedAnswers[$i] ?? null) === '0') required>
                                            <label class="btn btn-outline-secondary py-2" for="dass_{{ $i }}_0">0 - Never</label>

                                            <input type="radio" class="btn-check" name="answers[dass21][{{ $i }}]" id="dass_{{ $i }}_1" value="1" @checked(old("answers.dass21.{$i}", $savedAnswers[$i] ?? null) === 1 || old("answers.dass21.{$i}", $savedAnswers[$i] ?? null) === '1')>
                                            <label class="btn btn-outline-secondary py-2" for="dass_{{ $i }}_1">1 - Sometimes</label>

                                            <input type="radio" class="btn-check" name="answers[dass21][{{ $i }}]" id="dass_{{ $i }}_2" value="2" @checked(old("answers.dass21.{$i}", $savedAnswers[$i] ?? null) === 2 || old("answers.dass21.{$i}", $savedAnswers[$i] ?? null) === '2')>
                                            <label class="btn btn-outline-secondary py-2" for="dass_{{ $i }}_2">2 - Often</label>

                                            <input type="radio" class="btn-check" name="answers[dass21][{{ $i }}]" id="dass_{{ $i }}_3" value="3" @checked(old("answers.dass21.{$i}", $savedAnswers[$i] ?? null) === 3 || old("answers.dass21.{$i}", $savedAnswers[$i] ?? null) === '3')>
                                            <label class="btn btn-outline-secondary py-2" for="dass_{{ $i }}_3">3 - Almost Always</label>
                                        </div>
                                    @elseif(in_array(($testType ?? ''), ['phq9', 'gad7'], true))
                                        <!-- PHQ-9 & GAD-7 Choice Options -->
                                        <div class="btn-group w-100 mb-1 mt-2" role="group" aria-label="Question {{ $i }} Choices">
                                            <input type="radio" class="btn-check" name="answers[{{ $testType }}][{{ $i }}]" id="phq_{{ $i }}_0" value="0" @checked(old("answers.{$testType}.{$i}", $savedAnswers[$i] ?? null) === 0 || old("answers.{$testType}.{$i}", $savedAnswers[$i] ?? null) === '0') required>
                                            <label class="btn btn-outline-secondary py-2" for="phq_{{ $i }}_0">0 - Not at all</label>

                                            <input type="radio" class="btn-check" name="answers[{{ $testType }}][{{ $i }}]" id="phq_{{ $i }}_1" value="1" @checked(old("answers.{$testType}.{$i}", $savedAnswers[$i] ?? null) === 1 || old("answers.{$testType}.{$i}", $savedAnswers[$i] ?? null) === '1')>
                                            <label class="btn btn-outline-secondary py-2" for="phq_{{ $i }}_1">1 - Several days</label>

                                            <input type="radio" class="btn-check" name="answers[{{ $testType }}][{{ $i }}]" id="phq_{{ $i }}_2" value="2" @checked(old("answers.{$testType}.{$i}", $savedAnswers[$i] ?? null) === 2 || old("answers.{$testType}.{$i}", $savedAnswers[$i] ?? null) === '2')>
                                            <label class="btn btn-outline-secondary py-2" for="phq_{{ $i }}_2">2 - More than half the days</label>

                                            <input type="radio" class="btn-check" name="answers[{{ $testType }}][{{ $i }}]" id="phq_{{ $i }}_3" value="3" @checked(old("answers.{$testType}.{$i}", $savedAnswers[$i] ?? null) === 3 || old("answers.{$testType}.{$i}", $savedAnswers[$i] ?? null) === '3')>
                                            <label class="btn btn-outline-secondary py-2" for="phq_{{ $i }}_3">3 - Nearly every day</label>
                                        </div>
                                    @endif
                                </div>
                            @endforeach

                            {{-- ── Optional PHQ-9 Item 10 / GAD-7 Difficulty Rating ── --}}
                            @if(in_array(($testType ?? ''), ['phq9', 'gad7'], true))
                                <div class="card border-primary border-2 rounded-3 p-3 mb-3 bg-light shadow-sm">
                                    <div class="mb-2">
                                        <span class="badge bg-primary me-2">Global Difficulty</span>
                                        <p class="fw-bold mb-1 text-dark d-inline">
                                            If you checked off any problems, how difficult have these problems made it for you to do your work, take care of things at home, or get along with other people?
                                        </p>
                                    </div>
                                    <div class="btn-group w-100 mb-1 mt-2" role="group" aria-label="Functional Difficulty Rating">
                                        <input type="radio" class="btn-check" name="answers[{{ $testType }}][difficulty_rating]" id="diff_0" value="0" @checked(old("answers.{$testType}.difficulty_rating", $savedAnswers['difficulty_rating'] ?? null) === 0 || old("answers.{$testType}.difficulty_rating", $savedAnswers['difficulty_rating'] ?? null) === '0')>
                                        <label class="btn btn-outline-secondary py-2" for="diff_0">Not difficult at all</label>

                                        <input type="radio" class="btn-check" name="answers[{{ $testType }}][difficulty_rating]" id="diff_1" value="1" @checked(old("answers.{$testType}.difficulty_rating", $savedAnswers['difficulty_rating'] ?? null) === 1 || old("answers.{$testType}.difficulty_rating", $savedAnswers['difficulty_rating'] ?? null) === '1')>
                                        <label class="btn btn-outline-secondary py-2" for="diff_1">Somewhat difficult</label>

                                        <input type="radio" class="btn-check" name="answers[{{ $testType }}][difficulty_rating]" id="diff_2" value="2" @checked(old("answers.{$testType}.difficulty_rating", $savedAnswers['difficulty_rating'] ?? null) === 2 || old("answers.{$testType}.difficulty_rating", $savedAnswers['difficulty_rating'] ?? null) === '2')>
                                        <label class="btn btn-outline-secondary py-2" for="diff_2">Very difficult</label>

                                        <input type="radio" class="btn-check" name="answers[{{ $testType }}][difficulty_rating]" id="diff_3" value="3" @checked(old("answers.{$testType}.difficulty_rating", $savedAnswers['difficulty_rating'] ?? null) === 3 || old("answers.{$testType}.difficulty_rating", $savedAnswers['difficulty_rating'] ?? null) === '3')>
                                        <label class="btn btn-outline-secondary py-2" for="diff_3">Extremely difficult</label>
                                    </div>
                                </div>
                            @endif
                        </div>

                        <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                            <span class="text-muted small" id="answeredCounter">All questions required</span>
                            <button type="submit" class="btn btn-success btn-lg px-4 fw-bold shadow-sm">
                                <i class="bi bi-check2-circle me-1"></i> Submit Final Answers
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
