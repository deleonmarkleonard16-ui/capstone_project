<?php

namespace App\Http\Controllers;

use App\Models\GuidanceAppointment;
use App\Services\DocumentExportService;
use App\Services\PsychologicalReportService;
use App\Services\PersonalityAssessmentReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GuidanceReportController extends Controller
{
    public function preview(Request $request, GuidanceAppointment $appointment)
    {
        $request->validate(['auto_print' => 'nullable|boolean']);

        return $this->generate($request, $appointment, 'html');
    }

    public function download(Request $request, GuidanceAppointment $appointment)
    {
        $isPersonality = $appointment->test_category === 'personality' || $appointment->test_type === 'bfpi';
        $validated = $request->validate(['format' => $isPersonality ? 'nullable|in:pdf' : 'nullable|in:pdf,docx']);

        return $this->generate($request, $appointment, $validated['format'] ?? 'pdf');
    }

    private function generate(Request $request, GuidanceAppointment $appointment, string $format)
    {
        $isPersonality = $appointment->test_category === 'personality' || $appointment->test_type === 'bfpi';
        abort_unless($isPersonality || $appointment->test_category === 'psychological' || in_array($appointment->test_type, ['dass21', 'phq9', 'gad7'], true), 404);
        $fallback = route($request->user()->role.'.exports.index');
        try {
            $document = $isPersonality
                ? app(PersonalityAssessmentReportService::class)->build($appointment)
                : app(PsychologicalReportService::class)->build($appointment);
            $hasScoredResult = $isPersonality
                ? collect($document['rows'])->contains(fn ($row) => $row['selected'] !== null)
                : collect($document['matrices'])->pluck('rows')->flatten(1)->contains(fn ($row) => $row['selected'] !== null);
            if (! in_array($appointment->status, ['Completed', 'Under review']) || ! $hasScoredResult) {
                return redirect($fallback)->with('error', 'A completed assessment with scored results is required.');
            }
            $document['format'] = $format;
            $document['autoPrint'] = $format === 'html' && $request->boolean('auto_print');
            $bytes = app(DocumentExportService::class)->render($document, $format);
            $headers = ['Content-Type' => match ($format) {
                'pdf' => 'application/pdf',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                default => 'text/html; charset=UTF-8',
            }, 'Cache-Control' => 'private, no-store'];
            if ($format !== 'html') {
                $headers['Content-Disposition'] = 'attachment; filename="DMSGTA-'.($isPersonality ? 'Personality' : 'Psychological').'-Assessment-'.$appointment->getKey().'.'.$format.'"';
            }

            return response($bytes, 200, $headers);
        } catch (\Throwable $e) {
            Log::error('Psychological assessment report generation failed', ['appointment_id' => $appointment->getKey(), 'format' => $format, 'exception' => $e]);

            return redirect($fallback)->with('error', 'Unable to generate the document. Please contact the system administrator or try another format.');
        }
    }
}
