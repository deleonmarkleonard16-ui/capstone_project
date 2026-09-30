<?php

namespace App\Http\Controllers;

use App\Models\GuidanceAppointment;
use App\Support\CourseCatalog;
use Illuminate\Http\Response;

class PsychologicalCertificateController extends Controller
{
    public function __invoke(GuidanceAppointment $appointment): Response
    {
        abort_unless($appointment->status === 'Completed', 404);
        abort_unless($appointment->test_category === 'psychological'
            || in_array($appointment->test_type, ['dass21', 'phq9', 'gad7'], true), 404);

        $appointment->load(['applicant', 'serviceRequest', 'response', 'batch', 'sourceBatch']);
        abort_unless($appointment->response, 404);

        $studentName = trim(implode(' ', array_filter([
            $appointment->first_name, $appointment->middle_name, $appointment->last_name,
        ])));
        $courseCode = $appointment->origin_course
            ?: ($appointment->batch?->course ?: ($appointment->sourceBatch?->course ?: $appointment->serviceRequest?->course));
        $purpose = $appointment->serviceRequest?->purpose
            ?: ($appointment->batch?->reason_for_request
                ?: ($appointment->sourceBatch?->reason_for_request ?: $appointment->reference));

        return response()->view('admin.psychological.certificate', [
            'studentName' => $studentName ?: 'Name not provided',
            'studentNumber' => $appointment->student_number ?: 'Not provided',
            'courseName' => CourseCatalog::label($courseCode),
            'purpose' => $purpose ?: 'Psychological assessment',
            'issuedAt' => now()->timezone('Asia/Manila')->format('F j, Y'),
        ])->header('Cache-Control', 'private, no-store')
            ->header('X-Content-Type-Options', 'nosniff');
    }
}
