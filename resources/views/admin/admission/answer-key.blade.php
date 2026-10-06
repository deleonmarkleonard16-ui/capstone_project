@extends('layouts.app')

@section('title', 'Admission Answer Key')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h1 class="h3 mb-1">Official Answer Key</h1>
        <p class="text-muted mb-0">Configure the admission exam answers and re-score existing submissions.</p>
    </div>
</div>

@if ($active)
    @php
        $keys = \Illuminate\Support\Facades\DB::table('admission_answer_keys')
            ->where('admission_cycle_id', $active->id)
            ->pluck('correct_answer', 'item_number');
        $configuredItems = (int) ($active->total_items ?: 80);
    @endphp

    <div class="card page-card shadow-sm">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h2 class="h6 section-title mb-0 fw-bold">
                <i class="bi bi-list-ol me-1"></i>{{ $active->displayName }}
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle ms-1" id="key-item-badge">{{ $configuredItems }} Items</span>
            </h2>
            <div class="d-flex align-items-center gap-2">
                <label class="form-label form-label-sm mb-0 fw-semibold text-muted" for="total_items_setter">Total Items:</label>
                <input type="number" id="total_items_setter" class="form-control form-control-sm" style="width:90px" min="10" max="200" value="{{ $configuredItems }}">
                <button type="button" class="btn btn-primary btn-sm" id="applyItemCount"><i class="bi bi-arrow-repeat me-1"></i> Apply</button>
            </div>
        </div>
        <div class="card-body p-4">
            <form method="post" action="{{ route('admin.admission.answer-key.save') }}" id="answerKeyForm">
                @csrf
                <input type="hidden" name="total_items" id="hidden_total_items" value="{{ $configuredItems }}">
                <div class="vertical-answer-grid" id="answerKeyGrid" data-total-items="{{ $configuredItems }}">
                    @for ($i = 1; $i <= $configuredItems; $i++)
                        <div class="vertical-answer-grid__item">
                            <div class="answer-choice-set" role="radiogroup" aria-label="Answer for item {{ $i }}">
                                <span class="input-group-text answer-choice-set__number">{{ $i }}</span>
                                @foreach (['A', 'B', 'C', 'D'] as $letter)
                                    <input class="btn-check" type="radio" name="answers[{{ $i }}]" id="answer-{{ $i }}-{{ $letter }}" value="{{ $letter }}" @checked(($keys[$i] ?? null) === $letter) @required($loop->first)>
                                    <label class="btn btn-outline-primary btn-sm answer-choice-set__option" for="answer-{{ $i }}-{{ $letter }}">{{ $letter }}</label>
                                @endforeach
                            </div>
                        </div>
                    @endfor
                </div>
                <div class="d-flex align-items-center gap-3 mt-3 flex-wrap">
                    <button class="btn btn-primary btn-sm"><i class="bi bi-save me-1"></i> Save Answer Key &amp; Re-Score</button>
                    <span class="small text-muted"><i class="bi bi-info-circle me-1"></i>Saving updates <strong id="item-count-label">{{ $configuredItems }}</strong> answer slots and re-scores all existing submissions.</span>
                </div>
            </form>
        </div>
    </div>
@else
    <div class="card page-card shadow-sm">
        <div class="card-body p-5 text-center text-muted">
            <i class="bi bi-lock-fill fs-1 mb-3 opacity-25"></i>
            <h2 class="h5 fw-bold">Answer Key Locked</h2>
            <p class="small mb-3">Initialize or activate an admission cycle before configuring its answer key.</p>
            <a href="{{ route('admin.admission.index') }}" class="btn btn-primary btn-sm">Go to Admission Cycle</a>
        </div>
    </div>
@endif
@endsection

@push('styles')
<style>
    /* Grid places 1–14 top-to-bottom, then starts the next column. */
    .vertical-answer-grid {
        display: grid;
        grid-template-columns: minmax(0, 520px);
        gap: .55rem;
        max-height: 620px;
        overflow: auto;
    }
    .answer-choice-set { display: flex; align-items: stretch; gap: .25rem; }
    .answer-choice-set__number { min-width: 36px; justify-content: center; }
    .answer-choice-set__option { min-width: 38px; }
    @media (max-width: 767.98px) {
        .vertical-answer-grid { grid-template-columns: minmax(0, 1fr); }
    }
</style>
@endpush

@push('scripts')
<script>
(() => {
    const grid = document.getElementById('answerKeyGrid');
    const setter = document.getElementById('total_items_setter');
    if (!grid || !setter) return;

    const hiddenInput = document.getElementById('hidden_total_items');
    const badge = document.getElementById('key-item-badge');
    const label = document.getElementById('item-count-label');
    const buildLegacyItem = (item, answer = '') => {
        const col = document.createElement('div');
        col.className = 'vertical-answer-grid__item';
        col.innerHTML = `<div class="input-group input-group-sm"><span class="input-group-text" style="min-width:36px">${item}</span><select name="answers[${item}]" class="form-select form-select-sm" required><option value="">–</option><option value="A">A</option><option value="B">B</option><option value="C">C</option><option value="D">D</option></select></div>`;
        col.querySelector('select').value = answer;
        return col;
    };

    const buildItem = (item, answer = '') => {
        const col = document.createElement('div');
        col.className = 'vertical-answer-grid__item';
        const choices = ['A', 'B', 'C', 'D'].map((letter, index) =>
            `<input class="btn-check" type="radio" name="answers[${item}]" id="answer-${item}-${letter}" value="${letter}" ${letter === answer ? 'checked' : ''} ${index === 0 ? 'required' : ''}>` +
            `<label class="btn btn-outline-primary btn-sm answer-choice-set__option" for="answer-${item}-${letter}">${letter}</label>`
        ).join('');
        col.innerHTML = `<div class="answer-choice-set" role="radiogroup" aria-label="Answer for item ${item}">` +
            `<span class="input-group-text answer-choice-set__number">${item}</span>${choices}</div>`;
        return col;
    };

    document.getElementById('applyItemCount').addEventListener('click', () => {
        const count = Number.parseInt(setter.value, 10);
        if (!Number.isInteger(count) || count < 10 || count > 200) return setter.classList.add('is-invalid');
        setter.classList.remove('is-invalid');

        const answers = [...grid.querySelectorAll('.vertical-answer-grid__item')]
            .map(item => item.querySelector('input[type="radio"]:checked')?.value || '');
        grid.replaceChildren(...Array.from({ length: count }, (_, index) => buildItem(index + 1, answers[index] || '')));
        grid.dataset.totalItems = count;
        hiddenInput.value = count;
        badge.textContent = `${count} Items`;
        label.textContent = count;
    });
})();
</script>
@endpush
