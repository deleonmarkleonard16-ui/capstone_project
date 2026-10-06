<?php

namespace App\Http\Controllers;

use App\Models\AdmissionCycle;
use App\Services\AdmissionReportExportService;
use App\Services\AdmissionReportService;
use App\Services\AdmissionScoringService;
use App\Support\CourseCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdmissionReportController extends Controller
{
    public function psuCatQualifiers(Request $request, AdmissionReportService $reports, AdmissionReportExportService $export)
    {
        return $this->download($request, $reports, $export, 'psu-cat-qualifiers');
    }

    public function interviewQualifiers(Request $request, AdmissionReportService $reports, AdmissionReportExportService $export)
    {
        $cycle = AdmissionCycle::active();
        abort_unless($cycle, 409, 'An active admission cycle is required.');
        $data = $request->validate([
            'top_limits' => 'required|array|min:1',
            'top_limits.*' => 'required|integer|min:0|max:100000',
            'format' => ['nullable', Rule::in(['pdf', 'docx'])],
            'batch_group' => 'nullable|string|max:100',
        ]);
        $allowed = array_unique(array_merge(array_keys(CourseCatalog::allOptions()),
            $cycle->applicants()->distinct()->pluck('course_choice')->all()));
        foreach (array_keys($data['top_limits']) as $code) {
            if (!in_array($code, $allowed, true)) {
                throw ValidationException::withMessages(['top_limits' => 'A program code is invalid.']);
            }
        }
        DB::transaction(function () use ($cycle, $data) {
            foreach ($data['top_limits'] as $code => $limit) {
                DB::table('admission_interview_cutoffs')->updateOrInsert(
                    ['admission_cycle_id' => $cycle->id, 'course_code' => $code],
                    ['top_limit' => (int) $limit, 'created_at' => now(), 'updated_at' => now()]
                );
            }
        });
        app(AdmissionScoringService::class)->evaluate($cycle);

        if ($request->boolean('save_only')) {
            return back()->with('success', 'Interview top limits saved.');
        }

        return $this->download($request, $reports, $export, 'interview-qualifiers');
    }

    public function interviewNonQualifiers(Request $request, AdmissionReportService $reports, AdmissionReportExportService $export)
    {
        return $this->download($request, $reports, $export, 'interview-non-qualifiers');
    }

    public function finalEnrollmentQualified(Request $request, AdmissionReportService $reports, AdmissionReportExportService $export)
    {
        return $this->download($request, $reports, $export, 'final-enrollment-qualified');
    }

    public function finalEnrollmentWaitlisted(Request $request, AdmissionReportService $reports, AdmissionReportExportService $export)
    {
        return $this->download($request, $reports, $export, 'final-enrollment-waitlisted');
    }

    private function download(Request $request, AdmissionReportService $reports, AdmissionReportExportService $export, string $type)
    {
        $data = $request->validate([
            'format' => ['nullable', Rule::in(['pdf', 'docx'])],
            'batch_group' => 'nullable|string|max:100',
        ]);
        $cycle = AdmissionCycle::active();
        abort_unless($cycle, 409, 'An active admission cycle is required.');
        $format = $data['format'] ?? 'pdf';
        $roster = $reports->roster($cycle, $type, $data['batch_group'] ?? null);
        $bytes = $export->render($cycle, $type, $roster, $format, $data['batch_group'] ?? null);
        $mime = $format === 'pdf' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';

        return response($bytes, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'attachment; filename="PSU-CAT-'.$type.'-'.$cycle->id.'.'.$format.'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
