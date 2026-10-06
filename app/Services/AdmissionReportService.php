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

        $outcomes = $this->outcomesFor($all, $cutoffs, $quotas);

        $rows = $all->filter(fn ($a) => ($batch === null || $batch === '' || $a->batch_group === $batch) && match ($type) {
            'interview-qualifiers' => ($outcomes[$a->id]['interview'] ?? false),
            'interview-non-qualifiers' => $a->stanine_score !== null && !($outcomes[$a->id]['interview'] ?? false),
            'final-enrollment-qualified' => ($outcomes[$a->id]['remark'] ?? null) === 'Qualified for Enrollment',
            'final-enrollment-waitlisted' => ($outcomes[$a->id]['remark'] ?? null) === 'Waitlisted',
            default => true,
        })->values();

        $groups = [];
        foreach ($rows->groupBy('course_choice')->sortKeys() as $course => $group) {
            if (str_starts_with($type, 'interview-')) {
                $group = $group->sort(fn ($a, $b) => $this->compareExam($a, $b));
            }
            $groups[$course] = $group->values()->map(function ($a, $index) use ($type, $outcomes) {
                $reason = match ($type) {
                    'interview-non-qualifiers' => !$this->examEligible($a) ? 'Below board program stanine 4' : 'Outside program top limit',
                    'final-enrollment-qualified', 'final-enrollment-waitlisted' => $outcomes[$a->id]['remark'] ?? '-',
                    'interview-qualifiers' => 'Qualified for Interview',
                    default => '-',
                };
                return ['rank' => $index + 1, 'applicant' => $a, 'status' => $reason];
            })->all();
        }

        return ['groups' => $groups, 'count' => $rows->count(), 'cutoffs' => $cutoffs, 'quotas' => $quotas];
    }

    /**
     * The masterlist and every download must show one shared, human-readable
     * result. qualification_status remains a legacy storage field; it is not
     * the result presented to admission staff.
     *
     * @return array<int, array{interview: bool, remark: string, badge: string}>
     */
    public function outcomes(AdmissionCycle $cycle): array
    {
        $all = $cycle->applicants()->orderByDesc('total_score')->orderByDesc('stanine_score')
            ->orderByDesc('gwa')->orderByDesc('interview_score')->orderBy('id')->get();

        return $this->outcomesFor(
            $all,
            $this->cutoffs($cycle),
            DB::table('admission_course_quotas')->where('admission_cycle_id', $cycle->id)->pluck('seats', 'course_code')->all(),
        );
    }

    private function outcomesFor($all, array $cutoffs, array $quotas): array
    {
        $outcomes = [];
        foreach ($all->groupBy('course_choice') as $course => $group) {
            // Interview qualification is decided from exam remarks and the
            // saved program limit, before any interview score is entered.
            $eligible = $group->filter(fn ($a) => $this->examEligible($a))
                ->sort(fn ($a, $b) => $this->compareExam($a, $b))->values();
            $limit = (int) ($cutoffs[$course] ?? PHP_INT_MAX);
            $interviewIds = $eligible->take($limit)->pluck('id')->flip();

            $candidates = $group->filter(fn ($a) => $interviewIds->has($a->id)
                && $a->interview_score !== null && (float) $a->interview_score > 0
                && $a->total_score !== null)->values();
            $qualifiedIds = $candidates->take((int) ($quotas[$course] ?? 0))->pluck('id')->flip();

            foreach ($group as $applicant) {
                $interview = $interviewIds->has($applicant->id);
                $remark = match (true) {
                    $applicant->stanine_score === null => 'Pending exam result',
                    !$interview => 'Not Qualified for Interview',
                    $applicant->interview_score === null || $applicant->total_score === null => 'Qualified for Interview',
                    $qualifiedIds->has($applicant->id) => 'Qualified for Enrollment',
                    default => 'Waitlisted',
                };
                $outcomes[$applicant->id] = [
                    'interview' => $interview,
                    'remark' => $remark,
                    'badge' => match ($remark) {
                        'Qualified for Enrollment' => 'bg-success',
                        'Qualified for Interview', 'Waitlisted' => 'bg-warning text-dark',
                        'Not Qualified for Interview' => 'bg-danger',
                        default => 'bg-secondary',
                    },
                ];
            }
        }

        return $outcomes;
    }

    private function examEligible(AdmissionApplicant $applicant): bool
    {
        if ($applicant->stanine_score === null) {
            return false;
        }
        return ($applicant->qualification_evaluation['status'] ?? null) === 'Passed';
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
