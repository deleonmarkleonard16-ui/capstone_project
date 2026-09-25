<?php

namespace App\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class GuidanceTestScoringService
{
    public const LABELS = ['dass21' => 'Psychological Assessment', 'phq9' => 'Psychological Assessment', 'gad7' => 'Psychological Assessment', 'bfpi' => 'BFPI', 'career' => 'Career Test', 'exit' => 'Exit Form'];

    /**
     * DASS-21 4-point rating scale (0–3).
     * 0 = Did not apply to me at all — NEVER
     * 1 = Applied to me to some degree, or some of the time — SOMETIMES
     * 2 = Applied to me to a considerable degree, or a good part of time — OFTEN
     * 3 = Applied to me very much, or most of the time — ALMOST ALWAYS
     */
    public const DASS21_CHOICES = [
        0 => 'Never',
        1 => 'Sometimes',
        2 => 'Often',
        3 => 'Almost Always',
    ];

    /**
     * PHQ-9 & GAD-7 4-point frequency scale (0–3) — over the last 2 weeks.
     * 0 = Not at all
     * 1 = Several days
     * 2 = More than half the days
     * 3 = Nearly every day
     */
    public const PHQ_GAD_CHOICES = [
        0 => 'Not at all',
        1 => 'Several days',
        2 => 'More than half the days',
        3 => 'Nearly every day',
    ];

    /**
     * PHQ-9 Item 10 / GAD-7 difficulty rating (stored as `difficulty_rating`).
     * NOT included in the subscale total score — counselor reference only.
     */
    public const DIFFICULTY_CHOICES = [
        0 => 'Not difficult at all',
        1 => 'Somewhat difficult',
        2 => 'Very difficult',
        3 => 'Extremely difficult',
    ];

    /** Resolve a difficulty_rating value (0–3) to its human-readable label. */
    public static function difficultyLabel(int $value): string
    {
        return self::DIFFICULTY_CHOICES[$value] ?? "Unknown ($value)";
    }

    public function definition(string $test): array
    {
        if ($test === 'dass21') {
            return [
                'items'   => 21,
                'min'     => 0,
                'max'     => 3,
                'choices' => self::DASS21_CHOICES,
            ];
        }

        if (in_array($test, ['phq9', 'gad7'], true)) {
            return [
                'items'              => $test === 'phq9' ? 9 : 7,
                'min'                => 0,
                'max'                => 3,
                'choices'            => self::PHQ_GAD_CHOICES,
                'difficulty_choices' => self::DIFFICULTY_CHOICES,
            ];
        }
        $definition = config("guidance.$test", []);
        if (! isset(self::LABELS[$test]) || ($definition['items'] ?? 0) < 1) {
            throw ValidationException::withMessages(['test_type' => 'This paper instrument has not been configured by the guidance office.']);
        }
        if ($test === 'bfpi') {
            $items = range(1, $definition['items']);
            $mapped = array_merge([], ...array_values($definition['subscales']));
            if (! $mapped || array_diff($items, $mapped) || array_diff($mapped, $items)
                || in_array([], $definition['subscales'], true)
                || array_diff($definition['reverse_items'], $items)
                || ! in_array($definition['overlap'], ['Average', 'High'], true)) {
                throw ValidationException::withMessages(['test_type' => 'The BFPI subscale key is incomplete.']);
            }
            $definition += ['min' => 1, 'max' => 5];
        }
        if ($test === 'career' && (array_keys($definition['traits'] ?? []) !== range(1, $definition['items'])
            || array_keys($definition['questions'] ?? []) !== range(1, $definition['items']))) {
            throw ValidationException::withMessages(['test_type' => 'The Career questionnaire and trait key must cover every item.']);
        }
        return $definition;
    }

    public function score(string $test, array $answers): array
    {
        $definition = $this->definition($test);
        $keys = range(1, $definition['items']);
        $rules = ['answers' => ['required', 'array:'.implode(',', $keys), 'size:'.count($keys)]];
        foreach ($keys as $key) {
            $rules["answers.$key"] = ['required', $test === 'bfpi' ? 'numeric' : 'integer', 'between:'.$definition['min'].','.$definition['max']];
        }
        Validator::make(['answers' => $answers], $rules)->validate();
        $result = ['test_type' => $test, 'scoring_version' => '2026-09-18.1', 'scores' => [], 'interpretation' => []];
        if ($test === 'dass21') {
            foreach (['depression' => [[3,5,10,13,16,17,21], [4,6,10,13]], 'anxiety' => [[2,4,7,9,15,19,20], [3,5,7,9]], 'stress' => [[1,6,8,11,12,14,18], [7,9,12,16]]] as $scale => [$items, $bounds]) {
                $score = array_sum(array_map(fn ($item) => (int) $answers[$item], $items));
                $result['scores'][$scale] = $score;
                $result['interpretation'][$scale] = $this->category($score, $bounds, ['Normal', 'Mild', 'Moderate', 'Severe', 'Extremely Severe']);
            }
        } elseif (in_array($test, ['gad7', 'phq9'], true)) {
            $result['scores']['total'] = (int) array_sum($answers);
            if ($test === 'gad7') {
                $result['interpretation']['severity'] = $this->category($result['scores']['total'], [4,9,14], ['Minimal','Mild','Moderate','Severe']);
            } else {
                $result['interpretation']['severity'] = $this->phqSeverity($result['scores']['total']);
            }
        } elseif ($test === 'career') {
            $counts = [];
            foreach ($definition['traits'] as $item => $trait) {
                $result['scores'][$trait] = ($result['scores'][$trait] ?? 0) + (int) $answers[$item];
                $counts[$trait] = ($counts[$trait] ?? 0) + 1;
            }
            foreach ($counts as $trait => $count) {
                $mean = $result['scores'][$trait] / $count;
                $result['interpretation'][$trait] = $definition['choices'][(int) round($mean)];
            }
            // Stable ties preserve the original RIASEC order, matching the existing test.
            arsort($result['scores'], SORT_NUMERIC);
            $result['interpretation']['top_traits'] = implode(' - ', array_slice(array_keys($result['scores']), 0, 3));
            $result['interpretation']['review'] = 'Interest ratings, not clinical severity. Equal scores are listed in RIASEC order; review all tied traits.';
            $result['instrument_key'] = $definition;
        } elseif ($test === 'bfpi') {
            foreach ($definition['subscales'] as $scale => $items) {
                $rawSum = array_sum(array_map(fn ($i) => in_array($i, $definition['reverse_items']) ? 6 - (int) $answers[$i] : (int) $answers[$i], $items));
                $mean = round($rawSum / count($items), 2);
                $result['scores'][$scale] = $mean;
                $result['raw_sums'][$scale] = $rawSum;
                $result['interpretation'][$scale] = match (true) {
                    $mean >= 4.20 => 'Very High',
                    $mean >= 3.40 => 'High',
                    $mean >= 3.14 => $definition['overlap'],
                    $mean >= 2.60 => 'Average',
                    $mean >= 1.80 => 'Low',
                    default => 'Very Low',
                };
            }
            $result['instrument_key'] = $definition;
        } else {
            $result['interpretation']['review'] = 'Recorded for counselor review; no scoring rubric supplied.';
        }
        return $result;
    }

    public function phqSeverity(int $score): string
    {
        return $this->category($score, [4, 9, 14, 19], ['Minimal', 'Mild', 'Moderate', 'Moderately Severe', 'Severe']);
    }

    private function category(int $score, array $bounds, array $labels): string
    {
        foreach ($bounds as $index => $bound) {
            if ($score <= $bound) return $labels[$index];
        }
        return $labels[count($bounds)];
    }
}
