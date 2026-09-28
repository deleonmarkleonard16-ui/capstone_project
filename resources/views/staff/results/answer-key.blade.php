@extends('layouts.app')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Session Answer Key</h1>
            <p class="text-muted mb-0">{{ $session->title }} | Passing score controls who appears in passed applicants.</p>
        </div>
        <a href="{{ route(auth()->user()->role.'.sessions.results.index', $session) }}" class="btn btn-outline-secondary">View Results</a>
    </div>

    <div class="card page-card">
        <div class="card-body p-4">
            <form method="POST" action="{{ route(auth()->user()->role.'.sessions.answer-key.update', $session) }}">
                @csrf
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label class="form-label">Passing Score</label>
                        <input type="number" class="form-control" name="passing_score" min="1" max="80" value="{{ old('passing_score', $answerKey->passing_score) }}" required>
                    </div>
                </div>

                <div class="vertical-session-grid" data-total-items="{{ count($questionNumbers) }}" style="--vertical-grid-rows: {{ (int) ceil(count($questionNumbers) / 6) }}">
                    @foreach ($questionNumbers as $number)
                        <div>
                            <div class="question-block">
                                <div class="fw-semibold mb-3">Question {{ $number }}</div>
                                @foreach (['A', 'B', 'C', 'D'] as $choice)
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="radio" name="q{{ $number }}" id="key{{ $number }}{{ $choice }}" value="{{ $choice }}" @checked(old("q{$number}", $answerKey->{"q{$number}"} ?? null) === $choice)>
                                        <label class="form-check-label" for="key{{ $number }}{{ $choice }}">{{ $choice }}</label>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="d-flex justify-content-end mt-4">
                    <button type="submit" class="btn btn-primary btn-lg">Save Answer Key</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('styles')
<style>
    .vertical-session-grid { display:grid; grid-auto-flow:column; grid-template-rows:repeat(var(--vertical-grid-rows), minmax(0, 1fr)); grid-auto-columns:minmax(0, 1fr); gap:1rem; overflow:auto; }
    @media (max-width: 767.98px) { .vertical-session-grid { grid-auto-columns:minmax(240px, 1fr); } }
</style>
@endpush
@push('scripts')
<script>
document.querySelectorAll('.vertical-session-grid').forEach((grid) => {
    const sync = () => { const columns = innerWidth < 576 ? 1 : innerWidth < 768 ? 2 : innerWidth < 1024 ? 4 : 6; grid.style.setProperty('--vertical-grid-rows', Math.ceil(Number(grid.dataset.totalItems) / columns)); };
    sync(); window.addEventListener('resize', sync);
});
</script>
@endpush
