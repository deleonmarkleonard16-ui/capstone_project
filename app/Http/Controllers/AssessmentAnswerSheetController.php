<?php

namespace App\Http\Controllers;

use App\Models\GuidanceAppointment;
use App\Services\AssessmentAnswerSheetService;
use App\Services\DocumentExportService;

class AssessmentAnswerSheetController extends Controller
{
    public function __invoke(GuidanceAppointment $appointment)
    {
        abort_unless(in_array($appointment->status, ['Completed', 'Under review'], true) && $appointment->response, 404);
        $document = app(AssessmentAnswerSheetService::class)->build($appointment);
        abort_if(empty($document['sheets']), 404);

        return response(app(DocumentExportService::class)->render($document, 'pdf'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="DMSGTA-Answer-Sheet-'.$appointment->getKey().'.pdf"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
