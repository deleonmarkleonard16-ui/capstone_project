<?php

namespace App\Services;

use App\Models\GuidanceTestResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class GuidanceAnalyticsService
{
    public const SCALES = [
        'dass21.depression' => 'Psychological Assessment · Depression',
        'dass21.anxiety'    => 'Psychological Assessment · Anxiety',
        'dass21.stress'     => 'Psychological Assessment · Stress',
        'phq9.severity'     => 'Psychological Assessment · Mood',
        'gad7.severity'     => 'Psychological Assessment · Worry',
    ];

    /** Scales that apply to each module. Only psychological has scored scales currently. */
    public const MODULE_SCALES = [
        'psychological' => ['dass21.depression', 'dass21.anxiety', 'dass21.stress', 'phq9.severity', 'gad7.severity'],
        'personality'   => [],
        'career'        => [],
    ];

    private function responses(?string $moduleKey = null, ?string $course = null): Builder
    {
        // Archives remain part of the historical analytics population.
        $query = GuidanceTestResponse::whereHas(
            'appointment',
            fn (Builder $q) => $q->where('status', 'Completed'),
        );

        if ($moduleKey) {
            $query->whereHas('appointment', fn (Builder $q) => $q->where('test_category', $moduleKey));
        }
        if ($course) {
            $query->whereHas('appointment', fn (Builder $appointment) => $appointment
                ->whereHas('serviceRequest', fn (Builder $request) => $request->where('course', $course))
                ->orWhere('origin_course', $course)
                ->orWhereHas('batch', fn (Builder $batch) => $batch->where('course', $course))
                ->orWhereHas('sourceBatch', fn (Builder $batch) => $batch->where('course', $course)));
        }

        return $query;
    }

    private function severityExpression(string $scale): string
    {
        [$test, $dimension] = explode('.', $scale);
        $grammar    = (new GuidanceTestResponse)->getConnection()->getQueryGrammar();
        $nested     = $grammar->wrap("score_summary->tests->{$test}->interpretation->{$dimension}");
        $legacy     = $grammar->wrap("score_summary->interpretation->$dimension");
        $type       = $grammar->wrap('score_summary->test_type');
        $expression = "COALESCE($nested, CASE WHEN $type = '$test' THEN $legacy END)";
        if ($test === 'phq9') {
            // Older stored responses have PHQ totals but no severity; do not rewrite clinical records.
            $total       = $grammar->wrap('score_summary->tests->phq9->scores->total');
            $legacyTotal = $grammar->wrap('score_summary->scores->total');
            $score       = "COALESCE($total, CASE WHEN $type = 'phq9' THEN $legacyTotal END)";
            $expression  = "COALESCE($expression, CASE WHEN $score IS NULL THEN NULL WHEN CAST($score AS DECIMAL(10,2)) < 5 THEN 'Minimal' WHEN CAST($score AS DECIMAL(10,2)) < 10 THEN 'Mild' WHEN CAST($score AS DECIMAL(10,2)) < 15 THEN 'Moderate' WHEN CAST($score AS DECIMAL(10,2)) < 20 THEN 'Moderately Severe' ELSE 'Severe' END)";
        }
        return $expression;
    }

    public function distributions(string $moduleKey = 'psychological', ?string $course = null): array
    {
        if ($moduleKey === 'personality' || $moduleKey === 'career') {
            $test = $moduleKey === 'personality' ? 'bfpi' : 'career';
            $dimensions = $test === 'bfpi' ? array_keys(config('guidance.bfpi.subscales', [])) : array_values(array_unique(config('guidance.career.traits', [])));
            $series = [];
            foreach ($dimensions as $dimension) {
                $series[$test.'.'.$dimension] = ['label' => ucfirst($dimension), 'counts' => []];
            }
            foreach ($this->responses($moduleKey, $course)->get() as $response) {
                $interpretations = $response->testSummaries()[$test]['interpretation'] ?? [];
                foreach ($dimensions as $dimension) {
                    $value = $interpretations[$dimension] ?? null;
                    if ($value !== null) {
                        $key = $test.'.'.$dimension;
                        $series[$key]['counts'][$value] = ($series[$key]['counts'][$value] ?? 0) + 1;
                    }
                }
            }
            return $series;
        }
        $activeScaleKeys = self::MODULE_SCALES[$moduleKey] ?? [];
        if (empty($activeScaleKeys)) {
            return [];
        }

        $series = [];
        foreach ($activeScaleKeys as $key) {
            $label      = self::SCALES[$key];
            $expression = $this->severityExpression($key);
            $labels     = str_starts_with($key, 'dass21.') ? ['Normal', 'Mild', 'Moderate', 'Severe', 'Extremely Severe']
                : ($key === 'phq9.severity' ? ['Minimal', 'Mild', 'Moderate', 'Moderately Severe', 'Severe'] : ['Minimal', 'Mild', 'Moderate', 'Severe']);
            $projected = $this->responses($moduleKey, $course)->selectRaw("$expression AS severity");
            $counts    = DB::query()->fromSub($projected, 'scored_responses')->whereNotNull('severity')
                ->selectRaw('severity, COUNT(*) AS total')->groupBy('severity')->pluck('total', 'severity');
            $series[$key] = ['label' => $label, 'counts' => array_map(fn ($severity) => (int) ($counts[$severity] ?? 0), array_combine($labels, $labels))];
        }
        return $series;
    }

    public function redFlags(string $moduleKey = 'psychological', ?string $course = null): Builder
    {
        $activeScaleKeys = self::MODULE_SCALES[$moduleKey] ?? [];

        if (empty($activeScaleKeys)) {
            // Return an empty builder (no results) for modules with no configured scales.
            return GuidanceTestResponse::whereRaw('1 = 0');
        }

        return $this->responses($moduleKey, $course)->where(function (Builder $query) use ($activeScaleKeys) {
            foreach ($activeScaleKeys as $scale) {
                $query->orWhereRaw($this->severityExpression($scale) . ' IN (?, ?)', ['Severe', 'Extremely Severe']);
            }
        })->with(['applicant', 'appointment.serviceRequest'])->latest('guidance_test_response_id');
    }

    public function totals(string $moduleKey = 'psychological', ?string $course = null): array
    {
        return [
            'responses' => $this->responses($moduleKey, $course)->count(),
            'flagged'   => $this->redFlags($moduleKey, $course)->count(),
        ];
    }

    public function sectionTotals(string $moduleKey, ?string $course = null): array
    {
        $totals = [];
        foreach ($this->responses($moduleKey, $course)->with(['appointment.batch', 'appointment.sourceBatch'])->get() as $response) {
            $appointment = $response->appointment;
            $batch = $appointment->sourceBatch ?: $appointment->batch;
            if (!$batch) continue;
            $key = (string) $batch->getKey();
            if (!isset($totals[$key])) $totals[$key] = [
                'batch' => $batch->batch_name,
                'section' => $appointment->origin_section ?: $batch->year_section ?: 'Unspecified',
                'course' => $appointment->origin_course ?: $batch->course,
                'completed' => 0,
                'makeup' => 0,
            ];
            $totals[$key]['completed']++;
            if ($appointment->source_batch_id) $totals[$key]['makeup']++;
        }
        return array_values($totals);
    }
}
