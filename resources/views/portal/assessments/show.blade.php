@extends('portal.layout')

@section('content')
<link rel="stylesheet" href="{{ asset('css/answer-sheet.css') }}">
<div class="mb-4">
    <a href="{{ route('portal.track', ['reference' => $entry->reference]) }}" class="button secondary">&larr; Back to Request Tracker</a>
</div>

<section class="card shadow-sm p-4 border-0">
    <div class="request-banner mb-3">{{ $testTitle }}</div>
    <h2 class="h4 fw-bold mb-1">Digital Answer Sheet</h2>
    <p class="text-muted small mb-4">Requester: <strong>{{ strtoupper(trim($entry->first_name.' '.$entry->last_name)) }}</strong> | Reference: <code>{{ $entry->reference }}</code></p>

    @if(session('success'))
        <div class="alert alert-success d-flex align-items-center mb-4" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>
            <div>{{ session('success') }}</div>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <div>{{ session('error') }}</div>
        </div>
    @endif

    @if($submission)
        <div class="alert alert-success">Assessment completed. Your saved responses have been recorded for Guidance Office review. Please wait for the printed result that will be provided by the Guidance Office.</div>
    @endif
    <form method="POST" action="{{ route('guidance.test.submit', ['reference' => $entry->reference, 'test' => $test]) }}">
        @csrf

        <div class="answer-sheet-matrix" style="--vertical-grid-rows: {{ (int) ceil($definition['items'] / 2) }}">
            @for($item = 1; $item <= $definition['items']; $item++)
                <x-answer-sheet-item :item="$item" :name="'answers['.$item.']'" :choices="range($definition['min'], $definition['max'])" :selected="old('answers.'.$item, $submission?->answers[$item] ?? null)" :id-prefix="$test" />
            @endfor
            @if(in_array($test, ['phq9', 'gad7'], true))
                <x-answer-sheet-item :item="$definition['items'] + 1" name="difficulty_rating" :choices="range(0, 3)" :selected="old('difficulty_rating', $submission?->interpretation['difficulty_rating'] ?? null)" id-prefix="difficulty" :required="false" />
            @endif
        </div>

        <div class="d-flex justify-content-end gap-2 pt-3 border-top">
            <button type="submit" class="btn btn-primary btn-lg px-4 fw-bold">
                <i class="bi bi-send-check me-1"></i> {{ $submission ? 'Update Responses' : 'Submit Assessment' }}
            </button>
        </div>
    </form>
    <div class="small mt-3">Student ID: {{ $entry->student_number ?? 'N/A' }} | Course: {{ $entry->courseLabel() }} | O.R. Number: {{ $entry->guidanceAppointments->first()?->or_number ?: 'N/A' }}</div>
</section>
@endsection

