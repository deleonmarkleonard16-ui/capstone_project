<?php

namespace App\Http\Controllers;

use App\Models\GuidanceAppointment;
use App\Services\CareerAssessmentReportService;
use App\Services\DocumentExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CareerReportController extends Controller
{
    public function __invoke(Request $request, GuidanceAppointment $appointment)
    {
        abort_unless(in_array($appointment->status, ['Completed', 'Under review']) && $appointment->test_category === 'career', 404);
        $format = $request->validate(['format' => 'nullable|in:html,pdf,docx,csv'])['format'] ?? 'html';
        if ($format === 'csv') {
            $request->merge(['type' => 'individual', 'appointment_id' => $appointment->getKey()]);

            return app(DocumentExportController::class)->export($request, 'career');
        }
        $fallback = route($request->user()->role.'.exports.index');
        try {
            $document = app(CareerAssessmentReportService::class)->build($appointment);
            if (! collect($document['rows'])->contains(fn (array $row): bool => $row['selected'] !== null)) {
                return redirect($fallback)->with('error', 'A scored career assessment is required.');
            }
            $document['format'] = $format;
            $document['autoPrint'] = $format === 'html' && $request->boolean('auto_print');
            $bytes = app(DocumentExportService::class)->render($document, $format);
            $contentType = match ($format) {
                'pdf' => 'application/pdf',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                default => 'text/html; charset=UTF-8',
            };
            $headers = ['Content-Type' => $contentType, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'];
            if ($format !== 'html') {
                $headers['Content-Disposition'] = 'attachment; filename="DMSGTA-Career-Assessment-'.$appointment->getKey().'.'.$format.'"';
            }

            return response($bytes, 200, $headers);
        } catch (\Throwable $exception) {
            Log::error('Career assessment report generation failed', ['appointment_id' => $appointment->getKey(), 'format' => $format, 'exception' => $exception]);

            return redirect($fallback)->with('error', 'Unable to generate the document. Please contact the system administrator or try another format.');
        }
    }
}
