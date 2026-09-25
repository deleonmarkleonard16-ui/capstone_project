<?php

namespace App\Services;

use App\Models\AdmissionApplicant;
use App\Models\AdmissionCycle;
use Illuminate\Support\Facades\DB;

class AdmissionScoringService
{
    public function rescoreCycle(AdmissionCycle $cycle): void
    {
        $key = DB::table('admission_answer_keys')
            ->where('admission_cycle_id', $cycle->id)
            ->pluck('correct_answer', 'item_number');

        if ($key->isEmpty()) {
            return;
        }

        $totalItems = max(1, (int) ($cycle->total_items ?: $key->count()));

        foreach ($cycle->applicants()->whereNotNull('submitted_at')->get() as $applicant) {
            $answers = $applicant->answers ?? [];
            $correct = $key->filter(fn ($answer, $item) => strtoupper((string) ($answers[$item] ?? '')) === $answer)->count();
            $percent = ($correct / $totalItems) * 100;
            $stanine = min(9, max(1, (int) ceil($percent / (100 / 9))));

            $applicant->forceFill([
                'exam_score' => $correct,
                'stanine_score' => $stanine,
            ])->save();
        }

        $this->evaluate($cycle);
    }

    public function submit(AdmissionApplicant $applicant, array $answers): void
    {
        DB::transaction(function () use ($applicant, $answers): void {
            $applicant = AdmissionApplicant::query()->lockForUpdate()->findOrFail($applicant->id);
            abort_if($applicant->submitted_at, 409, 'The exam has already been submitted.');

            $cycle = $applicant->cycle;
            $totalItems = max(1, (int) ($cycle?->total_items ?: 80));

            $key = DB::table('admission_answer_keys')
                ->where('admission_cycle_id', $applicant->admission_cycle_id)
                ->pluck('correct_answer', 'item_number');

            abort_unless(
                $key->count() === $totalItems && $key->keys()->sort()->values()->all() === range(1, $totalItems),
                409,
                "A complete {$totalItems}-item answer key is required."
            );

            $correct = $key->filter(fn ($answer, $item) => strtoupper((string) ($answers[$item] ?? '')) === $answer)->count();
            $percent = round(($correct / $totalItems) * 100, 2);
            $stanine = min(9, max(1, (int) ceil($percent / (100 / 9))));

            $applicant->forceFill([
                'answers' => $answers,
                'exam_score' => $correct,
                'stanine_score' => $stanine,
                'submitted_at' => now(),
                'exam_token' => null,
            ])->save();

            $this->evaluate($applicant->cycle);
            \App\Events\AdmissionSubmissionRecorded::dispatch($applicant->admission_cycle_id, $applicant->id);
        });
    }

    public function evaluate(AdmissionCycle $cycle): void
    {
        $quotas = DB::table('admission_course_quotas')
            ->where('admission_cycle_id', $cycle->id)
            ->pluck('seats', 'course_code');

        $itemCount = max(1, (int) ($cycle->total_items ?: (DB::table('admission_answer_keys')->where('admission_cycle_id', $cycle->id)->count() ?: 80)));

        foreach ($cycle->applicants()->get()->groupBy('course_choice') as $course => $group) {
            $ranked = $group->map(function (AdmissionApplicant $applicant) use ($cycle, $itemCount): AdmissionApplicant {
                $total = $applicant->exam_score === null || $applicant->gwa === null || $applicant->interview_score === null
                    ? null
                    : round(
                        (float) $applicant->exam_score / $itemCount * $cycle->exam_weight
                        + (float) $applicant->gwa * $cycle->gwa_weight / 100
                        + (float) $applicant->interview_score * $cycle->interview_weight / 100,
                        2
                    );

                $applicant->forceFill(['total_score' => $total]);
                return $applicant;
            })->sortByDesc('total_score')->values();

            $qualified = 0;
            foreach ($ranked as $applicant) {
                $eligible = $applicant->total_score !== null && $applicant->stanine_score >= $cycle->passing_stanine;
                $status = $applicant->total_score === null
                    ? 'Pending'
                    : ($eligible && $qualified < (int) ($quotas[$course] ?? 0) ? 'Qualified' : 'Not Qualified');

                if ($status === 'Qualified') {
                    $qualified++;
                }

                $applicant->forceFill(['qualification_status' => $status]);
                if ($applicant->isDirty()) {
                    $applicant->save();
                }
            }
        }
    }
}
