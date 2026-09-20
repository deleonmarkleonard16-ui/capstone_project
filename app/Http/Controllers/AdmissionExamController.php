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
        return AdmissionApplicant::where('exam_token', $token)->whereNull('submitted_at')->firstOrFail();
    }

    public function take(string $token)
    {
        $applicant = $this->applicant($token);
        $count = DB::table('admission_answer_keys')->where('admission_cycle_id', $applicant->admission_cycle_id)->count();
        abort_if($count < 80, 409, 'The examination is not configured.');
        return view('admin.admission.take', compact('applicant', 'token'));
    }

    public function submit(Request $request, string $token, AdmissionScoringService $scoring)
    {
        $applicant = $this->applicant($token);
        $data = $request->validate(['answers' => 'nullable|array', 'answers.*' => ['nullable', Rule::in(['A', 'B', 'C', 'D'])]]);
        $scoring->submit($applicant, $data['answers'] ?? []);
        return $request->expectsJson() ? response()->json(['done' => true]) : view('admin.admission.complete');
    }

    public function strike(Request $request, string $token, AdmissionScoringService $scoring)
    {
        $data = $request->validate(['incident_type' => ['required', Rule::in(['back_button', 'print_screen', 'print', 'focus_loss'])]]);
        $applicant = DB::transaction(function () use ($token, $data) {
            $applicant = AdmissionApplicant::where('exam_token', $token)->lockForUpdate()->firstOrFail();
            if ($applicant->submitted_at) return $applicant;
            $applicant->increment('strike_count');
            DB::table('guidance_test_security_logs')->insert(['applicant_id' => $applicant->id, 'incident_type' => $data['incident_type'], 'strike_number' => $applicant->strike_count, 'created_at' => now(), 'updated_at' => now()]);
            return $applicant->fresh();
        });
        if ($applicant->strike_count >= 3 && ! $applicant->submitted_at) $scoring->submit($applicant, $request->input('answers', []));
        return response()->json(['strikes' => $applicant->strike_count, 'terminated' => $applicant->strike_count >= 3]);
    }

    public function incidents(Request $request)
    {
        $after = max(0, (int) $request->query('after', 0));
        return response()->json(DB::table('guidance_test_security_logs as logs')->join('admission_applicants as applicants', 'applicants.id', '=', 'logs.applicant_id')
            ->select('logs.*', 'applicants.application_number', 'applicants.first_name', 'applicants.last_name')
            ->where('logs.log_id', '>', $after)->orderBy('logs.log_id')->limit(100)->get());
    }
}
