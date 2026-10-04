<?php

namespace App\Services;

use App\Models\GuidanceAppointment;
use App\Models\GuidanceSetting;

class PsychologicalReportService
{
    public const DASS_COLUMNS = ['Very High', 'High', 'Average', 'Low', 'Very Low'];
    public const DASS_MAP = ['Normal' => 'Very Low', 'Mild' => 'Low', 'Moderate' => 'Average', 'Severe' => 'High', 'Extremely Severe' => 'Very High'];
    public const DESCRIPTIONS = [
        'Depression' => 'Feelings of hopelessness; tends to criticize oneself; feelings of deep sadness, distress, lack of interest/involvement, and devaluation of life',
        'Anxiety' => 'Experiences autonomic arousal such as cold sweat and rapid heartbeat, muscle tension, situational anxiety, and subjective experience of anxious affect',
        'Stress' => 'Difficulty relaxing, nervous arousal, tends to get upset / agitated easily, irritable / over-reactive and impatient',
    ];
    public const RECOMMENDATIONS = ['Fit for deployment', 'Fit for deployment with Reservation', 'For Counseling'];
    public const NOTE = 'NOTE: Should you wish to further understand the descriptions of the above remarks, you are advised to see the Psychometrician/Guidance Director/Psychologist.';

    public function build(GuidanceAppointment $appointment): array
    {
        $appointment->loadMissing(['applicant', 'serviceRequest', 'response', 'batch', 'sourceBatch']);
        $tests = $appointment->response?->testSummaries() ?? [];
        $levels = [];
        $dassRows = [];
        foreach (self::DESCRIPTIONS as $label => $description) {
            $key = strtolower($label);
            $severity = $this->severity($tests['dass21'] ?? [], $key, array_keys(self::DASS_MAP), 21);
            $levels[] = $severity;
            $dassRows[] = ['label' => $label, 'description' => $description, 'selected' => self::DASS_MAP[$severity ?? ''] ?? null];
        }
        $phqColumns = ['Minimal', 'Mild', 'Moderate', 'Moderately Severe', 'Severe'];
        $gadColumns = ['Minimal', 'Mild', 'Moderate', 'Severe'];
        $phq = $this->severity($tests['phq9'] ?? [], 'total', $phqColumns, 27);
        $gad = $this->severity($tests['gad7'] ?? [], 'total', $gadColumns, 21);
        $levels = array_merge($levels, [$phq, $gad]);
        $complete = ! in_array(null, $levels, true);
        $flagged = count(array_intersect($levels, ['Severe', 'Extremely Severe', 'Moderately Severe'])) > 0;
        $recommendation = $flagged ? 'For Counseling' : ($complete ? (count(array_diff($levels, ['Normal', 'Minimal'])) === 0 ? 'Fit for deployment' : 'Fit for deployment with Reservation') : null);

        $request = $appointment->serviceRequest;
        $batch = $appointment->batch ?? $appointment->sourceBatch;
        $section = $appointment->origin_section ?? $batch?->year_section ?? $request?->year_section;
        $blank = '____________________';

        return [
            'view' => 'guidance.psychological-report', 'orientation' => 'portrait',
            'appointment' => $appointment,
            'title' => 'Psychological Assessment Report',
            'fields' => [
                'Name' => trim(implode(' ', array_filter([$appointment->first_name, $appointment->middle_name, $appointment->last_name]))) ?: $blank,
                'Sex' => $appointment->applicant?->gender ?: ($request?->gender ?: $blank),
                'Course Yr. and Section' => trim($appointment->courseLabel().($section ? ' / '.$section : '')),
                'Test Administered' => 'Psychological Assessment',
                'Date of Examination' => $appointment->response?->created_at?->timezone('Asia/Manila')->format('F j, Y') ?? $blank,
                'Purpose' => $request?->purpose ?: ($batch?->reason_for_request ?: $blank),
            ],
            'matrices' => [
                ['title' => 'I. Summary of Depression, Anxiety, and Stress Scale (DASS-21)', 'columns' => self::DASS_COLUMNS, 'rows' => $dassRows],
                ['title' => 'II. Summary of Patient Health Questionnaire (PHQ-9)', 'columns' => $phqColumns, 'rows' => [['label' => 'PHQ-9', 'description' => '', 'selected' => $phq]]],
                ['title' => 'III. Summary of General Anxiety Disorder (GAD-7)', 'columns' => $gadColumns, 'rows' => [['label' => 'GAD-7', 'description' => '', 'selected' => $gad]]],
            ],
            // Prefer counselor-entered DB value; fall back to auto-computed
            'recommendation' => $appointment->recommendations ?: $recommendation,
            'complete' => $complete,
            // Remarks: counselor-entered narrative
            'remarks' => $appointment->remarks ?: '',
            'counselor' => GuidanceSetting::valueOf('counselor_name') ?: '',
            'orNumber' => $appointment->or_number ?: ($request?->or_number ?: $blank),
            'orDate' => ($appointment->or_date ?? $request?->or_date)?->format('F j, Y') ?? $blank,
        ];
    }

    private function severity(array $summary, string $scale, array $allowed, int $maximum): ?string
    {
        if (isset($summary['completion']) && $summary['completion'] !== 'Completed') {
            return null;
        }
        $score = $summary['scores'][$scale] ?? null;
        if (! is_numeric($score) || $score < 0 || $score > $maximum || (float) $score !== (float) (int) $score) {
            return null;
        }
        $severity = $summary['interpretation'][$scale === 'total' ? 'severity' : $scale] ?? null;

        return in_array($severity, $allowed, true) ? $severity : null;
    }
}
