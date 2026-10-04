<?php

namespace App\Http\Controllers;

use App\Models\GuidanceAppointment;
use App\Models\GuidanceSetting;
use Illuminate\Http\Request;

class CareerReportController extends Controller
{
    public function __invoke(Request $request, GuidanceAppointment $appointment)
    {
        abort_unless(in_array($appointment->status, ['Completed', 'Under review']) && $appointment->test_category === 'career', 404);
        $request->validate(['format' => 'nullable|in:html,pdf,docx,csv']);
        if (in_array($request->input('format'), ['pdf', 'docx', 'csv'], true)) {
            $request->merge(['type' => 'individual', 'appointment_id' => $appointment->getKey()]);

            return app(DocumentExportController::class)->export($request, 'career');
        }
        $appointment->load(['applicant', 'response', 'serviceRequest', 'batch', 'sourceBatch']);

        $summary = $appointment->response?->testSummaries()['career'] ?? null;
        $records = collect($summary['scores'] ?? []);
        if ($records->isEmpty() || $records->count() < 3) {
            return redirect()->route($request->user()->role.'.exports.index')->with('error', 'A scored career assessment is required.');
        }

        $scores = $summary['scores'];
        arsort($scores, SORT_NUMERIC);
        $traits = array_slice(array_keys($scores), 0, 3);

        $maxScore = max($scores);
        $interestLevel = match (true) {
            $maxScore >= 18 => 'Highly Interested / Strong Affinity',
            $maxScore >= 12 => 'Moderately Interested',
            default => 'Mildly Interested',
        };

        $skills = [
            'Realistic' => 'Hands-on problem solving, mechanical operations, and field execution',
            'Investigative' => 'Analytical research, critical thinking, and empirical problem investigation',
            'Artistic' => 'Creative expression, innovative design, and original concept formulation',
            'Social' => 'Interpersonal communication, educational mentoring, and empathic guidance',
            'Enterprising' => 'Strategic leadership, organizational persuasion, and venture management',
            'Conventional' => 'Systematic documentation, accurate data processing, and logistical coordination',
        ];

        $counselorName = GuidanceSetting::valueOf('counselor_name') ?: 'Ms. Noemi C. Carlos';

        $html = view('guidance.career-report', compact(
            'appointment',
            'summary',
            'traits',
            'skills',
            'interestLevel',
            'counselorName'
        ))->render();

        return response($html)->header('Cache-Control', 'no-store, private');
    }
}
