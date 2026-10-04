<?php

namespace App\Http\Controllers;

use App\Models\GuidanceAppointment;
use App\Support\CourseCatalog;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AssessmentCertificateController extends Controller
{
    private const CERTIFICATES = [
        'psychological' => ['title' => 'Certificate of Psychological Assessment', 'assessment' => 'psychological assessment'],
        'personality' => ['title' => 'Certificate of Personality Assessment', 'assessment' => 'personality assessment'],
        'career' => ['title' => 'Certificate of Career Assessment', 'assessment' => 'career assessment'],
    ];

    public function __invoke(Request $request, GuidanceAppointment $appointment): Response
    {
        abort_unless(in_array($appointment->status, ['Completed', 'Under review']), 404);
        $category = $this->category($appointment);
        abort_unless($category === $request->route('certificate_type'), 404);

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
            'title' => self::CERTIFICATES[$category]['title'],
            'assessment' => self::CERTIFICATES[$category]['assessment'],
            'studentName' => $studentName ?: 'Name not provided',
            'studentNumber' => $appointment->student_number ?: 'Not provided',
            'courseName' => CourseCatalog::label($courseCode),
            'purpose' => $purpose ?: 'Guidance assessment',
            'issuedAt' => now()->timezone('Asia/Manila')->format('F j, Y'),
        ])->header('Cache-Control', 'private, no-store')
            ->header('X-Content-Type-Options', 'nosniff');
    }

    private function category(GuidanceAppointment $appointment): ?string
    {
        if (isset(self::CERTIFICATES[$appointment->test_category])) {
            return $appointment->test_category;
        }

        return match ($appointment->test_type) {
            'dass21', 'phq9', 'gad7' => 'psychological',
            'bfpi' => 'personality',
            'career' => 'career',
            default => null,
        };
    }
}
