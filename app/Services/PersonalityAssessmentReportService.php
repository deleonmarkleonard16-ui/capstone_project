<?php

namespace App\Services;

use App\Models\GuidanceAppointment;
use App\Models\GuidanceSetting;

class PersonalityAssessmentReportService
{
    private const DESCRIPTIONS = [
        'Extraversion' => 'Energy and engagement when interacting with others and participating in social activities.',
        'Agreeableness' => 'Cooperation, consideration, trust, and concern for other people.',
        'Conscientiousness' => 'Organization, dependability, persistence, and attention to responsibilities.',
        'Neuroticism' => 'Emotional sensitivity and responses to stress and difficult situations.',
        'Openness' => 'Curiosity, imagination, creativity, and interest in new ideas and experiences.',
    ];

    private const LEVELS = [1 => 'Very Low', 2 => 'Low', 3 => 'Average', 4 => 'High', 5 => 'Very High'];

    public function build(GuidanceAppointment $appointment): array
    {
        $appointment->loadMissing(['applicant', 'serviceRequest', 'response', 'batch', 'sourceBatch']);
        $summary = $appointment->response?->testSummaries()['bfpi'] ?? [];
        $scores = $summary['scores'] ?? [];
        $rows = [];
        foreach (array_keys(self::DESCRIPTIONS) as $trait) {
            $score = $scores[$trait] ?? null;
            $score = is_numeric($score) && (float) $score >= 1 && (float) $score <= 5 ? (float) $score : null;
            $interpretation = $summary['interpretation'][$trait] ?? null;
            $levelKey = array_search($interpretation, self::LEVELS, true);
            $selected = $score === null ? null : ($levelKey !== false ? (int) $levelKey : (int) max(1, min(5, round($score))));
            $rows[] = [
                'label' => $trait,
                'description' => self::DESCRIPTIONS[$trait],
                'selected' => $selected,
                'interpretation' => $interpretation ?? ($score === null ? 'Not scored' : (self::LEVELS[$selected] ?? 'Recorded')),
            ];
        }

        $request = $appointment->serviceRequest;
        $batch = $appointment->batch ?? $appointment->sourceBatch;
        $section = $appointment->origin_section ?? $batch?->year_section ?? $request?->year_section;
        $sealPath = public_path('images/psu-logo.png');
        $titlePath = public_path('images/psu-template/psu_title_gothic.png');
        $imageDataUri = static fn (string $path): string => is_file($path) ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($path)) : '';

        return [
            'view' => 'guidance.personality-assessment-report',
            'orientation' => 'portrait',
            'appointment' => $appointment,
            'title' => 'Personality Assessment',
            'brand' => [
                'seal' => $imageDataUri($sealPath), 'universityTitle' => $imageDataUri($titlePath),
                'sealPath' => is_file($sealPath) ? $sealPath : null,
                'universityTitlePath' => is_file($titlePath) ? $titlePath : null,
                'campus' => 'San Carlos City, Pangasinan', 'website' => 'www.psu.edu.ph',
                'telephone' => '(075) 206 0802', 'telefax' => '(075) 522 4201 / 4057',
            ],
            'fields' => [
                'Name' => trim(implode(' ', array_filter([$appointment->first_name, $appointment->middle_name, $appointment->last_name]))) ?: '____________________',
                'Sex' => $appointment->applicant?->gender ?: ($request?->gender ?: '____________________'),
                'Course Yr. and Section' => trim($appointment->courseLabel().($section ? ' / '.$section : '')),
                'Test Administered' => 'Big Five Personality Inventory (BFPI)',
                'Date of Examination' => $appointment->response?->created_at?->timezone('Asia/Manila')->format('F j, Y') ?? '____________________',
                'Purpose' => $request?->purpose ?: ($batch?->reason_for_request ?: 'Personality assessment'),
            ],
            'rows' => $rows,
            'legend' => self::LEVELS,
            'complete' => count(array_filter($rows, static fn (array $row): bool => $row['selected'] !== null)) === count(self::DESCRIPTIONS),
            'remarks' => $appointment->remarks ?: 'Personality ratings describe response patterns at the time of assessment and are intended for guidance discussion.',
            'counselor' => GuidanceSetting::valueOf('counselor_name') ?: 'Ms. Noemi C. Carlos',
            'orNumber' => $appointment->or_number ?: ($request?->or_number ?: '____________________'),
            'orDate' => ($appointment->or_date ?? $request?->or_date)?->format('F j, Y') ?? '____________________',
        ];
    }
}
