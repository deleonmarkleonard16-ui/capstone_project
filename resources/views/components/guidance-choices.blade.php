@props([
    'testType' => 'dass21', // 'dass21', 'phq9', 'gad7', 'bfpi', 'career'
    'itemIndex' => 1,
    'inputName' => null,
    'selectedValue' => null,
    'required' => true,
])

@php
    $name = $inputName ?? "answers[{$testType}][{$itemIndex}]";
    $prefix = match($testType) {
        'dass21' => 'dass',
        'phq9' => 'phq',
        'gad7' => 'gad',
        default => $testType,
    };
    $idPrefix = "{$prefix}_{$itemIndex}";
@endphp

@if($testType === 'dass21')
    <!-- DASS-21 Choice Options (4-point rating scale from 0 to 3) -->
    <div class="btn-group w-100 mb-3" role="group" aria-label="Item {{ $itemIndex }} Choice Options">
        <input type="radio" class="btn-check" name="{{ $name }}" id="{{ $idPrefix }}_0" value="0" @checked((string)$selectedValue === '0') {{ $required ? 'required' : '' }}>
        <label class="btn btn-outline-secondary" for="{{ $idPrefix }}_0" title="Did not apply to me at all — NEVER">0 - Never</label>

        <input type="radio" class="btn-check" name="{{ $name }}" id="{{ $idPrefix }}_1" value="1" @checked((string)$selectedValue === '1')>
        <label class="btn btn-outline-secondary" for="{{ $idPrefix }}_1" title="Applied to me to some degree, or some of the time — SOMETIMES">1 - Sometimes</label>

        <input type="radio" class="btn-check" name="{{ $name }}" id="{{ $idPrefix }}_2" value="2" @checked((string)$selectedValue === '2')>
        <label class="btn btn-outline-secondary" for="{{ $idPrefix }}_2" title="Applied to me to a considerable degree, or a good part of time — OFTEN">2 - Often</label>

        <input type="radio" class="btn-check" name="{{ $name }}" id="{{ $idPrefix }}_3" value="3" @checked((string)$selectedValue === '3')>
        <label class="btn btn-outline-secondary" for="{{ $idPrefix }}_3" title="Applied to me very much, or most of the time — ALMOST ALWAYS">3 - Almost Always</label>
    </div>

@elseif($testType === 'phq9' || $testType === 'gad7')
    <!-- PHQ-9 & GAD-7 Choice Options (4-point frequency scale from 0 to 3 over last 2 weeks) -->
    <div class="btn-group w-100 mb-3" role="group" aria-label="Item {{ $itemIndex }} Choice Options">
        <input type="radio" class="btn-check" name="{{ $name }}" id="{{ $idPrefix }}_0" value="0" @checked((string)$selectedValue === '0') {{ $required ? 'required' : '' }}>
        <label class="btn btn-outline-secondary" for="{{ $idPrefix }}_0" title="Not at all">0 - Not at all</label>

        <input type="radio" class="btn-check" name="{{ $name }}" id="{{ $idPrefix }}_1" value="1" @checked((string)$selectedValue === '1')>
        <label class="btn btn-outline-secondary" for="{{ $idPrefix }}_1" title="Several days">1 - Several days</label>

        <input type="radio" class="btn-check" name="{{ $name }}" id="{{ $idPrefix }}_2" value="2" @checked((string)$selectedValue === '2')>
        <label class="btn btn-outline-secondary" for="{{ $idPrefix }}_2" title="More than half the days">2 - More than half the days</label>

        <input type="radio" class="btn-check" name="{{ $name }}" id="{{ $idPrefix }}_3" value="3" @checked((string)$selectedValue === '3')>
        <label class="btn btn-outline-secondary" for="{{ $idPrefix }}_3" title="Nearly every day">3 - Nearly every day</label>
    </div>

@elseif($testType === 'difficulty')
    <!-- PHQ-9 Item 10 and GAD-7 Difficulty Rating Choices -->
    <div class="btn-group w-100 mb-3" role="group" aria-label="Functional Difficulty Choices">
        <input type="radio" class="btn-check" name="{{ $name }}" id="{{ $idPrefix }}_0" value="0" @checked((string)$selectedValue === '0') {{ $required ? 'required' : '' }}>
        <label class="btn btn-outline-secondary" for="{{ $idPrefix }}_0">Not difficult at all</label>

        <input type="radio" class="btn-check" name="{{ $name }}" id="{{ $idPrefix }}_1" value="1" @checked((string)$selectedValue === '1')>
        <label class="btn btn-outline-secondary" for="{{ $idPrefix }}_1">Somewhat difficult</label>

        <input type="radio" class="btn-check" name="{{ $name }}" id="{{ $idPrefix }}_2" value="2" @checked((string)$selectedValue === '2')>
        <label class="btn btn-outline-secondary" for="{{ $idPrefix }}_2">Very difficult</label>

        <input type="radio" class="btn-check" name="{{ $name }}" id="{{ $idPrefix }}_3" value="3" @checked((string)$selectedValue === '3')>
        <label class="btn btn-outline-secondary" for="{{ $idPrefix }}_3">Extremely difficult</label>
    </div>

@else
    <!-- Generic 1-5 Scale -->
    <div class="btn-group w-100 mb-3" role="group" aria-label="Item {{ $itemIndex }} Choices">
        @for($val = 1; $val <= 5; $val++)
            <input type="radio" class="btn-check" name="{{ $name }}" id="{{ $idPrefix }}_{{ $val }}" value="{{ $val }}" @checked((string)$selectedValue === (string)$val) {{ $required ? 'required' : '' }}>
            <label class="btn btn-outline-secondary" for="{{ $idPrefix }}_{{ $val }}">{{ $val }}</label>
        @endfor
    </div>
@endif
