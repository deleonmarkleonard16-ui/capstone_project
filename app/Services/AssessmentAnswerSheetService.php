<?php

namespace App\Services;

use App\Models\GuidanceAppointment;

class AssessmentAnswerSheetService
{
    public function build(GuidanceAppointment $appointment): array
    {
        $appointment->loadMissing(['applicant', 'serviceRequest', 'response', 'batch', 'sourceBatch']);
        $request = $appointment->serviceRequest;
        $batch = $appointment->batch ?? $appointment->sourceBatch;
        $section = $appointment->origin_section ?? $batch?->year_section ?? $request?->year_section;
        $answers = $appointment->response?->answers ?? [];
        $titles = ['bfpi' => 'BIG FIVE PERSONALITY TEST', 'career' => 'CAREER INTEREST TEST (RIASEC)', 'dass21' => 'DASS-21', 'phq9' => 'PHQ-9', 'gad7' => 'GAD-7'];
        $sheets = [];
        foreach ($appointment->testTypes() as $test) {
            if (! isset($titles[$test])) continue;
            $definition = app(GuidanceTestScoringService::class)->definition($test);
            $raw = $answers[$test] ?? (count($appointment->testTypes()) === 1 ? $answers : []);
            $raw = is_array($raw) ? $raw : [];
            $columns = match ($test) {
                'bfpi' => ['O(10)', 'C(9)', 'E(8)', 'A(9)', 'N(8)'],
                'career' => ['R', 'I', 'A', 'S', 'E', 'C'],
                'dass21' => ['D', 'A', 'S'],
                default => ['Score'],
            };
            $rows = [];
            for ($item = 1; $item <= $definition['items']; $item++) {
                $selected = null;
                foreach ($definition['choices'] as $value => $label) {
                    if (isset($raw[$item]) && is_scalar($raw[$item]) && (string) $raw[$item] === (string) $value) $selected = $value;
                }
                $rows[] = ['number' => $item, 'selected' => $selected];
            }
            $sheets[] = [
                'test' => $test, 'title' => $titles[$test], 'choices' => $definition['choices'],
                'headings' => $test === 'bfpi' ? [1 => 'D', 2 => 'SD', 3 => 'N', 4 => 'SA', 5 => 'A'] : array_combine(array_keys($definition['choices']), array_keys($definition['choices'])),
                'columns' => $columns, 'rows' => $rows,
                'difficulty' => isset($definition['difficulty_choices'], $raw['difficulty_rating']) ? ($definition['difficulty_choices'][$raw['difficulty_rating']] ?? null) : null,
            ];
        }

        return [
            'view' => 'guidance.answer-sheet', 'orientation' => 'portrait', 'sheets' => $sheets,
            // Half of US Letter, split lengthwise: 4.25 x 11 inches, in PDF points.
            'paperSize' => [0, 0, 306, 792],
            'fields' => [
                'Name' => trim(implode(' ', array_filter([$appointment->first_name, $appointment->middle_name, $appointment->last_name]))),
                'Date' => $appointment->response?->created_at?->timezone('Asia/Manila')->format('F j, Y'),
                'Course, Yr. & Section' => trim($appointment->courseLabel().($section ? ' / '.$section : '')),
                'Sex' => $appointment->applicant?->gender ?: $request?->gender,
                'Purpose' => $request?->purpose ?: $batch?->reason_for_request,
                'OR No.' => $appointment->or_number ?: $request?->or_number,
            ],
        ];
    }
}
