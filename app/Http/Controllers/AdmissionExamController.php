<?php

namespace App\Http\Controllers;

use App\Models\AdmissionApplicant;
use App\Services\AdmissionScoringService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdmissionExamController extends Controller
{
    private function applicant(string $token): AdmissionApplicant
    {
        return AdmissionApplicant::where('exam_token', $token)
            ->whereNull('submitted_at')
            ->whereHas('cycle', fn ($q) => $q->where('is_active', true)->where('is_archived', false))
            ->firstOrFail();
    }

    public function take(string $token)
    {
        $applicant = $this->applicant($token);
        $totalItems = max(1, (int) ($applicant->cycle?->total_items ?: 80));

        $count = DB::table('admission_answer_keys')
            ->where('admission_cycle_id', $applicant->admission_cycle_id)
            ->count();

        abort_if($count < $totalItems, 409, 'The examination is not configured. Please contact the proctor.');

        return view('admin.admission.take', compact('applicant', 'token', 'totalItems'));
    }

    public function submit(Request $request, string $token, AdmissionScoringService $scoring)
    {
        $applicant = $this->applicant($token);
        $totalItems = max(1, (int) ($applicant->cycle?->total_items ?: 80));

        $data = $request->validate([
            'answers'   => 'nullable|array:' . implode(',', range(1, $totalItems)),
            'answers.*' => ['nullable', Rule::in(['A', 'B', 'C', 'D'])],
        ]);
        $scoring->submit($applicant, $data['answers'] ?? []);

        return $request->expectsJson()
            ? response()->json(['done' => true])
            : view('admin.admission.complete');
    }

    /**
     * Log a security strike. On strike 3 → auto-submit the exam.
     * Dispatches Toast notification to admin dashboard via security log.
     */
    public function strike(Request $request, string $token, AdmissionScoringService $scoring)
    {
        $applicantPreCheck = $this->applicant($token);
        $totalItems = max(1, (int) ($applicantPreCheck->cycle?->total_items ?: 80));

        $data = $request->validate([
            'incident_type' => ['required', Rule::in([
                'back_button', 'print_screen', 'print', 'focus_loss',
                'fullscreen_exit', 'screenshot', 'tab_switch',
            ])],
            'answers'   => 'nullable|array:' . implode(',', range(1, $totalItems)),
            'answers.*' => ['nullable', Rule::in(['A', 'B', 'C', 'D'])],
        ]);

        $applicant = DB::transaction(function () use ($token, $data, $scoring) {
            $applicant = AdmissionApplicant::where('exam_token', $token)
                ->lockForUpdate()
                ->firstOrFail();

            if ($applicant->submitted_at) {
                return $applicant;
            }

            $applicant->increment('strike_count');

            // Log to guidance_test_security_logs (shared table, applicant_id branch)
            DB::table('guidance_test_security_logs')->insert([
                'applicant_id'  => $applicant->id,
                'incident_type' => $data['incident_type'],
                'strike_number' => $applicant->strike_count,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);

            // Auto-submit on 3rd strike
            if ($applicant->strike_count >= 3) {
                $scoring->submit($applicant, $data['answers'] ?? []);
            }

            return $applicant->fresh();
        });

        return response()->json([
            'strikes'    => $applicant->strike_count,
            'terminated' => $applicant->strike_count >= 3,
        ]);
    }

    /**
     * Security incidents feed for the admin proctoring dashboard.
     * Polls via JS, returns JSON paginated after a cursor.
     */
    public function incidents(Request $request)
    {
        $after = max(0, (int) $request->query('after', 0));

        $rows = DB::table('guidance_test_security_logs as logs')
            ->join('admission_applicants as applicants', 'applicants.id', '=', 'logs.applicant_id')
            ->select(
                'logs.log_id',
                'logs.incident_type',
                'logs.strike_number',
                'logs.created_at',
                'applicants.application_number',
                'applicants.first_name',
                'applicants.last_name',
                'applicants.course_choice',
            )
            ->where('logs.log_id', '>', $after)
            ->whereNotNull('logs.applicant_id')
            ->orderBy('logs.log_id')
            ->limit(100)
            ->get();

        return response()->json($rows);
    }
}
