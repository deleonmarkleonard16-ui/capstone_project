<?php

namespace App\Services;

use App\Models\GuidanceAppointment;
use App\Models\GuidanceSetting;

class CareerAssessmentReportService
{
    private const TRAIT_DESCRIPTIONS = [
        'Realistic' => 'Hands-on problem solving, mechanical operations, and practical field activities.',
        'Investigative' => 'Research, analytical thinking, scientific ideas, and solving complex problems.',
        'Artistic' => 'Creative expression, design, writing, music, and original ideas.',
        'Social' => 'Teaching, helping, counseling, caring, and working with people.',
        'Enterprising' => 'Leading teams, persuading others, marketing, and organizing projects.',
        'Conventional' => 'Organizing records, handling numbers, and following structured procedures.',
    ];

    public function build(GuidanceAppointment $appointment): array
    {
        $appointment->loadMissing(['applicant', 'response', 'serviceRequest', 'batch', 'sourceBatch']);
        $summary = $appointment->response?->testSummaries()['career'] ?? [];
        $scores = $summary['scores'] ?? [];
        $configuredChoices = config('guidance.career.choices', []);
        $configuredTraits = array_keys(self::TRAIT_DESCRIPTIONS);
        $rows = [];

        foreach ($configuredTraits as $trait) {
            $score = $scores[$trait] ?? null;
            $score = is_numeric($score) && (int) $score >= 1 && (int) $score <= 5 ? (int) $score : null;
            $rows[] = [
                'label' => $trait,
                'description' => self::TRAIT_DESCRIPTIONS[$trait],
                'selected' => $score,
                'interpretation' => $summary['interpretation'][$trait] ?? ($score !== null ? ($configuredChoices[$score] ?? 'Recorded interest') : 'Not scored'),
            ];
        }

        $ranked = $scores;
        arsort($ranked, SORT_NUMERIC);
        $topTraits = array_slice(array_keys($ranked), 0, 3);
        $complete = count(array_filter($rows, static fn (array $row): bool => $row['selected'] !== null)) === count($configuredTraits);
        $request = $appointment->serviceRequest;
        $batch = $appointment->batch ?? $appointment->sourceBatch;
        $section = $appointment->origin_section ?? $batch?->year_section ?? $request?->year_section;
        $sealPath = public_path('images/psu-logo.png');
        $titlePath = public_path('images/psu-template/psu_title_gothic.png');
        $imageDataUri = static function (string $path): string {
            if (! is_file($path)) return '';

            return 'data:image/png;base64,'.base64_encode((string) file_get_contents($path));
        };

        return [
            'view' => 'guidance.career-assessment-report',
            'orientation' => 'portrait',
            'appointment' => $appointment,
            'title' => 'Career Assessment',
            'brand' => [
                'seal' => $imageDataUri($sealPath),
                'universityTitle' => $imageDataUri($titlePath),
                'sealPath' => is_file($sealPath) ? $sealPath : null,
                'universityTitlePath' => is_file($titlePath) ? $titlePath : null,
                'campus' => 'San Carlos City, Pangasinan',
                'website' => 'www.psu.edu.ph',
                'telephone' => '(075) 206 0802',
                'telefax' => '(075) 522 4201 / 4057',
            ],
            'fields' => [
                'Name' => trim(implode(' ', array_filter([$appointment->first_name, $appointment->middle_name, $appointment->last_name]))) ?: '____________________',
                'Sex' => $appointment->applicant?->gender ?: ($request?->gender ?: '____________________'),
                'Course Yr. and Section' => trim($appointment->courseLabel().($section ? ' / '.$section : '')),
                'Test Administered' => 'RIASEC Career Interest Assessment',
                'Date of Examination' => $appointment->response?->created_at?->timezone('Asia/Manila')->format('F j, Y') ?? '____________________',
                'Purpose' => $request?->purpose ?: ($batch?->reason_for_request ?: 'Career guidance'),
            ],
            'rows' => $rows,
            'legend' => array_map(static fn ($value): string => (string) $value, $configuredChoices),
            'topTraits' => $topTraits,
            'topCode' => implode(' - ', $topTraits),
            'recommendation' => $topTraits ? 'Explore educational and career paths aligned with '.$topTraits[0].(isset($topTraits[1]) ? ', '.$topTraits[1] : '').(isset($topTraits[2]) ? ', and '.$topTraits[2] : '').' interests.' : 'Review the recorded interests with a guidance counselor.',
            'remarks' => $appointment->remarks ?: 'Career interest ratings describe preferences and are intended for guidance discussion; they are not a clinical diagnosis.',
            'counselor' => GuidanceSetting::valueOf('counselor_name') ?: 'Ms. Noemi C. Carlos',
            'orNumber' => $appointment->or_number ?: ($request?->or_number ?: '____________________'),
            'orDate' => ($appointment->or_date ?? $request?->or_date)?->format('F j, Y') ?? '____________________',
            'complete' => $complete,
        ];
    }
}
