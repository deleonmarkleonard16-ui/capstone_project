<?php

namespace App\Http\Controllers;

use App\Models\AnswerSheet;
use App\Models\TestSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ExamMonitoringController extends Controller
{
    public function show(TestSession $session): View
    {
        $session->finalizeExpiredAnswerSheets();

        return view('staff.monitoring.show', [
            'session' => $session->load(['sessionApplicants.applicant']),
            'stats' => $this->buildStats($session),
        ]);
    }

    public function start(TestSession $session): RedirectResponse
    {
        if ($session->canBeStarted()) {
            $session->update([
                'started_at' => now(),
                'status' => 'in_progress',
            ]);
        }

        foreach ($session->sessionApplicants()->where('is_present', true)->get() as $assignment) {
            AnswerSheet::firstOrCreate(
                [
                    'test_session_id' => $session->id,
                    'applicant_id' => $assignment->applicant_id,
                ],
                [
                    'started_at' => now(),
                ]
            );
        }

        return redirect()
            ->route(auth()->user()->role.'.sessions.monitoring.show', $session)
            ->with('success', 'The global test timer has started.');
    }

    public function stats(TestSession $session): JsonResponse
    {
        return response()->json($this->buildStats($session));
    }

    private function buildStats(TestSession $session): array
    {
        $session->finalizeExpiredAnswerSheets();

        $assigned = $session->sessionApplicants()->count();
        $checkedIn = $session->sessionApplicants()->where('is_present', true)->count();
        $submitted = $session->answerSheets()->whereNotNull('submitted_at')->count();

        return [
            'total_applicants' => $assigned,
            'assigned' => $assigned,
            'checked_in' => $checkedIn,
            'absent' => max($assigned - $checkedIn, 0),
            'submitted' => $submitted,
            'status' => $session->fresh()->status,
            'started_at' => optional($session->fresh()->started_at)?->toDateTimeString(),
            'ends_at' => $session->fresh()->examEndsAt()->toDateTimeString(),
        ];
    }
}
