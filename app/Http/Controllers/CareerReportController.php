<?php

namespace App\Http\Controllers;

use App\Models\GuidanceAppointment;
use App\Models\GuidanceSetting;
use Illuminate\Http\Request;

class CareerReportController extends Controller
{
    public function __invoke(Request $request, GuidanceAppointment $appointment)
    {
        abort_unless($appointment->status === 'Completed' && $appointment->test_category === 'career', 404);
        $request->validate(['format' => 'nullable|in:html,pdf']);
        $appointment->load(['applicant', 'response', 'serviceRequest']);

        $summary = $appointment->response?->testSummaries()['career'] ?? null;
        abort_unless($summary && count($summary['scores'] ?? []) >= 3, 409, 'A scored career assessment is required.');

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

        if ($request->query('format') === 'pdf') {
            $pdf = new \Dompdf\Dompdf(['isRemoteEnabled' => false]);
            $pdf->loadHtml($html);
            $pdf->setPaper('A4', 'portrait');
            $pdf->render();
            return response($pdf->output())
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="career-assessment-evaluation.pdf"');
        }

        return response($html);
    }
}
