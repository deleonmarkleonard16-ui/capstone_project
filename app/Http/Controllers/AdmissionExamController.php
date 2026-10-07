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
            ->firstOrFail();
    }

    private function authorizeExamBrowser(Request $request, AdmissionApplicant $applicant, string $token): void
    {
        if ($applicant->admissionSession) {
            abort_unless($applicant->admissionSession->isInProgress(), 409, 'The proctor has not launched this examination session.');
        }
        if ($request->session()->has('admission_checkin_applicant_id')) {
            abort_unless((int) $request->session()->get('admission_checkin_applicant_id') === (int) $applicant->id, 403);
        }
        if ($request->session()->has('admission_exam_token')) {
            abort_unless(hash_equals((string) $request->session()->get('admission_exam_token'), hash('sha256', $token)), 403);
        }
    }

    public function take(Request $request, string $token)
    {
        $applicant = $this->applicant($token);
        if ($applicant->admissionSession) {
            abort_unless($applicant->admissionSession->isInProgress(), 409, 'The proctor has not launched this examination session.');
        }
        if ($request->session()->has('admission_checkin_applicant_id')) {
            abort_unless((int) $request->session()->get('admission_checkin_applicant_id') === (int) $applicant->id, 403);
        }
        session([
            'admission_exam_token' => hash('sha256', $token),
            'admission_checkin_applicant_id' => $applicant->id,
        ]);
        $totalItems = max(1, (int) ($applicant->cycle?->total_items ?: 80));

        // Auto-seed / initialize answer key items if not yet configured by admin
        $existingItems = DB::table('admission_answer_keys')
            ->where('admission_cycle_id', $applicant->admission_cycle_id)
            ->pluck('item_number')
            ->all();

        if (count($existingItems) < $totalItems) {
            $now = now();
            $missing = [];
            for ($i = 1; $i <= $totalItems; $i++) {
                if (!in_array($i, $existingItems, true)) {
                    $missing[] = [
                        'admission_cycle_id' => $applicant->admission_cycle_id,
                        'item_number'        => $i,
                        'correct_answer'     => 'A',
                        'created_at'         => $now,
                        'updated_at'         => $now,
                    ];
                }
            }
            if (!empty($missing)) {
                DB::table('admission_answer_keys')->insert($missing);
            }
        }

        return response()->view('admin.admission.take', compact('applicant', 'token', 'totalItems'))
            ->header('Cache-Control', 'no-store, private');
    }

    public function state(Request $request, string $token)
    {
        $applicant = AdmissionApplicant::where('exam_token', $token)->first();
        if (!$applicant && $request->session()->has('admission_checkin_applicant_id')) {
            $applicant = AdmissionApplicant::find($request->session()->get('admission_checkin_applicant_id'));
        }

        if (!$applicant) {
            return response()->json(['valid' => false, 'terminated' => false], 404);
        }

        $isTerminated = (int) $applicant->strike_count >= 3;
        $isSubmitted  = $applicant->submitted_at !== null && !$isTerminated;

        return response()->json([
            'valid'               => true,
            'applicant_id'        => $applicant->id,
            'strike_count'        => (int) $applicant->strike_count,
            'terminated'          => $isTerminated,
            'submitted'           => $isSubmitted,
            'session_in_progress' => $applicant->admissionSession?->isInProgress() ?? true,
            'take_url'            => route('admission.take', $applicant->exam_token ?? $token),
            'answers'             => $applicant->answers ?? [],
        ])->header('Cache-Control', 'no-store, private');
    }

    public function terminated(Request $request)
    {
        $applicantId = session('admission_checkin_applicant_id');
        $applicant = $applicantId ? AdmissionApplicant::find($applicantId) : null;
        $token = $applicant?->exam_token ?? '';

        return view('admin.admission.terminated', compact('applicant', 'token'));
    }

    public function submit(Request $request, string $token, AdmissionScoringService $scoring)
    {
        $applicant = $this->applicant($token);
        $this->authorizeExamBrowser($request, $applicant, $token);
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
        $this->authorizeExamBrowser($request, $applicantPreCheck, $token);
        $totalItems = max(1, (int) ($applicantPreCheck->cycle?->total_items ?: 80));

        $data = $request->validate([
            'incident_type' => ['required', 'string', 'max:100'],
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

            // Save answers so far in applicant model
            if (isset($data['answers']) && is_array($data['answers'])) {
                $applicant->forceFill(['answers' => $data['answers']])->save();
            }

            // Log to guidance_test_security_logs (shared table, applicant_id branch)
            DB::table('guidance_test_security_logs')->insert([
                'applicant_id'  => $applicant->id,
                'student_id' => $applicant->student_id ?: $applicant->application_number,
                'course_program' => $applicant->course_choice ?: 'Not provided',
                'current_test_taking' => 'PSU College Admission Test',
                'incident_type' => $data['incident_type'],
                'strike_number' => $applicant->strike_count,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);

            // Auto-submit on 3rd strike
            if ($applicant->strike_count >= 3) {
                $scoring->submit($applicant, $data['answers'] ?? ($applicant->answers ?? []));
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
                'applicants.id as applicant_id',
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
