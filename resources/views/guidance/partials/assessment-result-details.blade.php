@php
    $resultTests = $appointment->response?->testSummaries() ?? [];
    $recordedAnswers = $appointment->response?->answers ?? [];
    $instrumentNames = ['dass21' => 'DASS-21', 'phq9' => 'PHQ-9', 'gad7' => 'GAD-7', 'bfpi' => 'BFPI', 'career' => 'RIASEC'];
@endphp
@if($resultTests)
<div class="assessment-result-details">
    <h2>Assessment Rubric &amp; Interpretation</h2>
    @foreach($resultTests as $testKey => $testData)
        <h3>{{ \App\Services\GuidanceTestScoringService::LABELS[$testKey] ?? $testKey }} — {{ $instrumentNames[$testKey] ?? strtoupper($testKey) }}</h3>
        @if(isset($testData['completion']))
            <p>{{ $testData['completion'] }} ({{ $testData['answered_items'] ?? 0 }}/{{ $testData['total_items'] ?? 0 }} items)</p>
        @endif
        <table class="result-detail-table">
            <thead><tr><th>Scale / Trait</th><th>Score</th><th>Raw Sum</th><th>Interpretation</th></tr></thead>
            <tbody>
            @forelse(($testData['scores'] ?? []) as $scale => $score)
                <tr>
                    <td>{{ $scale === 'total' ? 'Total Score' : ucfirst($scale) }}</td>
                    <td>{{ is_scalar($score) ? $score : 'Not scored' }}@if($scale === 'total' && in_array($testKey, ['gad7', 'phq9'], true)) / {{ $testKey === 'gad7' ? 21 : 27 }}@endif</td>
                    <td>{{ $testData['raw_sums'][$scale] ?? '—' }}</td>
                    <td>{{ $testData['interpretation'][$scale === 'total' ? 'severity' : $scale] ?? 'For counselor review' }}</td>
                </tr>
            @empty
                <tr><td colspan="4">No scored results available.</td></tr>
            @endforelse
            </tbody>
        </table>
        @if($testKey === 'career' && isset($testData['interpretation']['top_traits']))<p><strong>Top RIASEC Traits:</strong> {{ $testData['interpretation']['top_traits'] }}</p>@endif
        @if(isset($testData['interpretation']['review']))<p>{{ $testData['interpretation']['review'] }}</p>@endif
    @endforeach
    @if(array_intersect(['dass21', 'phq9', 'gad7'], array_keys($resultTests)))
        <p class="note">Psychological Assessment scores are raw subscale sums. Screening severity is for counselor review.</p>
    @endif

    <h2>Raw Item Choices</h2>
    @foreach($resultTests as $testKey => $testData)
        @php
            $definition = app(\App\Services\GuidanceTestScoringService::class)->answerSheetDefinition($testKey);
            $rawAnswers = $recordedAnswers[$testKey] ?? (!$appointment->test_types ? $recordedAnswers : []);
            $itemCount = $testData['total_items'] ?? $definition['items'];
        @endphp
        <h3>{{ $instrumentNames[$testKey] ?? strtoupper($testKey) }}</h3>
        <table class="result-detail-table raw-item-choices">
            <thead><tr><th>Item</th><th>Selected Choice</th></tr></thead>
            <tbody>
            @for($item = 1; $item <= $itemCount; $item++)
                <tr><td>{{ $item }}</td><td>{{ isset($rawAnswers[$item]) && is_scalar($rawAnswers[$item]) ? $rawAnswers[$item] : 'Unanswered' }}</td></tr>
            @endfor
            </tbody>
        </table>
    @endforeach
</div>
@endif
