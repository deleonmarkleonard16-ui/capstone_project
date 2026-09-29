@props(['item', 'name', 'choices', 'selected' => null, 'idPrefix' => 'answer', 'required' => true, 'cardClass' => 'item-card', 'cardId' => null])
<div class="answer-sheet-row {{ $cardClass }}" @if($cardId) id="{{ $cardId }}" @endif data-item="{{ $item }}">
    <span class="answer-sheet-item">Item #{{ str_pad($item, 2, '0', STR_PAD_LEFT) }}</span>
    <div class="answer-sheet-options" role="radiogroup" aria-label="Item #{{ str_pad($item, 2, '0', STR_PAD_LEFT) }}">
        @foreach($choices as $choice)
            <label class="answer-sheet-choice" for="{{ $idPrefix }}-{{ $item }}-{{ $choice }}">
                <input type="radio" id="{{ $idPrefix }}-{{ $item }}-{{ $choice }}" name="{{ $name }}" value="{{ $choice }}" @checked($selected !== null && (string) $selected === (string) $choice) @required($required)>
                <span>{{ $choice }}</span>
            </label>
        @endforeach
    </div>
</div>
