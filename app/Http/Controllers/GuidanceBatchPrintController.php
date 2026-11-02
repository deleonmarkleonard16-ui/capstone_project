<?php

namespace App\Http\Controllers;

use App\Models\GuidanceTestBatch;
use App\Services\CareerAssessmentReportService;
use App\Services\GuidanceCategories;
use App\Services\PersonalityAssessmentReportService;
use App\Services\PsychologicalReportService;
use Illuminate\Http\Request;

class GuidanceBatchPrintController extends Controller
{
    public function __invoke(Request $request, GuidanceTestBatch $batch)
    {
        abort_unless($batch->isArchived(), 404);
        $category = array_search($batch->test_type, GuidanceCategories::LABELS, true);
        abort_if($category === false, 404);

        $batch->load([
            'appointments.applicant', 'appointments.response', 'appointments.serviceRequest',
            'makeupAppointments.applicant', 'makeupAppointments.response', 'makeupAppointments.serviceRequest',
        ]);
        $roster = $batch->appointments->concat($batch->makeupAppointments)
            ->unique('guidance_appointment_id')->sortBy('student_id_number');
        $builder = app(match ($category) {
            'psychological' => PsychologicalReportService::class,
            'personality' => PersonalityAssessmentReportService::class,
            'career' => CareerAssessmentReportService::class,
        });
        $reports = [];
        foreach ($roster as $appointment) {
            if (!$appointment->response || !in_array($appointment->status, ['Completed', 'Under review'], true)) {
                continue;
            }
            $appointment->setRelation($appointment->batch_id ? 'batch' : 'sourceBatch', $batch);
            $document = $builder->build($appointment);
            $rows = $category === 'psychological'
                ? collect($document['matrices'])->pluck('rows')->flatten(1)
                : collect($document['rows']);
            if (!$rows->contains(fn ($row) => $row['selected'] !== null)) {
                continue;
            }
            $reports[] = array_merge($document, [
                'embedded' => true,
                'format' => 'batch',
                'autoPrint' => false,
                'administeredBy' => $request->user()->name,
                'administeredPosition' => $request->user()->roleLookup?->name
                    ?? str($request->user()->role)->headline()->toString(),
                'counselor' => 'Noemi C. Carlos, RGC',
            ]);
        }

        if ($reports === []) {
            return redirect()->route($request->user()->role.'.guidance-batches.index', [
                'archived' => 1, 'category' => $category, 'q' => $batch->batch_name,
            ])->with('error', 'This batch has no scored assessment reports available to print.');
        }

        return response()->view('guidance.batches.print', [
            'batch' => $batch, 'reports' => $reports, 'skipped' => $roster->count() - count($reports),
        ])->header('Cache-Control', 'private, no-store')->header('X-Content-Type-Options', 'nosniff');
    }
}
