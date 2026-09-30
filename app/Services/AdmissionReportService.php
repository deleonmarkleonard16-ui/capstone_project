<?php

namespace App\Services;

use App\Models\AdmissionApplicant;
use App\Models\AdmissionCycle;
use Illuminate\Support\Facades\DB;

class AdmissionReportService
{
    private const BOARD_PROGRAMS = ['BEED', 'BSED-FIL', 'BSED-SOC', 'BTLEd'];

    public function cutoffs(AdmissionCycle $cycle): array
    {
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('admission_interview_cutoffs')) {
                return DB::table('admission_interview_cutoffs')
                    ->where('admission_cycle_id', $cycle->id)->pluck('top_limit', 'course_code')->all();
            }
        } catch (\Throwable $e) {
            // Gracefully return empty array if table doesn't exist
        }
        return [];
    }

    public function roster(AdmissionCycle $cycle, string $type, ?string $batch = null): array
    {
        $all = $cycle->applicants()->orderByDesc('total_score')->orderByDesc('stanine_score')
            ->orderByDesc('gwa')->orderByDesc('interview_score')->orderBy('id')->get();
        $cutoffs = $this->cutoffs($cycle);
        $quotas = DB::table('admission_course_quotas')
            ->where('admission_cycle_id', $cycle->id)->pluck('seats', 'course_code')->all();

        $interview = [];
        foreach ($all->groupBy('course_choice') as $course => $group) {
            // Interview invitations are fixed from exam-stage data, before interview scores exist.
            $eligible = $group->filter(fn ($a) => $this->examEligible($a))
                ->sort(fn ($a, $b) => $this->compareExam($a, $b))->values();
            $limit = (int) ($cutoffs[$course] ?? PHP_INT_MAX);
            foreach ($eligible as $index => $applicant) {
                $interview[$applicant->id] = $index < $limit;
            }
        }

        $final = [];
        foreach ($all->groupBy('course_choice') as $course => $group) {
            $candidates = $group->filter(fn ($a) => ($interview[$a->id] ?? false)
                && $a->interview_score !== null && (float) $a->interview_score > 0
                && $a->total_score !== null)->values();
            $seats = (int) ($quotas[$course] ?? 0);
            foreach ($candidates as $index => $applicant) {
                $final[$applicant->id] = $index < $seats ? 'Qualified for Enrollment' : 'Waitlisted / Not Qualified for Enrollment';
            }
        }

        $rows = $all->filter(fn ($a) => ($batch === null || $batch === '' || $a->batch_group === $batch) && match ($type) {
            'interview-qualifiers' => $interview[$a->id] ?? false,
            'interview-non-qualifiers' => $a->stanine_score !== null && !($interview[$a->id] ?? false),
            'final-enrollment-qualified' => ($final[$a->id] ?? null) === 'Qualified for Enrollment',
            'final-enrollment-waitlisted' => ($final[$a->id] ?? null) === 'Waitlisted / Not Qualified for Enrollment',
            default => true,
        })->values();

        $groups = [];
        foreach ($rows->groupBy('course_choice')->sortKeys() as $course => $group) {
            if (str_starts_with($type, 'interview-')) {
                $group = $group->sort(fn ($a, $b) => $this->compareExam($a, $b));
            }
            $groups[$course] = $group->values()->map(function ($a, $index) use ($type, $final) {
                $reason = match ($type) {
                    'interview-non-qualifiers' => !$this->examEligible($a) ? 'Below board program stanine 4' : 'Outside program top limit',
                    'final-enrollment-qualified', 'final-enrollment-waitlisted' => $final[$a->id] ?? '-',
                    'interview-qualifiers' => 'Qualified for Interview',
                    default => '-',
                };
                return ['rank' => $index + 1, 'applicant' => $a, 'status' => $reason];
            })->all();
        }

        return ['groups' => $groups, 'count' => $rows->count(), 'cutoffs' => $cutoffs, 'quotas' => $quotas];
    }

    private function examEligible(AdmissionApplicant $applicant): bool
    {
        if ($applicant->stanine_score === null) {
            return false;
        }
        return !in_array($applicant->course_choice, self::BOARD_PROGRAMS, true)
            || (int) $applicant->stanine_score >= 4;
    }

    private function compareExam(AdmissionApplicant $a, AdmissionApplicant $b): int
    {
        foreach (['exam_score', 'stanine_score', 'gwa'] as $field) {
            $comparison = ($b->{$field} ?? -1) <=> ($a->{$field} ?? -1);
            if ($comparison !== 0) return $comparison;
        }
        return $a->id <=> $b->id;
    }
}
